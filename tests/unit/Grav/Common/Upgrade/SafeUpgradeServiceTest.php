<?php

use Grav\Common\Filesystem\Folder;
use Grav\Common\Upgrade\SafeUpgradeService;

class SafeUpgradeServiceTest extends \Codeception\TestCase\Test
{
    /** @var string */
    private $tmpDir;

    protected function _before(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/grav-safe-upgrade-' . uniqid('', true);
        Folder::create($this->tmpDir);
    }

    protected function _after(): void
    {
        if (is_dir($this->tmpDir)) {
            Folder::delete($this->tmpDir);
        }
    }

    public function testPreflightAggregatesWarnings(): void
    {
        $service = new class(['root' => $this->tmpDir]) extends SafeUpgradeService {
            public $pending = [
                'alpha' => ['type' => 'plugins', 'current' => '1.0.0', 'available' => '1.1.0']
            ];
            public $conflicts = [
                'beta' => ['requires' => '^1.0']
            ];
            public $monolog = [
                'gamma' => [
                    ['file' => 'user/plugins/gamma/gamma.php', 'method' => '->addError(']
                ]
            ];

            protected function detectPendingPluginUpdates(): array
            {
                return $this->pending;
            }

            protected function detectPsrLogConflicts(): array
            {
                return $this->conflicts;
            }

            protected function detectMonologConflicts(): array
            {
                return $this->monolog;
            }
        };

        $result = $service->preflight();

        self::assertArrayHasKey('warnings', $result);
        self::assertCount(3, $result['warnings']);
        self::assertArrayHasKey('alpha', $result['plugins_pending']);
        self::assertArrayHasKey('beta', $result['psr_log_conflicts']);
        self::assertArrayHasKey('gamma', $result['monolog_conflicts']);
    }

    public function testPreflightHandlesDetectionFailure(): void
    {
        $service = new class(['root' => $this->tmpDir]) extends SafeUpgradeService {
            protected function detectPendingPluginUpdates(): array
            {
                throw new RuntimeException('Cannot reach GPM');
            }

            protected function detectPsrLogConflicts(): array
            {
                return [];
            }

            protected function detectMonologConflicts(): array
            {
                return [];
            }
        };

        $result = $service->preflight();

        self::assertSame([], $result['plugins_pending']);
        self::assertSame([], $result['psr_log_conflicts']);
        self::assertSame([], $result['monolog_conflicts']);
        self::assertCount(1, $result['warnings']);
        self::assertStringContainsString('Cannot reach GPM', $result['warnings'][0]);
    }

    public function testPromoteAndRollback(): void
    {
        [$root, $manifestStore] = $this->prepareLiveEnvironment();
        $service = new SafeUpgradeService([
            'root' => $root,
            'manifest_store' => $manifestStore,
        ]);

        $package = $this->preparePackage();
        $manifest = $service->promote($package, '1.8.0', ['backup', 'cache', 'images', 'logs', 'tmp', 'user']);

        self::assertFileExists($root . '/system/new.txt');
        self::assertFileExists($root . '/ORIGINAL');

        $manifestFile = $manifestStore . '/' . $manifest['id'] . '.json';
        self::assertFileExists($manifestFile);

        $service->rollback($manifest['id']);

        self::assertFileExists($root . '/ORIGINAL');
        self::assertFileDoesNotExist($root . '/system/new.txt');

        self::assertDirectoryExists($manifest['backup_path']);
    }

    public function testKeepsAllSnapshots(): void
    {
        [$root, $manifestStore] = $this->prepareLiveEnvironment();
        $service = new SafeUpgradeService([
            'root' => $root,
            'manifest_store' => $manifestStore,
        ]);

        $manifests = [];
        for ($i = 0; $i < 4; $i++) {
            $package = $this->preparePackage((string)$i);
            $manifests[] = $service->promote($package, '1.8.' . $i, ['backup', 'cache', 'images', 'logs', 'tmp', 'user']);
            // Ensure subsequent promotions have a marker to restore.
            file_put_contents($root . '/ORIGINAL', 'state-' . $i);
        }

        $files = glob($manifestStore . '/*.json');
        self::assertCount(4, $files);
        self::assertTrue(is_dir($manifests[0]['backup_path']));
    }

    public function testDetectsPsrLogConflictsFromFilesystem(): void
    {
        [$root] = $this->prepareLiveEnvironment();
        $plugin = $root . '/user/plugins/problem';
        Folder::create($plugin);
        file_put_contents($plugin . '/composer.json', json_encode(['require' => ['psr/log' => '^1.0']], JSON_PRETTY_PRINT));

        $service = new SafeUpgradeService([
            'root' => $root,
        ]);

        $method = new ReflectionMethod(SafeUpgradeService::class, 'detectPsrLogConflicts');
        $method->setAccessible(true);
        $conflicts = $method->invoke($service);

        self::assertArrayHasKey('problem', $conflicts);
    }

    public function testDetectsMonologConflictsFromFilesystem(): void
    {
        [$root] = $this->prepareLiveEnvironment();
        $plugin = $root . '/user/plugins/logger';
        Folder::create($plugin . '/src');
        $code = <<<'PHP'
<?php
class LoggerTest {
    public function test(
        \Monolog\Logger $logger
    ) {
        $logger->addError('deprecated');
    }
}
PHP;
        file_put_contents($plugin . '/src/logger.php', $code);

        $service = new SafeUpgradeService([
            'root' => $root,
        ]);

        $method = new ReflectionMethod(SafeUpgradeService::class, 'detectMonologConflicts');
        $method->setAccessible(true);
        $conflicts = $method->invoke($service);

        self::assertArrayHasKey('logger', $conflicts);
        self::assertNotEmpty($conflicts['logger']);
        self::assertStringContainsString('addError', $conflicts['logger'][0]['method']);
    }

    public function testClearRecoveryFlagRemovesFile(): void
    {
        [$root] = $this->prepareLiveEnvironment();
        $flag = $root . '/user/data/recovery.flag';
        $window = $root . '/user/data/recovery.window';
        Folder::create(dirname($flag));
        file_put_contents($flag, 'flag');
        Folder::create(dirname($window));
        file_put_contents($window, json_encode(['expires_at' => time() + 120]));

        $service = new SafeUpgradeService([
            'root' => $root,
        ]);
        $service->clearRecoveryFlag();

        self::assertFileDoesNotExist($flag);
        self::assertFileExists($window);
    }

    /**
     * @return array{0:string,1:string}
     */
    private function prepareLiveEnvironment(): array
    {
        $root = $this->tmpDir . '/root';
        $manifestStore = $root . '/user/data/upgrades';

        Folder::create($root . '/user/plugins/sample');
        Folder::create($root . '/system');
        file_put_contents($root . '/system/original.txt', 'original');
        file_put_contents($root . '/ORIGINAL', 'original-root');
        file_put_contents($root . '/user/plugins/sample/blueprints.yaml', "name: Sample Plugin\nversion: 1.0.0\n");
        file_put_contents($root . '/user/plugins/sample/composer.json', json_encode(['require' => ['php' => '^8.0']], JSON_PRETTY_PRINT));

        return [$root, $manifestStore];
    }

    /**
     * @param string $suffix
     * @return string
     */
    private function preparePackage(string $suffix = ''): string
    {
        $package = $this->tmpDir . '/package-' . uniqid('', true);
        Folder::create($package . '/system');
        Folder::create($package . '/user');
        file_put_contents($package . '/index.php', 'new-release' . $suffix);
        file_put_contents($package . '/system/new.txt', 'release' . $suffix);

        return $package;
    }
}
