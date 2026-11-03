<?php

use Grav\Common\Filesystem\Folder;

/**
 * The public webroot layout seen from the bootstrap: system/defines.php, the stream setup and
 * the GPM installer paths, with GRAV_ROOT private and only `public/` served.
 *
 * defines.php defines constants once per process, so every scenario runs in a child PHP
 * process against a throwaway layout (same approach as EnvBootstrapTest).
 */
class PublicWebrootBootstrapTest extends \PHPUnit\Framework\TestCase
{
    /** @var string */
    private $root;

    protected function setUp(): void
    {
        // Grav keeps paths with forward slashes on Windows too.
        $this->root = str_replace('\\', '/', realpath(sys_get_temp_dir()) ?: sys_get_temp_dir()) . '/grav-webroot-' . bin2hex(random_bytes(6));
        mkdir($this->root . '/system/config', 0777, true);
        mkdir($this->root . '/bin');
        mkdir($this->root . '/public/user', 0777, true);
        file_put_contents($this->root . '/system/config/system.yaml', '');
        file_put_contents($this->root . '/public/index.php', '<?php');
    }

    protected function tearDown(): void
    {
        Folder::delete($this->root);
    }

    public function testCliFindsThePublicFolderWithoutConfiguration(): void
    {
        // bin/grav runs from the private root, which has no index.php of its own.
        $result = $this->boot($this->root);

        self::assertSame($this->root, $result['GRAV_ROOT']);
        self::assertSame($this->root . '/public', $result['GRAV_WEBROOT']);
        self::assertSame($this->root . '/public/user/', $result['USER_DIR']);
        self::assertSame($this->root . '/cache/', $result['CACHE_DIR']);
    }

    public function testSingleFolderLayoutIsUnchanged(): void
    {
        file_put_contents($this->root . '/index.php', '<?php');
        mkdir($this->root . '/user');

        $result = $this->boot($this->root, ['setup' => true, 'installer' => true]);

        self::assertSame($this->root, $result['GRAV_ROOT']);
        self::assertSame($this->root, $result['GRAV_WEBROOT']);
        // Streams stay relative to the locator base, exactly as before.
        self::assertSame(['system'], $result['stream_system']);
        self::assertSame(['cache'], $result['stream_cache']);
        self::assertSame(['logs'], $result['stream_log']);
        self::assertSame(['tmp'], $result['stream_tmp']);
        self::assertSame(['backup'], $result['stream_backup']);
        self::assertSame(['user'], $result['stream_user']);
        self::assertSame($this->root . '/user/plugins/random', $result['install_root']);
        self::assertTrue($result['instance_root']);
    }

    public function testExplicitWebrootWinsOverDetection(): void
    {
        mkdir($this->root . '/web/user', 0777, true);
        file_put_contents($this->root . '/web/index.php', '<?php');

        $result = $this->boot($this->root, ['server' => ['GRAV_WEBROOT' => $this->root . '/web']]);

        self::assertSame($this->root . '/web', $result['GRAV_WEBROOT']);
    }

    public function testEnvFileIsReadFromAPredefinedRoot(): void
    {
        // index.php defines GRAV_ROOT for the public webroot layout; the web server's working
        // directory is then the webroot, but the .env lives in the private root.
        file_put_contents($this->root . '/.env', "GRAV_TMP_PATH=/srv/elsewhere/tmp\n");

        $result = $this->boot($this->root . '/public', [
            'define' => ['GRAV_ROOT' => $this->root, 'GRAV_WEBROOT' => $this->root . '/public'],
        ]);

        self::assertSame($this->root, $result['GRAV_ROOT']);
        self::assertSame($this->root . '/public', $result['GRAV_WEBROOT']);
        self::assertSame('/srv/elsewhere/tmp', $result['GRAV_TMP_PATH']);
    }

    public function testRootStreamsResolveAgainstTheRootInSplitLayout(): void
    {
        $result = $this->boot($this->root . '/public', [
            'define' => ['GRAV_ROOT' => $this->root, 'GRAV_WEBROOT' => $this->root . '/public'],
            'server' => ['GRAV_LOG_PATH' => '/srv/logs'],
            'setup' => true,
        ]);

        self::assertSame(['system', $this->root . '/system'], $result['stream_system']);
        self::assertSame([$this->root . '/cache'], $result['stream_cache']);
        self::assertSame([$this->root . '/tmp'], $result['stream_tmp']);
        self::assertSame([$this->root . '/backup'], $result['stream_backup']);
        // Absolute paths are kept as they are.
        self::assertSame(['/srv/logs'], $result['stream_log']);
        // The user folder stays relative to the webroot.
        self::assertSame(['user'], $result['stream_user']);
    }

