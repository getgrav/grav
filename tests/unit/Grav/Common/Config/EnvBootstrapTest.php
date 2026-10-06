<?php

use Grav\Common\Config\Env;

/**
 * Tests for how a .env reaches the bootstrap path constants (system/defines.php)
 * on hosts that disable putenv(), and for the shared Env::get() lookup. (#4344)
 *
 * defines.php defines constants, so each scenario runs in a child PHP process
 * started in a throwaway Grav root that holds the .env.
 */
class EnvBootstrapTest extends \PHPUnit\Framework\TestCase
{
    /** @var string */
    private $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/grav-env-boot-' . uniqid('', true);
        mkdir($this->root);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->root . '/{,.}*', GLOB_BRACE) ?: [] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        @rmdir($this->root);
    }

    public function testEnvPathsReachConstantsWhenPutenvIsDisabled(): void
    {
        file_put_contents(
            $this->root . '/.env',
            "GRAV_BACKUP_PATH=/srv/outside/backup/\nGRAV_TMP_PATH=/srv/outside/tmp\nGRAV_CACHE_PATH=/srv/outside/cache\n"
        );

        $result = $this->bootstrap(true);

        self::assertSame('', $result['log'], 'a disabled putenv() must not be logged on every request');
        self::assertSame('/srv/outside/backup', $result['GRAV_BACKUP_PATH']);
        self::assertSame('/srv/outside/tmp', $result['GRAV_TMP_PATH']);
        self::assertSame('/srv/outside/cache', $result['GRAV_CACHE_PATH']);
        // Untouched constants keep their defaults.
        self::assertSame('logs', $result['GRAV_LOG_PATH']);
    }

    public function testLaterLayersStillLoadWhenPutenvIsDisabled(): void
    {
        // The old code threw while flushing the first file, so .env.local never loaded.
        file_put_contents($this->root . '/.env', "GRAV_BACKUP_PATH=/srv/base/backup\n");
        file_put_contents($this->root . '/.env.local', "GRAV_BACKUP_PATH=/srv/local/backup\n");

        $result = $this->bootstrap(true);

        self::assertSame('/srv/local/backup', $result['GRAV_BACKUP_PATH']);
    }

    public function testEnvPathsReachConstantsWhenPutenvIsEnabled(): void
    {
        file_put_contents($this->root . '/.env', "GRAV_TMP_PATH=/srv/outside/tmp\n");

        $result = $this->bootstrap(false);

        self::assertSame('', $result['log']);
        self::assertSame('/srv/outside/tmp', $result['GRAV_TMP_PATH']);
    }

    public function testServerVariableDrivesConstantWithoutAnyEnvFile(): void
    {
        // Apache SetEnv / nginx fastcgi_param land in $_SERVER only.
        $result = $this->bootstrap(true, ['server' => ['GRAV_BACKUP_PATH' => '/srv/fcgi/backup']]);

        self::assertSame('/srv/fcgi/backup', $result['GRAV_BACKUP_PATH']);
    }

    public function testEmptyServerValueFallsThroughToGetenv(): void
    {
        // A present-but-empty $_SERVER entry must not shadow a working getenv(),
        // exactly as Setup::envVar() does it.
        $result = $this->bootstrap(false, [
            'server' => ['GRAV_TMP_PATH' => ''],
            'putenv' => ['GRAV_TMP_PATH' => '/srv/real/tmp'],
        ]);

        self::assertSame('/srv/real/tmp', $result['GRAV_TMP_PATH']);
    }

    public function testEmptyEverywhereFallsBackToDefault(): void
    {
        $result = $this->bootstrap(false, ['server' => ['GRAV_TMP_PATH' => ''], 'putenv' => ['GRAV_TMP_PATH' => '']]);

        self::assertSame('tmp', $result['GRAV_TMP_PATH']);
    }

    public function testGetPrefersServerThenEnvThenGetenv(): void
    {
        $name = 'DOTENV_TEST_GET';
        try {
            putenv($name . '=fromgetenv');
            self::assertSame('fromgetenv', Env::get($name));

            $_ENV[$name] = 'fromenv';
            self::assertSame('fromenv', Env::get($name));

            $_SERVER[$name] = 'fromserver';
            self::assertSame('fromserver', Env::get($name));

            // Empty entries are skipped, not returned.
            $_SERVER[$name] = '';
            self::assertSame('fromenv', Env::get($name));
            $_ENV[$name] = '';
            self::assertSame('fromgetenv', Env::get($name));
            putenv($name . '=');
            self::assertNull(Env::get($name));
        } finally {
            putenv($name);
            unset($_ENV[$name], $_SERVER[$name]);
        }
    }

    /**
     * Run system/defines.php (through the Composer autoloader, as in production)
     * in a child process whose working directory is the throwaway root.
     *
     * @param bool  $disablePutenv Start the child with disable_functions=putenv.
     * @param array $opts          'server' => [name => value] set in $_SERVER and
     *                             'putenv' => [name => value] set via putenv(),
     *                             both before the autoloader runs.
     * @return array<string,string>
     */
    private function bootstrap(bool $disablePutenv, array $opts = []): array
    {
        $repo = dirname(__DIR__, 5);
        $errorLog = $this->root . '/error.log';
        file_put_contents($errorLog, '');

        $script = $this->root . '/child.php';
        file_put_contents($script, '<?php
            $opts = json_decode($argv[2], true);
            foreach ($opts["server"] ?? [] as $k => $v) { $_SERVER[$k] = $v; }
            foreach ($opts["putenv"] ?? [] as $k => $v) { putenv("$k=$v"); }
            require $argv[1] . "/vendor/autoload.php";
            $out = [];
            foreach (["GRAV_BACKUP_PATH", "GRAV_TMP_PATH", "GRAV_CACHE_PATH", "GRAV_LOG_PATH"] as $c) {
                $out[$c] = constant($c);
            }
            echo json_encode($out);
        ');

        $cmd = [PHP_BINARY, '-n', '-d', 'display_errors=0', '-d', 'log_errors=1', '-d', 'error_log=' . $errorLog];
        if ($disablePutenv) {
            $cmd[] = '-d';
            $cmd[] = 'disable_functions=putenv';
        }
        array_push($cmd, $script, $repo, json_encode($opts));

        $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $this->root);
        self::assertIsResource($proc);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        proc_close($proc);

        $decoded = json_decode($stdout, true);
        self::assertIsArray($decoded, "child failed:\n{$stdout}\n{$stderr}");
        $decoded['log'] = trim((string)file_get_contents($errorLog));

        return $decoded;
    }
}
