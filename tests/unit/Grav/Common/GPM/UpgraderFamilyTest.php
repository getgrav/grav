<?php

namespace Grav\Common\GPM;

use PHPUnit\Framework\TestCase;

/**
 * Testable subclass that bypasses the GravCore HTTP constructor so we can
 * inject arbitrary local/remote version strings without any HTTP calls.
 */
class TestableUpgrader extends Upgrader
{
    private string $localVersion;
    private string $remoteVersion;

    public function __construct(string $local, string $remote)
    {
        // Intentionally skip parent constructor — no HTTP, no Grav bootstrap needed.
        $this->localVersion  = $local;
        $this->remoteVersion = $remote;
    }

    public function getLocalVersion(): string
    {
        return $this->localVersion;
    }

    public function getRemoteVersion(): string
    {
        return $this->remoteVersion;
    }
}

class UpgraderFamilyTest extends TestCase
{
    private function make(string $local, string $remote): TestableUpgrader
    {
        return new TestableUpgrader($local, $remote);
    }

    // ------------------------------------------------------------------
    // Cross-family: upgrades MUST be blocked
    // ------------------------------------------------------------------

    public function testOneEightToTwoZeroIsBlocked(): void
    {
        $u = $this->make('1.8.0-beta.28', '2.0.0');
        $this->assertFalse($u->isUpgradable(), '1.8→2.0 must be blocked');
        $this->assertTrue($u->isNextMajorAvailable(), '2.0 notice must fire');
    }

    public function testOneSevenToTwoZeroIsBlocked(): void
    {
        $u = $this->make('1.7.49', '2.0.0');
        $this->assertFalse($u->isUpgradable(), '1.7→2.0 must be blocked');
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
        $this->assertTrue($this->make('1.9.0', '2.0.0')->isNextMajorAvailable());
        $this->assertTrue($this->make('2.1.0', '3.0.0')->isNextMajorAvailable());
    }

    public function testNextMajorNotFiredWhenOnTwoZero(): void
    {
        $this->assertFalse($this->make('2.0.0', '2.0.1')->isNextMajorAvailable());
        $this->assertFalse($this->make('2.0.0', '2.1.0')->isNextMajorAvailable());
    }
}
