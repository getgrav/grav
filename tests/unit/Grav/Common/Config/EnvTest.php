<?php

use Grav\Common\Config\Env;

/**
 * Tests for native .env support (Grav\Common\Config\Env).
 */
class EnvTest extends \PHPUnit\Framework\TestCase
{
    /** @var string */
    private $dir;

    /** @var string|false */
    private $savedEnvironment;

    /** @var string[] Variable names touched by a test, cleared on tearDown. */
    private $touched = [];

    protected function setUp(): void
    {
        // Isolate from any GRAV_ENVIRONMENT / dotenv state the suite may carry.
        $this->savedEnvironment = getenv('GRAV_ENVIRONMENT');
        $this->clearVar('GRAV_ENVIRONMENT');
        $this->clearVar('SYMFONY_DOTENV_VARS');

        $this->dir = sys_get_temp_dir() . '/grav-env-' . uniqid('', true);
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/.env*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->dir);

        foreach ($this->touched as $name) {
            $this->clearVar($name);
        }
        $this->clearVar('SYMFONY_DOTENV_VARS');
        $this->clearVar('GRAV_ENVIRONMENT');

        if ($this->savedEnvironment !== false) {
            putenv('GRAV_ENVIRONMENT=' . $this->savedEnvironment);
            $_ENV['GRAV_ENVIRONMENT'] = $_SERVER['GRAV_ENVIRONMENT'] = $this->savedEnvironment;
        }
    }

    public function testLoadsBaseEnvIntoGetenvAndSuperglobals(): void
    {
        $this->touched[] = 'DOTENV_TEST_FOO';
        $this->writeEnv('.env', "DOTENV_TEST_FOO=bar\n");

        Env::load($this->dir);

        self::assertSame('bar', getenv('DOTENV_TEST_FOO'));
        self::assertSame('bar', $_ENV['DOTENV_TEST_FOO']);
        self::assertSame('bar', $_SERVER['DOTENV_TEST_FOO']);
    }

    public function testLocalLayerOverridesBase(): void
    {
        $this->touched[] = 'DOTENV_TEST_LAYER';
        $this->writeEnv('.env', "DOTENV_TEST_LAYER=base\n");
        $this->writeEnv('.env.local', "DOTENV_TEST_LAYER=local\n");

        Env::load($this->dir);

        self::assertSame('local', getenv('DOTENV_TEST_LAYER'));
    }

    public function testRealEnvironmentVariableWins(): void
    {
        $this->touched[] = 'DOTENV_TEST_REAL';
        putenv('DOTENV_TEST_REAL=fromserver');
        $_ENV['DOTENV_TEST_REAL'] = $_SERVER['DOTENV_TEST_REAL'] = 'fromserver';

        $this->writeEnv('.env', "DOTENV_TEST_REAL=fromfile\n");

        Env::load($this->dir);

        self::assertSame('fromserver', getenv('DOTENV_TEST_REAL'));
    }

    public function testPerEnvironmentLayerLoadedWhenEnvironmentSet(): void
    {
        $this->touched[] = 'DOTENV_TEST_PERENV';
        $this->touched[] = 'GRAV_ENVIRONMENT';
        $this->writeEnv('.env', "GRAV_ENVIRONMENT=staging\n");
        $this->writeEnv('.env.staging', "DOTENV_TEST_PERENV=staged\n");

        Env::load($this->dir);

        self::assertSame('staging', getenv('GRAV_ENVIRONMENT'));
        self::assertSame('staged', getenv('DOTENV_TEST_PERENV'));
    }

    public function testLocalLayerSkippedWhenEnvironmentIsTest(): void
    {
        $this->touched[] = 'DOTENV_TEST_TESTSKIP';
        $this->touched[] = 'GRAV_ENVIRONMENT';
        $this->writeEnv('.env', "GRAV_ENVIRONMENT=test\nDOTENV_TEST_TESTSKIP=base\n");
        $this->writeEnv('.env.local', "DOTENV_TEST_TESTSKIP=local\n");

        Env::load($this->dir);

        // .env.local must not be loaded under the test environment.
        self::assertSame('base', getenv('DOTENV_TEST_TESTSKIP'));
    }

    public function testDoesNotForceDefaultEnvironment(): void
    {
        $this->touched[] = 'DOTENV_TEST_NODEFAULT';
        $this->writeEnv('.env', "DOTENV_TEST_NODEFAULT=value\n");

        Env::load($this->dir);

        // Unlike Symfony's loadEnv(), no default environment is invented, so
        // Grav's hostname-based environment detection stays intact.
        self::assertFalse(getenv('GRAV_ENVIRONMENT'));
    }

    public function testNoEnvFilesIsNoop(): void
    {
        // Empty directory: must not error and must not set anything.
        Env::load($this->dir);

        self::assertFalse(getenv('GRAV_ENVIRONMENT'));
    }

    /**
     * @param string $name
     * @param string $contents
     */
    private function writeEnv(string $name, string $contents): void
    {
        file_put_contents($this->dir . '/' . $name, $contents);
    }

    /**
     * @param string $name
     */
    private function clearVar(string $name): void
    {
        putenv($name);
        unset($_ENV[$name], $_SERVER[$name]);
    }
}
