<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Common\Security;
use Grav\Common\Filesystem\Folder;

/**
 * Class NonceKeySecurityTest
 *
 * Covers: GHSA-3f29-pqwf-v4j4 (salt leak via sandboxed Twig `grav.config.get('security.salt')`).
 *
 * Verifies `Security::getNonceKey()`:
 *   - reads the value from `user/config/security-private.php` when present,
 *   - migrates a legacy `security.salt` out of Config on first call (preserving the value
 *     so existing nonces/sessions survive), and scrubs it from the loaded Config,
 *   - generates a fresh 64-char hex value for a clean install,
 *   - is stable within a request (subsequent calls return the same value).
 */
class NonceKeySecurityTest extends \PHPUnit\Framework\TestCase
{
    /** @var Grav */
    protected $grav;

    /** @var string */
    protected $configFolder;

    /** @var string */
    protected $privateFile;

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $this->grav = $grav();

        /** @var \RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator $locator */
        $locator = $this->grav['locator'];
        $this->configFolder = $locator->findResource('config://', true) ?: $locator->findResource('config://', true, true);
        $this->privateFile = "{$this->configFolder}/security-private.php";

        // Ensure a clean slate.
        $this->resetStaticCache();
        if (is_file($this->privateFile)) {
            @unlink($this->privateFile);
        }
        if (is_file("{$this->privateFile}.tmp")) {
            @unlink("{$this->privateFile}.tmp");
        }
        $this->grav['config']->set('security.salt', null);
    }

    protected function tearDown(): void
    {
        if (is_file($this->privateFile)) {
            @unlink($this->privateFile);
        }
        if (is_file("{$this->privateFile}.tmp")) {
            @unlink("{$this->privateFile}.tmp");
        }
        // The migration test path can rewrite security.yaml to drop the salt key;
        // delete the empty file so we don't leave per-environment residue behind.
        $securityYaml = "{$this->configFolder}/security.yaml";
        if (is_file($securityYaml) && trim((string) @file_get_contents($securityYaml)) === '{  }') {
            @unlink($securityYaml);
        }
        $this->resetStaticCache();
        parent::tearDown();
    }

    private function resetStaticCache(): void
    {
        $reflection = new ReflectionClass(Security::class);
        if ($reflection->hasProperty('nonceKey')) {
            $p = $reflection->getProperty('nonceKey');
            $p->setAccessible(true);
            $p->setValue(null, null);
        }
    }

    // =========================================================================
    // GHSA-3f29-pqwf-v4j4: legacy security.salt migrated out of Config
    // =========================================================================

    public function testGetNonceKey_GHSA3f29_MigratesLegacySaltAndScrubsFromConfig(): void
    {
        $legacy = 'legacy-salt-value-from-existing-install';
        $this->grav['config']->set('security.salt', $legacy);

        $key = Security::getNonceKey();

        self::assertSame($legacy, $key, 'value should be preserved so existing nonces/sessions survive');
        self::assertNull(
            $this->grav['config']->get('security.salt'),
            'GHSA-3f29: security.salt must be scrubbed from live Config after migration (sandboxed Twig cannot read it)'
        );
        self::assertFileExists($this->privateFile, 'migration writes the value to the private file');
    }

    public function testGetNonceKey_GHSA3f29_ReadsFromPrivateFileOnSubsequentCalls(): void
    {
        $value = str_repeat('a', 64);
        file_put_contents($this->privateFile, "<?php\nreturn " . var_export($value, true) . ";\n");

        self::assertSame($value, Security::getNonceKey());
    }

    public function testGetNonceKey_GeneratesFreshKeyForCleanInstall(): void
    {
        // No legacy salt, no private file.
        $key = Security::getNonceKey();

        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $key, 'fresh install should generate 64-char hex key');
        self::assertFileExists($this->privateFile);

        // Re-read via a fresh cache to confirm persistence.
        $this->resetStaticCache();
        self::assertSame($key, Security::getNonceKey(), 'value must persist across cache resets (i.e. across requests)');
    }

    public function testGetNonceKey_IsStableWithinRequest(): void
    {
        $a = Security::getNonceKey();
        $b = Security::getNonceKey();
        self::assertSame($a, $b);
    }

    public function testGetNonceKey_IgnoresEmptyStringInConfig(): void
    {
        // An empty `salt:` in user config should NOT be migrated; we should generate instead.
        $this->grav['config']->set('security.salt', '');

        $key = Security::getNonceKey();
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $key);
    }
}
