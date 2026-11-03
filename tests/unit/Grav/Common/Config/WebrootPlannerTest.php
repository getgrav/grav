<?php

use Grav\Common\Config\WebrootPlan;
use Grav\Common\Config\WebrootPlanner;
use Grav\Common\Config\WebrootPublisher;
use Grav\Common\Config\WebrootStatus;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Utils;

/**
 * Planning and carrying out webroot:split and webroot:join, symlink rewriting included.
 */
class WebrootPlannerTest extends \PHPUnit\Framework\TestCase
{
    /** @var string Outer temp folder, holding the site and a folder next to it. */
    private $base;
    /** @var string The site. */
    private $root;

    protected function setUp(): void
    {
        $base = sys_get_temp_dir() . '/grav-planner-' . bin2hex(random_bytes(6));
        mkdir($base . '/site', 0777, true);
        // sys_get_temp_dir() is behind a symlink on macOS; the planner works with real paths, with
        // forward slashes on Windows too.
        $this->base = str_replace('\\', '/', realpath($base));
        $this->root = $this->base . '/site';

        foreach (['index.php', '.htaccess', 'robots.txt', 'composer.json', '.env', 'favicon.ico', 'bin/grav', 'vendor/autoload.php', 'cache/x', 'assets/x.css', 'images/x.jpg', 'system/config/system.yaml', 'system/assets/jquery.js', 'user/config/system.yaml', 'user/pages/01.home/default.md'] as $file) {
            $this->write($file);
        }
        // Enough of the stock index.php for the status check to know it.
        $this->write('index.php', "<?php // GRAV_ROOT is found in dirname(__DIR__)");
        $this->write('../dev-plugins/foo/foo.php');
        mkdir($this->root . '/user/plugins');
        mkdir($this->root . '/dev-plugins/bar', 0777, true);
    }

    protected function tearDown(): void
    {
        Folder::delete($this->base);
    }

    public function testSplitPlanMovesTheWebrootEntriesAndListsUnknownOnes(): void
    {
        $plan = WebrootPlanner::split($this->root);

        self::assertTrue($plan->canApply(), implode("\n", $plan->errors));
        self::assertSame($this->root . '/public', $plan->webroot);
        $moved = array_map('basename', array_keys($plan->moves));
        self::assertSame(['.htaccess', 'assets', 'images', 'robots.txt', 'user', 'index.php'], $moved);
        // Grav's own private files are not listed; the rest is, so the user can decide.
        self::assertSame(['dev-plugins', 'favicon.ico'], $plan->unknown);

        $plan = WebrootPlanner::split($this->root, 'public', ['favicon.ico']);
        self::assertContains($this->root . '/favicon.ico', array_keys($plan->moves));
        self::assertSame(['dev-plugins'], $plan->unknown);
    }

    public function testSplitRefusals(): void
    {
        self::assertNotEmpty(WebrootPlanner::split($this->root, 'site-public')->errors, 'a name Grav does not look for');
        self::assertNotEmpty(WebrootPlanner::split($this->root, 'public', ['vendor'])->errors, 'a private entry');
        self::assertNotEmpty(WebrootPlanner::split($this->root, 'public', ['../x'])->errors, 'not a top-level name');
        self::assertNotEmpty(WebrootPlanner::split($this->root, 'public', ['missing'])->errors, 'missing entry');

        mkdir($this->root . '/public');
        self::assertTrue(WebrootPlanner::split($this->root)->canApply(), 'an empty folder is fine');
        $this->write('public/x');
        self::assertNotEmpty(WebrootPlanner::split($this->root)->errors, 'a folder with files in it');

        unlink($this->root . '/index.php');
        self::assertNotEmpty(WebrootPlanner::split($this->root, 'web')->errors, 'no index.php: already split');
    }

