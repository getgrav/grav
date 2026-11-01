<?php

use Codeception\Util\Fixtures;
use Grav\Common\Filesystem\Folder;
use Grav\Common\GPM\Installer;
use Grav\Common\Grav;
use Grav\Common\Plugins;

/**
 * Replacing an installed plugin must never let a request load plugins while its folder is
 * missing or half copied. Requests hold the shared plugin folder lock while they load
 * plugins (InitializeProcessor), so the observers here take that same lock the same way,
 * and turn away on the maintenance flag the way index.php does.
 */
class InstallerMoveInstallTest extends \PHPUnit\Framework\TestCase
{
    private string $root;
    private string $plugins;
    private string $plugin;
    private string $lockFile;

    protected function setUp(): void
    {
        $grav = Fixtures::get('grav');
        $grav();

        $this->root = sys_get_temp_dir() . '/grav-move-install-' . bin2hex(random_bytes(6));
        $this->plugins = $this->root . '/user/plugins';
        $this->plugin = $this->plugins . '/login';
        $this->makePlugin($this->plugin, 'old');
        file_put_contents($this->plugin . '/dropped.php', 'old');

        $this->lockFile = Grav::instance()['locator']->findResource('tmp://', true, true) . '/plugins.lock';
        @unlink(GRAV_ROOT . '/.upgrading');
    }

    protected function tearDown(): void
    {
        Folder::delete($this->root);
        @unlink(GRAV_ROOT . '/.upgrading');
    }

    public function testReplacesTheInstalledPluginAndCleansUp(): void
    {
        self::assertTrue(Installer::moveInstall($this->makePlugin($this->root . '/package', 'new'), $this->plugin));

        self::assertSame('new', file_get_contents($this->plugin . '/login.php'));
        self::assertFileDoesNotExist($this->plugin . '/dropped.php');
        self::assertFileDoesNotExist($this->root . '/package');
        self::assertSame(['login'], $this->entries($this->plugins), 'no staging folder is left behind');
        self::assertFileDoesNotExist(GRAV_ROOT . '/.upgrading');
    }

    public function testInstallsAPluginTheSiteDoesNotHaveYet(): void
    {
        self::assertTrue(Installer::moveInstall($this->makePlugin($this->root . '/package', 'new'), $this->plugins . '/other'));

        self::assertSame('new', file_get_contents($this->plugins . '/other/login.php'));
        self::assertSame(['login', 'other'], $this->entries($this->plugins));
        self::assertFileDoesNotExist(GRAV_ROOT . '/.upgrading');
    }

    public function testAFailedMoveKeepsTheInstalledCopy(): void
    {
        try {
            Installer::moveInstall($this->root . '/missing', $this->plugin);
            self::fail('Moving a missing package must fail');
        } catch (\RuntimeException) {
        }

        self::assertSame('old', file_get_contents($this->plugin . '/login.php'));
        self::assertFileExists($this->plugin . '/dropped.php');
        self::assertSame(['login'], $this->entries($this->plugins));
        self::assertFileDoesNotExist(GRAV_ROOT . '/.upgrading');
    }

    public function testReplacesASymlinkedPluginWithoutTouchingItsTarget(): void
    {
        $repo = $this->makePlugin($this->root . '/repo/login', 'repo');
        Folder::delete($this->plugin);
        symlink($repo, $this->plugin);

        self::assertTrue(Installer::moveInstall($this->makePlugin($this->root . '/package', 'new'), $this->plugin));

        self::assertFalse(is_link($this->plugin));
        self::assertSame('new', file_get_contents($this->plugin . '/login.php'));
        self::assertSame('repo', file_get_contents($repo . '/login.php'));
        self::assertSame('repo', file_get_contents($repo . '/vendor/autoload.php'));
        self::assertSame(['login'], $this->entries($this->plugins));
    }

    public function testARequestLoadingPluginsFinishesBeforeTheFolderIsSwapped(): void
    {
        // A request that is loading plugins when the update starts holds the shared lock for 0.6 s.
        $observer = $this->observe('hold', 0.6);

        $start = microtime(true);
        Installer::moveInstall($this->makePlugin($this->root . '/package', 'new'), $this->plugin);
        $elapsed = microtime(true) - $start;

        $report = $this->report($observer);
        self::assertGreaterThan(0, $report['checks']);
        self::assertSame(0, $report['missing'], 'the request saw the plugin missing or half copied');
        self::assertSame(0, $report['new'], 'the request saw the new copy before it finished loading');
        self::assertTrue($report['flag'], 'new requests are turned away while the update waits');
        self::assertGreaterThan(0.3, $elapsed, 'the update waited for the request');
        self::assertSame('new', file_get_contents($this->plugin . '/login.php'));
    }

