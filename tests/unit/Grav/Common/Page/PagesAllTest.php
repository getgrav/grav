<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Common\Page\Collection;
use Grav\Common\Page\Interfaces\PageInterface;
use Grav\Common\Page\Pages;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

/**
 * Pages::all() walks the children index without recursion. These tests pin its order and
 * values against the recursive implementation it replaced.
 */
class PagesAllTest extends \PHPUnit\Framework\TestCase
{
    /** @var Grav */
    protected $grav;

    /** @var Pages */
    protected $pages;

    /** @var string */
    protected $base;

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $this->grav = $grav();
        $this->pages = $this->grav['pages'];

        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];
        $locator->addPath('page', '', 'tests/fake/all-order-site/user/pages', false);
        $this->pages->init();

        $this->base = (string)$this->pages->root()->path();
    }

    /**
     * The order a reader of the fixture expects: depth first, each folder's children in the
     * order its page asks for (title descending for the blog, the manual list for docs).
     */
    public function testOrderIsPinned(): void
    {
        self::assertSame([
            '01.blog',
            '01.blog/cherry',
            '01.blog/banana',
            '01.blog/banana/01.first',
            '01.blog/banana/01.first/deep',
            '01.blog/banana/02.second',
            '01.blog/apple',
            '02.docs',
            '02.docs/zeta',
            '02.docs/zeta/child',
            '02.docs/alpha',
            '02.docs/mid',
            '03.landing',
            '03.landing/_features',
            '03.landing/_hero',
            '04.about',
            '04.about/team',
            'unprefixed',
        ], $this->relative($this->pages->all()));
    }

    public function testMatchesTheRecursiveWalk(): void
    {
        self::assertSame($this->legacyAll()->toArray(), $this->pages->all()->toArray());

        $blog = $this->pages->find('/blog');
        self::assertInstanceOf(PageInterface::class, $blog);
        self::assertSame($this->legacyAll($blog)->toArray(), $this->pages->all($blog)->toArray());

        $leaf = $this->pages->find('/blog/banana/first/deep');
        self::assertInstanceOf(PageInterface::class, $leaf);
        self::assertSame($this->legacyAll($leaf)->toArray(), $this->pages->all($leaf)->toArray());
    }

    public function testSlugsComeFromTheChildrenIndexForPagesNotInMemory(): void
    {
        $expected = $this->legacyAll()->toArray();
        self::assertSame('about-us', $expected[$this->base . '/04.about']['slug']);

        // Make every page lazy, the way a lazily indexed pages cache holds them.
        $root = $this->pages->root();
        $index = $this->property('index');
        $this->setProperty('index', array_map(static fn($page) => $page === $root ? $page : true, $index));
        $this->setProperty('instances', [$this->base => $root]);

        try {
            self::assertSame($expected, $this->pages->all()->toArray());
            self::assertSame([$this->base], array_keys($this->property('instances')), 'all() loaded pages it did not need');
        } finally {
            $this->setProperty('index', $index);
        }
    }

    public function testRuntimeSlugOfALoadedPageWins(): void
    {
        $page = $this->pages->find('/docs/mid');
        self::assertInstanceOf(PageInterface::class, $page);
        $page->slug('changed');

        $all = $this->pages->all();
        self::assertSame('changed', $all->toArray()[$page->path()]['slug']);
        self::assertSame($this->legacyAll()->toArray(), $all->toArray());
    }

    public function testReturnsACollectionOfPages(): void
    {
        $all = $this->pages->all();
        self::assertInstanceOf(Collection::class, $all);
        self::assertCount(18, $all);
        foreach ($all as $page) {
            self::assertInstanceOf(PageInterface::class, $page);
        }
    }

    /**
     * Pages::all() as it was before it became iterative.
     */
    private function legacyAll(?PageInterface $current = null): Collection
    {
        $all = new Collection();
        $current = $current ?: $this->pages->root();

        if (!$current->root()) {
            $all[$current->path()] = ['slug' => $current->slug()];
        }

        foreach ($current->children() as $next) {
            $all->append($this->legacyAll($next));
        }

        return $all;
    }

    /**
     * @return string[] Paths relative to the pages folder, in order.
     */
    private function relative(Collection $all): array
    {
        $paths = [];
        foreach (array_keys($all->toArray()) as $path) {
            $paths[] = substr($path, strlen($this->base) + 1);
        }

        return $paths;
    }

    private function property(string $name)
    {
        $property = new ReflectionProperty(Pages::class, $name);
        $property->setAccessible(true);

        return $property->getValue($this->pages);
    }

    private function setProperty(string $name, $value): void
    {
        $property = new ReflectionProperty(Pages::class, $name);
        $property->setAccessible(true);
        $property->setValue($this->pages, $value);
    }
}