    public function testRelativeSymlinksAreRewrittenToTheSameTarget(): void
    {
        if (Utils::isWindows()) {
            $this->markTestSkipped('PHP on Windows always stores a symlink target as an absolute path, and only to a target that exists.');
        }

        // Outside user/ and outside the site, outside user/ but inside the site, inside user/,
        // into another folder that moves too, absolute, and already broken.
        symlink('../../../dev-plugins/foo', $this->root . '/user/plugins/foo');
        symlink('../../dev-plugins/bar', $this->root . '/user/plugins/bar');
        symlink('../config', $this->root . '/user/pages/config-link');
        symlink('../../assets/x.css', $this->root . '/user/config/x.css');
        symlink($this->base . '/dev-plugins/foo', $this->root . '/user/plugins/abs');
        symlink('../../../nowhere', $this->root . '/user/plugins/broken');

        $plan = WebrootPlanner::split($this->root);
        $webroot = $this->root . '/public';
        self::assertSame([
            $webroot . '/user/plugins/bar' => ['from' => '../../dev-plugins/bar', 'to' => '../../../dev-plugins/bar'],
            $webroot . '/user/plugins/broken' => ['from' => '../../../nowhere', 'to' => '../../../../nowhere'],
            $webroot . '/user/plugins/foo' => ['from' => '../../../dev-plugins/foo', 'to' => '../../../../dev-plugins/foo'],
        ], $plan->links);

        // Planning changes nothing.
        self::assertFileExists($this->root . '/index.php');
        self::assertDirectoryDoesNotExist($webroot);

        self::assertSame([], $plan->apply());

        self::assertFileExists($webroot . '/user/plugins/foo/foo.php');
        self::assertDirectoryExists($webroot . '/user/plugins/bar');
        self::assertDirectoryExists($webroot . '/user/pages/config-link');
        self::assertFileExists($webroot . '/user/config/x.css');
        self::assertFileExists($webroot . '/user/plugins/abs/foo.php');
        self::assertSame('../config', readlink($webroot . '/user/pages/config-link'));
        self::assertSame($this->base . '/dev-plugins/foo', readlink($webroot . '/user/plugins/abs'));

        // Join puts every link back the way it was.
        $join = WebrootPlanner::join($this->root, $webroot);
        self::assertTrue($join->canApply(), implode("\n", $join->errors));
        self::assertSame([], $join->apply());
        self::assertSame('../../../dev-plugins/foo', readlink($this->root . '/user/plugins/foo'));
        self::assertSame('../../dev-plugins/bar', readlink($this->root . '/user/plugins/bar'));
        self::assertSame('../../../nowhere', readlink($this->root . '/user/plugins/broken'));
        self::assertFileExists($this->root . '/user/plugins/foo/foo.php');
    }

    public function testSymlinkedTopLevelEntryIsRewritten(): void
    {
        if (Utils::isWindows()) {
            $this->markTestSkipped('PHP on Windows always stores a symlink target as an absolute path, and only to a target that exists.');
        }

        Folder::delete($this->root . '/user');
        mkdir($this->base . '/shared-user/config', 0777, true);
        symlink('../shared-user', $this->root . '/user');

        $plan = WebrootPlanner::split($this->root);
        self::assertSame([$this->root . '/public/user' => ['from' => '../shared-user', 'to' => '../../shared-user']], $plan->links);
        $plan->apply();
        self::assertDirectoryExists($this->root . '/public/user/config');
    }

    public function testSplitAndJoinRoundTrip(): void
    {
        $plan = WebrootPlanner::split($this->root);
        $plan->apply();
        $webroot = $this->root . '/public';

        foreach (['index.php', '.htaccess', 'robots.txt', 'assets/x.css', 'images/x.jpg', 'user/config/system.yaml', 'system/assets/jquery.js', 'system/' . WebrootPublisher::MANIFEST] as $file) {
            self::assertFileExists($webroot . '/' . $file);
        }
        foreach (['composer.json', '.env', 'favicon.ico', 'bin/grav', 'vendor/autoload.php', 'cache/x', 'system/config/system.yaml'] as $file) {
            self::assertFileExists($this->root . '/' . $file);
            self::assertFileDoesNotExist($webroot . '/' . $file);
        }
        self::assertFileDoesNotExist($webroot . '/system/config/system.yaml');
        self::assertFileDoesNotExist($this->root . '/.upgrading');
        self::assertFileDoesNotExist($webroot . '/.upgrading');

        $status = new WebrootStatus($this->root, $webroot);
        self::assertFalse(WebrootStatus::hasFailures($status->checks()), json_encode($status->checks()));

        $join = WebrootPlanner::join($this->root, $webroot);
        self::assertSame($join->publisher->target(), $webroot . '/system');
        self::assertNotContains($webroot . '/system', array_keys($join->moves));
        $join->apply();

        self::assertDirectoryDoesNotExist($webroot);
        foreach (['index.php', '.htaccess', 'robots.txt', 'assets/x.css', 'user/config/system.yaml', 'system/assets/jquery.js', 'system/config/system.yaml'] as $file) {
            self::assertFileExists($this->root . '/' . $file);
        }
    }

