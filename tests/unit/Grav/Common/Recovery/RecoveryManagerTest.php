<?php

use Grav\Common\Filesystem\Folder;
use Grav\Common\Recovery\RecoveryManager;

class RecoveryManagerTest extends \Codeception\TestCase\Test
{
    /** @var string */
    private $tmpDir;

    protected function _before(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/grav-recovery-' . uniqid('', true);
        Folder::create($this->tmpDir);
        Folder::create($this->tmpDir . '/user');
        Folder::create($this->tmpDir . '/system');
    }

    protected function _after(): void
    {
        if (is_dir($this->tmpDir)) {
            Folder::delete($this->tmpDir);
        }
    }

    public function testHandleShutdownQuarantinesPluginAndCreatesFlag(): void
    {
        $plugin = $this->tmpDir . '/user/plugins/bad';
        Folder::create($plugin);
        file_put_contents($plugin . '/plugin.php', '<?php // plugin');

        $manager = new class($this->tmpDir) extends RecoveryManager {
            protected $error;
            public function __construct(string $rootPath)
            {
                parent::__construct($rootPath);
                $this->error = [
                    'type' => E_ERROR,
                    'file' => $this->getRootPath() . '/user/plugins/bad/plugin.php',
                    'message' => 'Fatal failure',
                    'line' => 42,
                ];
            }

            public function getRootPath(): string
            {
                $prop = new \ReflectionProperty(RecoveryManager::class, 'rootPath');
                $prop->setAccessible(true);

                return $prop->getValue($this);
            }

            protected function resolveLastError(): ?array
            {
                return $this->error;
            }
        };

        $manager->handleShutdown();

        $flag = $this->tmpDir . '/system/recovery.flag';
        self::assertFileExists($flag);
        $context = json_decode(file_get_contents($flag), true);
        self::assertSame('Fatal failure', $context['message']);
        self::assertSame('bad', $context['plugin']);
        self::assertNotEmpty($context['token']);

        $configFile = $this->tmpDir . '/user/config/plugins/bad.yaml';
        self::assertFileExists($configFile);
        self::assertStringContainsString('enabled: false', file_get_contents($configFile));

        $quarantine = $this->tmpDir . '/user/data/upgrades/quarantine.json';
        self::assertFileExists($quarantine);
        $decoded = json_decode(file_get_contents($quarantine), true);
        self::assertArrayHasKey('bad', $decoded);
    }

    public function testHandleShutdownIgnoresNonFatalErrors(): void
    {
        $manager = new class($this->tmpDir) extends RecoveryManager {
            protected function resolveLastError(): ?array
            {
                return ['type' => E_USER_WARNING, 'message' => 'Notice'];
            }
        };

        $manager->handleShutdown();

        self::assertFileDoesNotExist($this->tmpDir . '/system/recovery.flag');
    }

    public function testClearRemovesFlag(): void
    {
        $flag = $this->tmpDir . '/system/recovery.flag';
        file_put_contents($flag, 'flag');

        $manager = new RecoveryManager($this->tmpDir);
        $manager->clear();

        self::assertFileDoesNotExist($flag);
    }

    public function testGenerateTokenFallbackOnRandomFailure(): void
    {
        $manager = new class($this->tmpDir) extends RecoveryManager {
            protected function randomBytes(int $length): string
            {
                throw new \RuntimeException('No randomness');
            }
        };

        $manager->activate([]);
        $context = $manager->getContext();

        self::assertNotEmpty($context['token']);
    }

    public function testGetContextWithoutFlag(): void
    {
        $manager = new RecoveryManager($this->tmpDir);
        self::assertNull($manager->getContext());
    }

    public function testDisablePluginRecordsQuarantineWithoutFlag(): void
    {
        $plugin = $this->tmpDir . '/user/plugins/problem';
        Folder::create($plugin);

        $manager = new RecoveryManager($this->tmpDir);
        $manager->disablePlugin('problem', ['message' => 'Manual disable']);

        $flag = $this->tmpDir . '/system/recovery.flag';
        self::assertFileDoesNotExist($flag);

        $configFile = $this->tmpDir . '/user/config/plugins/problem.yaml';
        self::assertFileExists($configFile);
        self::assertStringContainsString('enabled: false', file_get_contents($configFile));

        $quarantine = $this->tmpDir . '/user/data/upgrades/quarantine.json';
        self::assertFileExists($quarantine);
        $decoded = json_decode(file_get_contents($quarantine), true);
        self::assertSame('Manual disable', $decoded['problem']['message']);
    }
}
