<?php

use Codeception\Util\Fixtures;
use Grav\Common\Cache;
use Grav\Common\File\CompiledYamlFile;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use Grav\Common\Page\Pages;
use Grav\Framework\Flex\FlexDirectory;
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
        Folder::delete($this->root);
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

    public function testCacheCreatedBeforeUriInitIsKeptApartByPort(): void
    {
        // A restored session loads the user's blueprint, and with it the cache, before
        // Uri::init(). The content saved later is rendered with the port of the request.
        $uri = $this->grav['uri'];
        $uri->initializeWithUrl('http://one.example:8443/');
        $early = new Cache($this->grav);
        $uri->init();
        $late = new Cache($this->grav);

        self::assertSame($late->getKey(), $early->getKey());
        self::assertNotSame($this->cache('one.example')->getKey(), $early->getKey());

        $early->save('entry', 'http://one.example:8443/');

        self::assertFalse($this->cache('one.example')->fetch('entry'));
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

    public function testFlexSaveReachesEveryHost(): void
    {
        $file = $this->root . '/accounts/editor.yaml';
        mkdir(dirname($file), 0777, true);
        file_put_contents($file, "state: enabled\n");
        touch($file, time() - 10);

        // The index and the account are cached by a request for the first host.
        self::assertSame('enabled', $this->directory('one.example')->getObject('editor')->get('state'));
        self::assertSame('enabled', $this->directory('one.example')->getObject('editor')->get('state'));

        // The account is saved by a request for the second host.
        $two = $this->directory('two.example');
        file_put_contents($file, "state: disabled\n");
        clearstatcache();
        CompiledYamlFile::instance($file)->free();
        $two->reloadIndex();

        self::assertSame('disabled', $this->directory('one.example')->getObject('editor')->get('state'));
    }

    public function testDifferentFlexStoragesHaveDifferentIndexes(): void
    {
        foreach (['accounts' => 'enabled', 'other' => 'disabled'] as $folder => $state) {
            mkdir($this->root . '/' . $folder, 0777, true);
            file_put_contents($this->root . "/{$folder}/editor.yaml", "state: {$state}\n");
            touch($this->root . "/{$folder}/editor.yaml", time() - 10);
        }

        self::assertSame('enabled', $this->directory('one.example')->getObject('editor')->get('state'));
        $shared = $this->grav['cache']->getSharedKey();

        // Same configuration and shared cache key, but the accounts are kept somewhere else.
        $two = $this->directory('two.example', 'other');

        self::assertSame($shared, $this->grav['cache']->getSharedKey());
        self::assertSame('disabled', $two->getObject('editor')->get('state'));
    }

    public function testFlexRenderedOutputIsKeptApartByHost(): void
    {
        $one = $this->directory('one.example');
        $one->getCache('render')->set('block', 'http://one.example/');

        self::assertNull($this->directory('two.example')->getCache('render')->get('block'));

        $one = $this->directory('one.example');

        self::assertSame('http://one.example/', $one->getCache('render')->get('block'));

        // A save still clears the rendered output of the host it was made on.
        $one->reloadIndex();

        self::assertNull($one->getCache('render')->get('block'));
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

    /**
     * The accounts directory of a request for the given host.
     */
    private function directory(string $host, string $folder = 'accounts'): FlexDirectory
    {
        unset($this->grav['cache']);
        $this->grav['cache'] = $this->cache($host);

        $enabled = ['enabled' => true, 'lifetime' => 60];

        return new FlexDirectory('user-accounts', 'blueprints://flex/user-accounts.yaml', [
            'enabled' => true,
            'data' => ['storage' => ['options' => ['folder' => substr($this->root, strlen(GRAV_ROOT) + 1) . '/' . $folder]]],
            'object' => ['cache' => ['index' => $enabled, 'object' => $enabled, 'render' => $enabled]],
        ]);
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