    public function testInstallerSendsUserPathsToTheWebroot(): void
    {
        $result = $this->boot($this->root, ['installer' => true]);

        // Callers such as the API plugin pass GRAV_ROOT as the destination.
        self::assertSame($this->root . '/public/user/plugins/random', $result['install_root']);
        self::assertSame($this->root . '/public/user/plugins/random', $result['install_webroot']);
        self::assertSame($this->root . '/public/user/themes/quark', $result['install_theme']);
        // A core upgrade installs into GRAV_ROOT itself.
        self::assertSame($this->root, $result['install_core']);
        // Another folder is a Grav instance of its own and is left alone.
        self::assertSame('/srv/other/user/plugins/random', $result['install_other']);
        // The running site is a valid instance whichever of its two folders is named.
        self::assertTrue($result['instance_root']);
        self::assertTrue($result['instance_webroot']);
    }

    public function testInstallerRejectsAnIncompleteSplitInstance(): void
    {
        Folder::delete($this->root . '/bin');

        $result = $this->boot($this->root, ['installer' => true]);

        self::assertFalse($result['instance_root']);
    }

    /**
     * Load system/defines.php (through the Composer autoloader, as in production) in a child
     * process, then report the constants and, optionally, the Setup streams and installer paths.
     *
     * @param string $cwd  Working directory of the child process.
     * @param array  $opts 'define' => constants set before the autoloader, 'server' => $_SERVER
     *                     entries, 'setup' => report streams, 'installer' => report GPM paths.
     * @return array<string,mixed>
     */
    private function boot(string $cwd, array $opts = []): array
    {
        $repo = dirname(__DIR__, 5);
        $script = $this->root . '/child.php';
        file_put_contents($script, '<?php
            $opts = json_decode($argv[2], true);
            define("GRAV_CLI", true);
            foreach ($opts["define"] ?? [] as $k => $v) { define($k, $v); }
            foreach ($opts["server"] ?? [] as $k => $v) { $_SERVER[$k] = $v; }
            require $argv[1] . "/vendor/autoload.php";
            $out = [];
            foreach (["GRAV_ROOT", "GRAV_WEBROOT", "USER_DIR", "CACHE_DIR", "GRAV_TMP_PATH"] as $c) {
                $out[$c] = constant($c);
            }
            if (!empty($opts["setup"])) {
                $setup = new \Grav\Common\Config\Setup([]);
                foreach (["system", "cache", "log", "tmp", "backup", "user"] as $s) {
                    $out["stream_" . $s] = $setup->get("streams.schemes." . $s . ".prefixes")[""];
                }
            }
            if (!empty($opts["installer"])) {
                $i = \Grav\Common\GPM\Installer::class;
                $out["install_root"] = $i::resolveInstallPath(GRAV_ROOT, "user/plugins/random");
                $out["install_webroot"] = $i::resolveInstallPath(GRAV_WEBROOT, "user/plugins/random");
                $out["install_theme"] = $i::resolveInstallPath(GRAV_ROOT . "/", "/user/themes/quark");
                $out["install_core"] = $i::resolveInstallPath(GRAV_ROOT, "");
                $out["install_other"] = $i::resolveInstallPath("/srv/other", "user/plugins/random");
                $out["instance_root"] = $i::isGravInstance(GRAV_ROOT);
                $out["instance_webroot"] = $i::isGravInstance(GRAV_WEBROOT);
            }
            echo json_encode($out);
        ');

        $cmd = [PHP_BINARY, '-d', 'display_errors=stderr', $script, $repo, json_encode($opts)];
        $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $cwd);
        self::assertIsResource($proc);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        proc_close($proc);

        $decoded = json_decode($stdout, true);
        self::assertIsArray($decoded, "child failed:\n{$stdout}\n{$stderr}");

        return $decoded;
    }
}
