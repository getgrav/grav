<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Common\Page\Pages;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

/**
 * Collections are filtered by the taxonomy values in the URL (`/list/tag:a,b`). The list is visitor
 * input: repeating a value must not add work, and it must not change which pages match.
 */
class PagesUrlTaxonomyFilterTest extends \PHPUnit\Framework\TestCase
{
    /** @var Grav */
    protected $grav;

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $this->grav = $grav();

        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];
        $locator->addPath('page', '', 'tests/fake/taxonomy-filter-site/user/pages', false);
        $this->grav['pages']->init();
    }

    /**
     * @return array{0: string[], 1: array} Titles of the matching pages and the tag values they were filtered by.
     */
    protected function filter(string $params): array
    {
        $this->grav['uri']->initializeWithUrl('http://localhost/list/' . $params)->init();

        /** @var Pages $pages */
        $pages = $this->grav['pages'];
        $collection = $pages->find('/list', true)->collection();

        $titles = [];
        foreach ($collection as $page) {
            $titles[] = $page->title();
        }
        sort($titles);

        return [$titles, $collection->params()['taxonomies']['tag'] ?? []];
    }

    public function testPageIsRemovedOnceAfterItsFirstMissingValue(): void
    {
        $collection = new class extends \Grav\Common\Page\Collection {
            public $removals = 0;

            public function remove($key = null)
            {
                ++$this->removals;

                return parent::remove($key);
            }
        };
        $collection->addPage($this->grav['pages']->find('/list/one', true));
        $pages = $this->getMockBuilder(Pages::class)
            ->setConstructorArgs([$this->grav])
            ->onlyMethods(['evaluate'])
            ->getMock();
        $pages->method('evaluate')->willReturn($collection);
        $result = $pages->getCollection([
            'items' => '@self.children',
            'url_taxonomy_filters' => false,
            'taxonomies' => ['tag' => ['missing', 'another', 'last']],
        ]);

        self::assertCount(0, $result);
        self::assertSame(1, $collection->removals);
    }

    public function testEveryValueHasToMatch(): void
    {
        self::assertSame(['One', 'Two'], $this->filter('tag:a')[0]);
        self::assertSame(['One'], $this->filter('tag:a,b')[0]);
        self::assertSame(['One'], $this->filter('tag:b,a')[0]);
        self::assertSame([], $this->filter('tag:a,c')[0]);
        self::assertSame([], $this->filter('tag:nope,a')[0]);
    }

    public function testRepeatedValuesAreFilteredOnce(): void
    {
        [$titles, $values] = $this->filter('tag:' . implode(',', array_fill(0, 500, 'a')));
        self::assertSame(['One', 'Two'], $titles);
        self::assertSame(['a'], $values);

        [$titles, $values] = $this->filter('tag:' . str_repeat(',', 500));
        self::assertSame([], $titles);
        self::assertSame([''], $values);
    }

    public function testValuesAreComparedAfterDecoding(): void
    {
        [$titles, $values] = $this->filter('tag:a,%2561,b,%2562');
        self::assertSame(['One'], $titles);
        self::assertSame(['a', 'b'], $values);
    }
}
