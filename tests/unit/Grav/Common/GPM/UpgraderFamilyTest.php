<?php

namespace Grav\Common\GPM;

use PHPUnit\Framework\TestCase;

/**
 * Testable subclass that bypasses the GravCore HTTP constructor so we can
 * inject arbitrary local/remote version strings without any HTTP calls.
 *
 * Also stubs the `next_major` hint path — the real Upgrader reads that via
 * $this->remote->getNextMajor(), which we emulate with an injected array.
 */
class TestableUpgrader extends Upgrader
{
    /** @var string */
    private $localVersion;
    /** @var string */
    private $remoteVersion;
    /** @var array|null */
    private $nextMajor;

    public function __construct(string $local, string $remote, ?array $nextMajor = null)
    {
        // Intentionally skip parent constructor — no HTTP, no Grav bootstrap needed.
        $this->localVersion  = $local;
        $this->remoteVersion = $remote;
        $this->nextMajor     = $nextMajor;
    }

    public function getLocalVersion(): string
    {
        return $this->localVersion;
    }

    public function getRemoteVersion(): string
    {
        return $this->remoteVersion;
    }

    // Override isNextMajorAvailable so tests don't hit the (unset) GravCore remote.
    public function isNextMajorAvailable(): bool
    {
        if (!$this->nextMajor || empty($this->nextMajor['version'])) {
            return false;
        }

        $localMajor = (int) explode('.', $this->getLocalVersion())[0];
        $nextMajor  = (int) explode('.', (string) $this->nextMajor['version'])[0];

        return $nextMajor > $localMajor;
    }
}

class UpgraderFamilyTest extends TestCase
{
    private function make(string $local, string $remote, ?array $nextMajor = null): TestableUpgrader
    {
        return new TestableUpgrader($local, $remote, $nextMajor);
    }

    // ------------------------------------------------------------------
    // Cross-family: upgrades MUST be blocked
    // ------------------------------------------------------------------

    public function testOneEightToTwoZeroIsBlocked(): void
    {
        // Pre family-aware server (or client not yet supporting the hint) — cross-family raw remote.
        // Upgrade still must be blocked; notice fires only when server sends next_major hint.
        $u = $this->make('1.8.0-beta.28', '2.0.0', ['version' => '2.0.0']);
        $this->assertFalse($u->isUpgradable(), '1.8→2.0 must be blocked');
        $this->assertTrue($u->isNextMajorAvailable(), '2.0 notice must fire when server hints next_major');
    }

    public function testOneSevenToTwoZeroIsBlocked(): void
    {
        // Family-aware: server returns 1.7.x to a 1.7.x client and hints next_major=2.0.0.
        $u = $this->make('1.7.49', '1.7.49', ['version' => '2.0.0']);
        $this->assertFalse($u->isUpgradable(), '1.7 client must not self-upgrade across majors');
        $this->assertTrue($u->isNextMajorAvailable());
    }

    public function testOneSevenToOneEightIsBlocked(): void
    {
        // Different minor family — should also be blocked
        $u = $this->make('1.7.49', '1.8.0-beta.1');
        $this->assertFalse($u->isUpgradable(), '1.7→1.8 must be blocked');
        $this->assertFalse($u->isNextMajorAvailable(), 'no major increment, so no notice');
    }

    // ------------------------------------------------------------------
    // Same family: upgrades MUST be allowed when remote is newer
    // ------------------------------------------------------------------

    public function testOneSevenSameFamilyUpgrade(): void
    {
        $u = $this->make('1.7.48', '1.7.49');
        $this->assertTrue($u->isUpgradable());
        $this->assertFalse($u->isNextMajorAvailable());
    }

    public function testOneEightPrereleaseUpgrade(): void
    {
        $u = $this->make('1.8.0-beta.28', '1.8.0-beta.29');
        $this->assertTrue($u->isUpgradable());
        $this->assertFalse($u->isNextMajorAvailable());
    }

    public function testTwoZeroSameFamilyUpgrade(): void
    {
        $u = $this->make('2.0.0', '2.0.1');
        $this->assertTrue($u->isUpgradable());
        $this->assertFalse($u->isNextMajorAvailable());
    }

    // ------------------------------------------------------------------
    // Same version: not upgradable
    // ------------------------------------------------------------------

    public function testSameVersionNotUpgradable(): void
    {
        $this->assertFalse($this->make('1.8.0-beta.28', '1.8.0-beta.28')->isUpgradable());
        $this->assertFalse($this->make('1.7.49', '1.7.49')->isUpgradable());
        $this->assertFalse($this->make('2.0.0', '2.0.0')->isUpgradable());
    }

    // ------------------------------------------------------------------
    // isNextMajorAvailable: only fires on a true major increment
    // ------------------------------------------------------------------

    public function testNextMajorNotFiredForMinorIncrement(): void
    {
        // 1.8 → 1.9 is a different minor, not a new major
        $this->assertFalse($this->make('1.8.0', '1.9.0')->isNextMajorAvailable());
    }

    public function testNextMajorFiredCorrectly(): void
    {
        // Hint-driven: notice fires when server advertises a newer major than local.
        $this->assertTrue($this->make('1.9.0', '1.9.0', ['version' => '2.0.0'])->isNextMajorAvailable());
        $this->assertTrue($this->make('2.1.0', '2.1.0', ['version' => '3.0.0'])->isNextMajorAvailable());
    }

    public function testNextMajorNotFiredWhenOnTwoZero(): void
    {
        // On 2.0, the 2.0.x server would not advertise a next_major pointing back at 2.x.
        $this->assertFalse($this->make('2.0.0', '2.0.1')->isNextMajorAvailable());
        $this->assertFalse($this->make('2.0.0', '2.1.0')->isNextMajorAvailable());
        // Even if a stale hint slipped through, a same-major hint must not fire.
        $this->assertFalse($this->make('2.0.0', '2.0.1', ['version' => '2.0.1'])->isNextMajorAvailable());
    }

    public function testNextMajorNotFiredWithoutHint(): void
    {
        // No next_major hint from server → notice must not fire regardless of remote version.
        $this->assertFalse($this->make('1.9.0', '2.0.0')->isNextMajorAvailable());
    }
}