    public function testRequestsDuringAnUpdateNeverSeeThePluginMissing(): void
    {
        // Requests keep arriving while the plugin is replaced ten times.
        $observer = $this->observe('poll', 30);

        for ($i = 0; $i < 10; $i++) {
            Installer::moveInstall($this->makePlugin($this->root . '/package', "new{$i}"), $this->plugin);
            usleep(20000);
        }
        touch($this->root . '/stop');

        $report = $this->report($observer);
        self::assertGreaterThan(0, $report['checks']);
        self::assertSame(0, $report['missing'], 'a request loaded plugins while the folder was missing or half copied');
        self::assertSame('new9', file_get_contents($this->plugin . '/login.php'));
    }

    public function testPluginsSkipsHiddenFolders(): void
    {
        $locator = Grav::instance()['locator'];
        $base = $locator->findResource('plugins://', true, true);
        Folder::create($base . '/.gpm-test/new');
        Folder::create($base . '/gpm-test-visible');

        try {
            $plugins = new Plugins();
            spl_autoload_unregister([$plugins, 'autoloadUnloadedPlugin']);
            $names = (new \ReflectionProperty(Plugins::class, 'pending'))->getValue($plugins);
        } finally {
            Folder::delete($base . '/.gpm-test');
            Folder::delete($base . '/gpm-test-visible');
        }

        self::assertContains('gpm-test-visible', $names);
        self::assertNotContains('.gpm-test', $names);
    }

    private function makePlugin(string $path, string $content): string
    {
        // Enough files that deleting the folder in place takes a measurable time.
        foreach (['classes', 'vendor/composer', 'vendor/lib/src', 'templates/partials'] as $dir) {
            mkdir($path . '/' . $dir, 0700, true);
        }
        for ($i = 0; $i < 60; $i++) {
            file_put_contents("{$path}/vendor/lib/src/File{$i}.php", $content);
            file_put_contents("{$path}/classes/Class{$i}.php", $content);
            file_put_contents("{$path}/templates/partials/t{$i}.html.twig", $content);
        }
        file_put_contents($path . '/login.php', $content);
        file_put_contents($path . '/vendor/autoload.php', $content);

        return $path;
    }

    /**
     * @return string[]
     */
    private function entries(string $dir): array
    {
        $entries = array_values(array_diff(scandir($dir), ['.', '..']));
        sort($entries);

        return $entries;
    }

    /**
     * Start a separate process that loads "plugins" the way a request does.
     *
     * hold: take the shared lock once and keep checking the plugin for $seconds.
     * poll: until the stop file appears, skip while the flag is up (index.php answers 503),
     *       otherwise take the shared lock, check the plugin and let go.
     *
     * @return array{0: resource, 1: array}
     */
    private function observe(string $mode, float $seconds): array
    {
        $script = $this->root . '/observer.php';
        file_put_contents($script, <<<'PHP'
<?php
[, $mode, $lockFile, $plugin, $flag, $seconds, $ready, $stop] = $argv;
$report = ['checks' => 0, 'missing' => 0, 'new' => 0, 'flag' => false];
$check = static function () use ($plugin, &$report) {
    $report['checks']++;
    $login = @file_get_contents($plugin . '/login.php');
    $autoload = @file_get_contents($plugin . '/vendor/autoload.php');
    $last = @file_get_contents($plugin . '/vendor/lib/src/File59.php');
    if ($login === false || $autoload === false || $last === false) {
        $report['missing']++;
    } elseif ($login !== 'old') {
        $report['new']++;
    }
};
$handle = fopen($lockFile, 'c');
$until = microtime(true) + (float)$seconds;
if ($mode === 'hold') {
    flock($handle, LOCK_SH);
    touch($ready);
    while (microtime(true) < $until) {
        $check();
        $report['flag'] = $report['flag'] || file_exists($flag);
        clearstatcache();
        usleep(1000);
    }
    flock($handle, LOCK_UN);
} else {
    touch($ready);
    while (!file_exists($stop) && microtime(true) < $until) {
        clearstatcache();
        if (!file_exists($flag)) {
            flock($handle, LOCK_SH);
            $check();
            flock($handle, LOCK_UN);
        }
        usleep(200);
    }
}
echo json_encode($report);
PHP);
        $ready = $this->root . '/ready';
        $args = [PHP_BINARY, $script, $mode, $this->lockFile, $this->plugin, GRAV_ROOT . '/.upgrading', (string)$seconds, $ready, $this->root . '/stop'];
        $process = proc_open($args, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);

        $until = microtime(true) + 10;
        while (!file_exists($ready) && microtime(true) < $until) {
            usleep(5000);
            clearstatcache();
        }
        self::assertFileExists($ready, 'observer did not start');

        return [$process, $pipes];
    }

    private function report(array $observer): array
    {
        [$process, $pipes] = $observer;
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        proc_close($process);

        $report = json_decode((string)$output, true);
        self::assertIsArray($report, "observer failed: {$output} {$errors}");

        return $report;
    }
}
