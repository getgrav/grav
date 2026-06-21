<?php

use Grav\Common\Filesystem\Folder;
use Grav\Installer\Install;

class InstallCompatibilityTest extends \PHPUnit\Framework\TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/grav-compat-test-' . uniqid();
        mkdir($this->tmpDir . '/user/plugins', 0755, true);
        mkdir($this->tmpDir . '/user/themes', 0755, true);
        mkdir($this->tmpDir . '/user/config/plugins', 0755, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tmpDir)) {
            Folder::delete($this->tmpDir);
        }
    }

    public function testReadBlueprintCompatibilityExplicit(): void
    {
        $dir = $this->createPlugin('test-plugin', [
            'name' => 'Test Plugin',
            'version' => '1.0.0',
            'compatibility' => [
                'grav' => ['1.7', '1.8'],
                'api' => ['1.0'],
            ],
        ]);

        $result = $this->callMethod('readBlueprintCompatibility', [$dir]);
        self::assertSame(['1.7', '1.8'], $result['grav']);
        self::assertSame(['1.0'], $result['api']);
    }

    public function testReadBlueprintCompatibilityInferredFrom18Dep(): void
    {
        $dir = $this->createPlugin('test-18', [
            'name' => 'Test 1.8',
            'version' => '2.0.0',
            'dependencies' => [
                ['name' => 'grav', 'version' => '>=1.8.0'],
            ],
        ]);

        $result = $this->callMethod('readBlueprintCompatibility', [$dir]);
        self::assertSame(['1.8'], $result['grav']);
        self::assertSame([], $result['api']);
    }

    public function testReadBlueprintCompatibilityInferredFrom17Dep(): void
    {
        $dir = $this->createPlugin('test-17', [
            'name' => 'Test 1.7',
            'version' => '1.0.0',
            'dependencies' => [
                ['name' => 'grav', 'version' => '>=1.7.0'],
            ],
        ]);

        $result = $this->callMethod('readBlueprintCompatibility', [$dir]);
        self::assertSame(['1.7'], $result['grav']);
    }

    public function testReadBlueprintCompatibilityNoDependency(): void
    {
        $dir = $this->createPlugin('test-none', [
            'name' => 'Test None',
            'version' => '1.0.0',
        ]);

        $result = $this->callMethod('readBlueprintCompatibility', [$dir]);
        self::assertSame(['1.7'], $result['grav']);
    }

    public function testReadBlueprintCompatibilityMissingFile(): void
    {
        $dir = $this->tmpDir . '/user/plugins/no-blueprint';
        mkdir($dir, 0755, true);

        $result = $this->callMethod('readBlueprintCompatibility', [$dir]);
        self::assertSame([], $result['grav']);
    }

    public function testInferCompatibleVersionsVariousConstraints(): void
    {
        $install = Install::instance();

        // >=1.8.0
        $result = $this->callMethod('inferCompatibleVersions', [[['name' => 'grav', 'version' => '>=1.8.0']]]);
        self::assertSame(['1.8'], $result['grav']);

        // ~1.8
        $result = $this->callMethod('inferCompatibleVersions', [[['name' => 'grav', 'version' => '~1.8']]]);
        self::assertSame(['1.8'], $result['grav']);

        // >=1.7.0,<2.0
        $result = $this->callMethod('inferCompatibleVersions', [[['name' => 'grav', 'version' => '>=1.7.0,<2.0']]]);
        self::assertSame(['1.7'], $result['grav']);

        // No grav dep
        $result = $this->callMethod('inferCompatibleVersions', [[['name' => 'form', 'version' => '>=6.0']]]);
        self::assertSame(['1.7'], $result['grav']);

        // Empty
        $result = $this->callMethod('inferCompatibleVersions', [[]]);
        self::assertSame(['1.7'], $result['grav']);
    }

    public function testDetectIncompatiblePackagesBlocksEnabled(): void
    {
        $this->createPlugin('incompatible-enabled', [
            'name' => 'Incompatible Enabled',
            'version' => '1.0.0',
            'compatibility' => ['grav' => ['1.7']],
        ]);
        // Plugin enabled by default (no config file = enabled)

        $result = $this->callMethod('detectIncompatiblePackages', ['1.8.0', $this->tmpDir]);
        self::assertArrayHasKey('incompatible-enabled', $result['blocking']);
        self::assertSame('1.8', $result['target']);
    }

    public function testDetectIncompatiblePackagesWarnsDisabled(): void
    {
        $this->createPlugin('incompatible-disabled', [
            'name' => 'Incompatible Disabled',
            'version' => '1.0.0',
            'compatibility' => ['grav' => ['1.7']],
        ]);
        // Disable it
        file_put_contents(
            $this->tmpDir . '/user/config/plugins/incompatible-disabled.yaml',
            "enabled: false\n"
        );

        $result = $this->callMethod('detectIncompatiblePackages', ['1.8.0', $this->tmpDir]);
        self::assertArrayHasKey('incompatible-disabled', $result['warnings']);
        self::assertEmpty($result['blocking']);
    }

    public function testDetectIncompatiblePackagesPassesCompatible(): void
    {
        $this->createPlugin('compatible-plugin', [
            'name' => 'Compatible Plugin',
            'version' => '2.0.0',
            'compatibility' => ['grav' => ['1.7', '1.8']],
        ]);

        $result = $this->callMethod('detectIncompatiblePackages', ['1.8.0', $this->tmpDir]);
        self::assertArrayNotHasKey('compatible-plugin', $result['blocking']);
        self::assertArrayNotHasKey('compatible-plugin', $result['warnings']);
    }

    /**
     * Create a plugin directory with a blueprints.yaml file.
     */
    private function createPlugin(string $slug, array $blueprint): string
    {
        $dir = $this->tmpDir . '/user/plugins/' . $slug;
        mkdir($dir, 0755, true);

        $yaml = \Symfony\Component\Yaml\Yaml::dump($blueprint, 4);
        file_put_contents($dir . '/blueprints.yaml', $yaml);

        return $dir;
    }

    /**
     * Call a private/protected method on the Install singleton via reflection.
     */
    private function callMethod(string $method, array $args): mixed
    {
        $install = Install::instance();
        $ref = new \ReflectionMethod($install, $method);
        $ref->setAccessible(true);

        return $ref->invoke($install, ...$args);
    }
}
