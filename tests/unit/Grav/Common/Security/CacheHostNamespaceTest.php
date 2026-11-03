<?php

use Codeception\Util\Fixtures;
use Grav\Common\Cache;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use Grav\Common\Page\Pages;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

class CacheHostNamespaceTest extends \PHPUnit\Framework\TestCase
{
    /** @var Grav */
    protected $grav;

    /** @var string */
    protected $root;

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $this->grav = $grav();

        $config = $this->grav['config'];
        $config->set('system.cache.enabled', true);
        $config->set('system.cache.driver', 'file');
        $config->set('system.cache.check.interval', 0);
        $config->set('system.languages.supported', []);
        $this->grav['language']->setLanguages([]);
        $this->grav['language']->init();

        $this->root = GRAV_ROOT . '/tests/_output/grav-cache-host-' . bin2hex(random_bytes(4));
        mkdir($this->root . '/cache', 0777, true);
        mkdir($this->root . '/pages/01.home', 0777, true);
        file_put_contents($this->root . '/pages/01.home/default.md', "---\ntitle: Home\n---\nHome");

        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];
        $locator->addPath('cache', '', $this->root . '/cache', false);
        $locator->addPath('page', '', $this->root . '/pages', false);
    }

    protected function tearDown(): void
    {
        // Close the lazy page index the test's Pages holds open: Windows won't delete an open file.
        unset($this->grav['pages']);
        gc_collect_cycles();
        try {
            Folder::delete($this->root);
        } catch (\RuntimeException $e) {
            // On Windows a Pages object this process still references keeps the index file open;
            // the leftover folder is in tests/_output, which git ignores.
            if (DIRECTORY_SEPARATOR !== '\\') {
                throw $e;
            }
        }
        parent::tearDown();
    }

    public function testRegularEntriesAreKeptApartByHost(): void
    {
        $one = $this->cache('one.example');
        $two = $this->cache('two.example');

        self::assertNotSame($one->getKey(), $two->getKey());

        $one->save('entry', 'http://one.example/');

        self::assertSame('http://one.example/', $this->cache('one.example')->fetch('entry'));
        self::assertFalse($two->fetch('entry'));
    }

    public function testSharedEntriesAreTheSameForEveryHost(): void
    {
        $one = $this->cache('one.example');

        // Each host name is an environment of its own, whose configuration is compiled
        // in a later second: Config::key() changes with it, the checksum does not.
        $config = $this->grav['config'];
        $config->timestamp($config->timestamp() + 1);
        (new ReflectionProperty($config, 'key'))->setValue($config, null);

        $two = $this->cache('two.example');

        self::assertNotSame($one->getKey(), $two->getKey());
        self::assertSame($one->getSharedKey(), $two->getSharedKey());

        $one->saveShared('entry', 'data');

        self::assertSame('data', $two->fetchShared('entry'));
        self::assertFalse($two->fetch('entry'), 'Shared entries are not regular entries');

        $two->deleteAll();

        self::assertFalse($this->cache('one.example')->fetchShared('entry'));
    }

    public function testDisabledCacheHasNoSharedEntries(): void
    {
        $cache = $this->cache('one.example');
        $cache->saveShared('entry', 'data');
        $cache->setEnabled(false);

        self::assertFalse($cache->fetchShared('entry'));
    }

    public function testNewHostReusesThePagesIndex(): void
    {
        $id = $this->pages('one.example')->getPagesCacheId();
        $size = $this->bytes();

        $pages = $this->pages('two.example');

        self::assertSame($id, $pages->getPagesCacheId());
        self::assertNotNull($pages->find('/home'));
        self::assertSame($size, $this->bytes(), 'The second host stores nothing');
    }

    public function testPagesIndexIsKeptApartByHostWithTwigInFrontmatter(): void
    {
        $this->grav['config']->set('system.pages.frontmatter.process_twig', true);

        $id = $this->pages('one.example')->getPagesCacheId();

        self::assertFalse($this->grav['cache']->fetchShared($id));
        self::assertIsArray($this->grav['cache']->fetch($id));
        self::assertFalse($this->cache('two.example')->fetch($id));
    }

    public function testUuidIndexesAreReusedForBothIndexModes(): void
    {
        $this->grav['config']->set('system.pages.uuid', true);
        $uuid = \Grav\Common\Page\PageIdentity::assign($this->root . '/pages/01.home');
        foreach ([false, true] as $lazy) {
            $this->cache('one.example')->deleteAll();
            $this->grav['config']->set('system.pages.lazy_index', $lazy);
            $this->pages('one.example');
            $pages = $this->pages('two.example');
            self::assertSame(0, $pages->rebuilds);
            self::assertSame($uuid, $pages->findByUuid($uuid)->uuid());
            $cached = $this->grav['cache']->fetchShared($pages->getPagesCacheId());
            self::assertSame($lazy, $cached[6]);
            self::assertIsArray($cached[7]);
        }
    }

    public function testDifferentPageRootsHaveDifferentIndexes(): void
    {
        $first = $this->pages('one.example')->getPagesCacheId();
        mkdir($this->root . '/other/01.home', 0777, true);
        file_put_contents($this->root . '/other/01.home/default.md', "---\ntitle: Other\n---\nOther");
        $this->grav['locator']->addPath('page', '', $this->root . '/other', false);
        $pages = $this->pages('two.example');
        self::assertNotSame($first, $pages->getPagesCacheId());
        self::assertSame('Other', $pages->find('/home')->title());
    }

    /**
     * The cache service of a request for the given host.
     */
    private function cache(string $host): Cache
    {
        $this->grav['uri']->initializeWithUrl("http://{$host}/")->init();

        return new Cache($this->grav);
    }

    /**
     * The pages service of a request for the given host.
     */
    private function pages(string $host): Pages
    {
        unset($this->grav['cache']);
        $this->grav['cache'] = $this->cache($host);

        $pages = new class($this->grav) extends Pages {
            public $rebuilds = 0;

            public function resetPages(array $pages_dirs): void
            {
                $this->rebuilds++;
                parent::resetPages($pages_dirs);
            }
        };
        unset($this->grav['pages']);
        $this->grav['pages'] = $pages;
        $pages->init();

        return $pages;
    }

    private function bytes(): int
    {
        $size = 0;
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->root . '/cache/grav', FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            $size += $file->getSize();
        }

        return $size;
    }
}
