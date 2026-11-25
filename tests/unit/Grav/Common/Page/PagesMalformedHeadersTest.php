<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Common\Page\Interfaces\PageInterface;
use Grav\Common\Page\Pages;
use Grav\Framework\Flex\Flex;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

/**
 * A page whose collection, child order or form header cannot be used is a broken page,
 * not a broken site: the index of all pages is still built.
 */
class PagesMalformedHeadersTest extends \PHPUnit\Framework\TestCase
{
    /** @var Grav */
    protected $grav;

    /** @var Pages */
    protected $pages;

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $this->grav = $grav();
        $this->pages = $this->grav['pages'];
        $this->grav['config']->set('system.home.alias', '/home');

        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];
        $locator->addPath('page', '', 'tests/fake/malformed-headers-site/user/pages', false);

        // Like Errors::resetHandlers() on a site: a warning is an exception (unless @-silenced).
        set_error_handler(static function (int $errno, string $message, string $file, int $line): bool {
            if (!($errno & error_reporting())) {
                return false;
            }

            throw new ErrorException($message, 0, $errno, $file, $line);
        }, E_WARNING);

        $this->pages->init();
    }

    protected function tearDown(): void
    {
        restore_error_handler();
        parent::tearDown();
    }

    public function testEveryPageIsIndexed(): void
    {
        foreach (['/', '/landing', '/numbers', '/team', '/team/anna', '/team/ben', '/feedback'] as $route) {
            self::assertInstanceOf(PageInterface::class, $this->pages->find($route, true), $route);
        }
    }

    public function testUnknownSortFlagIsIgnored(): void
    {
        // Only SORT_* constant names are read as sort flags; anything else is left out.
        self::assertCount(1, $this->pages->find('/landing')->collection());
    }

    public function testOnlyCommandsSelectPages(): void
    {
        $page = $this->pages->find('/numbers');

        self::assertCount(0, $page->collection());
        foreach ([5, 0, 1.5, true, false, null, [5], [[false]]] as $items) {
            self::assertCount(0, $page->evaluate($items), json_encode($items));
        }
        // A value that is not a command does not take the commands next to it away.
        self::assertCount(1, $page->evaluate(['@self.modular', 5]));
    }

    public function testNestedHeaderValueDoesNotStopTheChildOrder(): void
    {
        $team = $this->pages->find('/team');

        self::assertSame('header.taxonomy', $team->orderBy());
        self::assertEqualsCanonicalizing(['anna', 'ben'], $this->slugs($team->children()));
        // A list of scalars is still sorted by its values.
        self::assertSame(['ben', 'anna'], $this->slugs($team->children()->order('header.taxonomy.tag')));
    }

    public function testFlexPagesAreOrderedByNestedHeaderValue(): void
    {
        $flex = new Flex([], ['object' => $this->grav['config']->get('system.flex', [])]);
        $flex->addDirectoryType('pages', 'blueprints://flex/pages.yaml', ['enabled' => true]);
        $directory = $flex->getDirectory('pages');
        self::assertNotNull($directory);

        $collection = $directory->createCollection([
            'anna' => $directory->createObject(['header' => ['taxonomy' => ['tag' => ['news', 'grav']]]], 'anna'),
            'ben' => $directory->createObject(['header' => ['taxonomy' => ['tag' => ['news']]]], 'ben'),
        ]);

        self::assertEqualsCanonicalizing(['anna', 'ben'], $collection->order('header.taxonomy')->getKeys());
        self::assertSame(['ben', 'anna'], $collection->order('header.taxonomy.tag')->getKeys());
    }

    public function testFormNameThatIsNotScalarFallsBackToTheSlug(): void
    {
        $forms = $this->pages->find('/feedback')->getForms();

        self::assertSame(['feedback'], array_keys($forms));
        self::assertSame('feedback', $forms['feedback']['name']);
        self::assertArrayHasKey('email', $forms['feedback']['fields']);
    }

    /**
     * @param iterable<PageInterface> $collection
     * @return string[]
     */
    private function slugs(iterable $collection): array
    {
        $slugs = [];
        foreach ($collection as $page) {
            $slugs[] = $page->slug();
        }

        return $slugs;
    }
}
