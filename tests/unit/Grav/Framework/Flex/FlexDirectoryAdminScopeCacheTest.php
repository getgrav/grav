<?php

use Codeception\Util\Fixtures;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use Grav\Common\Page\Pages;
use Grav\Framework\Flex\FlexDirectory;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

/**
 * Admin and site share the Flex cache: what is saved in admin scope has to clear
 * the entries the site reads. The classic admin plugin appends '$' to its cache
 * key, the API plugin (Admin2) registers $grav['admin'] and leaves the key alone.
 */
class FlexDirectoryAdminScopeCacheTest extends \PHPUnit\Framework\TestCase
{
    /** @var Grav */
    protected $grav;

    /** @var string Folder of the test inside GRAV_ROOT. */
    protected $root;

    protected function setUp(): void
    {
        parent::setUp();

        $grav = Fixtures::get('grav');
        $this->grav = $grav();

        $this->root = 'tests/_output/flex-admin-scope-' . bin2hex(random_bytes(4));
        mkdir(GRAV_ROOT . "/{$this->root}/cache", 0777, true);
        mkdir(GRAV_ROOT . "/{$this->root}/pages/01.home", 0777, true);
        file_put_contents(GRAV_ROOT . "/{$this->root}/pages/01.home/default.md", "---\ntitle: Home\n---\nHome");

        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];
        $locator->addPath('cache', '', GRAV_ROOT . "/{$this->root}/cache", false);
        $locator->addPath('page', '', GRAV_ROOT . "/{$this->root}/pages", false);

        // A cache which outlives the directory object, as between two requests.
        $this->grav['config']->set('system.cache.driver', 'file');
        $this->grav['cache']->init($this->grav);
    }

    protected function tearDown(): void
    {
        unset($this->grav['admin']);
        Folder::delete(GRAV_ROOT . '/' . $this->root);

        parent::tearDown();
    }

    public function testAdminScopeUsesTheCacheOfTheSite(): void
    {
        $this->directory()->getCache('index')->set('built', 'site');

        // Only $grav['admin'] is registered: the cache key is the one of the site.
        $this->grav['admin'] = new stdClass();

        self::assertSame('site', $this->directory()->getCache('index')->get('built'));
    }

    public function testClassicAdminCacheKeyUsesTheCacheOfTheSite(): void
    {
        $this->directory()->getCache('index')->set('built', 'site');

        $this->grav['admin'] = new stdClass();
        $this->grav['cache']->setKey($this->grav['cache']->getKey() . '$');

        self::assertSame('site', $this->directory()->getCache('index')->get('built'));
    }

    public function testClearInAdminScopeClearsTheCacheOfTheSite(): void
    {
        foreach (['index', 'object', 'render'] as $namespace) {
            $this->directory()->getCache($namespace)->set('built', 'site');
        }

        $this->grav['admin'] = new stdClass();
        $this->directory()->clearCache();
        unset($this->grav['admin']);

        foreach (['index', 'object', 'render'] as $namespace) {
            self::assertNull($this->directory()->getCache($namespace)->get('built'), $namespace);
        }
    }

    public function testPagesBuiltInAdminScopeAreNotLoadedByTheSite(): void
    {
        $this->grav['admin'] = new stdClass();
        $admin = $this->pages();
        unset($this->grav['admin']);

        // The index cache is shared, the pages built from it are not.
        $site = $this->pages();
        self::assertNotSame($admin->getPagesCacheId(), $site->getPagesCacheId());
        self::assertSame(1, $site->builds);
        self::assertNotNull($site->find('/home'));

        // The site loads the pages it built itself.
        $site = $this->pages();
        self::assertSame(0, $site->builds);
        self::assertNotNull($site->find('/home'));
    }

    /**
     * The pages directory of a new request.
     */
    private function directory(): FlexDirectory
    {
        $enabled = ['enabled' => true, 'lifetime' => 60];

        return new FlexDirectory('pages', 'blueprints://flex/pages.yaml', [
            'enabled' => true,
            'data' => ['storage' => ['options' => ['folder' => $this->root . '/pages']]],
            'object' => ['cache' => ['index' => $enabled, 'object' => $enabled, 'render' => $enabled]],
        ]);
    }

    /**
     * The pages service of a new request on a site with Flex pages.
     */
    private function pages(): Pages
    {
        $directory = $this->directory();
        $this->grav['flex']->addDirectory($directory);

        $pages = new class($this->grav) extends Pages {
            /** @var int */
            public $builds = 0;

            protected function buildRoutes(): void
            {
                $this->builds++;
                parent::buildRoutes();
            }
        };
        (new ReflectionProperty(Pages::class, 'directory'))->setValue($pages, $directory);
        unset($this->grav['pages']);
        $this->grav['pages'] = $pages;
        $pages->init();

        return $pages;
    }
}