    public function testJoinRefusesConflictsAndUnpublishedSystemFiles(): void
    {
        WebrootPlanner::split($this->root)->apply();
        $webroot = $this->root . '/public';

        $this->write('robots.txt');
        $join = WebrootPlanner::join($this->root, $webroot);
        self::assertFalse($join->canApply());
        self::assertStringContainsString('robots.txt', implode("\n", $join->errors));
        unlink($this->root . '/robots.txt');

        $this->write('public/system/config/mine.yaml');
        $join = WebrootPlanner::join($this->root, $webroot);
        self::assertFalse($join->canApply());
        self::assertStringContainsString('config/mine.yaml', implode("\n", $join->errors));

        $this->expectException(RuntimeException::class);
        $join->apply();
    }

    public function testStatusFindsPrivateFilesAndStrayEntries(): void
    {
        if (Utils::isWindows()) {
            $this->markTestSkipped('PHP on Windows always stores a symlink target as an absolute path, and only to a target that exists.');
        }

        WebrootPlanner::split($this->root)->apply();
        $webroot = $this->root . '/public';
        $this->write('public/vendor/autoload.php');
        $this->write('public/system/config/system.yaml');
        $this->write('index.php');
        file_put_contents($webroot . '/system/assets/jquery.js', 'edited');
        symlink('../../../nowhere', $webroot . '/user/plugins/broken');
        file_put_contents($webroot . '/index.php', '<?php require __DIR__ . "/vendor/autoload.php";');

        $checks = [];
        foreach ((new WebrootStatus($this->root, $webroot))->checks() as $check) {
            $checks[$check['id']] = $check;
        }

        self::assertSame(WebrootStatus::FAIL, $checks['private-in-webroot']['status']);
        self::assertSame(['vendor', 'system/config/system.yaml'], $checks['private-in-webroot']['details']);
        self::assertSame(WebrootStatus::WARN, $checks['stray-in-root']['status']);
        self::assertSame(WebrootStatus::FAIL, $checks['published']['status']);
        self::assertSame(WebrootStatus::FAIL, $checks['symlinks']['status']);
        // An older index.php can't find the private folder.
        self::assertSame(WebrootStatus::FAIL, $checks['index-php']['status']);
    }

    public function testCopyFallbackKeepsSymlinksAndNeverFollowsThem(): void
    {
        if (Utils::isWindows()) {
            $this->markTestSkipped('PHP on Windows always stores a symlink target as an absolute path, and only to a target that exists.');
        }

        // The path move() takes when rename() fails (another filesystem).
        symlink('../../../dev-plugins/foo', $this->root . '/user/plugins/foo');
        $copy = new ReflectionMethod(WebrootPlan::class, 'copyTree');
        $delete = new ReflectionMethod(WebrootPlan::class, 'deleteTree');
        $copy->setAccessible(true);
        $delete->setAccessible(true);

        $copy->invoke(null, $this->root . '/user', $this->base . '/user-copy');
        self::assertSame('../../../dev-plugins/foo', readlink($this->base . '/user-copy/plugins/foo'));
        self::assertFileExists($this->base . '/user-copy/config/system.yaml');

        $delete->invoke(null, $this->root . '/user');
        self::assertDirectoryDoesNotExist($this->root . '/user');
        // The folder the link pointed at is still there.
        self::assertFileExists($this->base . '/dev-plugins/foo/foo.php');
    }

    public function testRelativeAndNormalize(): void
    {
        self::assertSame('../../../a/b', WebrootPlanner::relative('/x/y/z', '/a/b'));
        self::assertSame('c', WebrootPlanner::relative('/a/b', '/a/b/c'));
        self::assertSame('.', WebrootPlanner::relative('/a/b', '/a/b'));
        self::assertSame('/a/c', WebrootPlanner::normalize('/a/b/../c/./'));
        self::assertSame('/', WebrootPlanner::normalize('/a/../..'));
        self::assertSame('C:/a', WebrootPlanner::normalize('C:\\a\\b\\..'));
    }

    private function write(string $relative, ?string $content = null): void
    {
        $file = $this->root . '/' . $relative;
        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0777, true);
        }
        file_put_contents($file, $content ?? $relative);
    }
}
