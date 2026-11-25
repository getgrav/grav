<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Common\Page\Pages;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

/**
 * A collection filtered by access level (`filter: access: [site.free]`) lists the pages which name one of
 * the levels in their `access` rules. The nested spelling of a rule (`site: {premium: true}`) names the same
 * level as the flat one (`site.premium: true`): the value of a rule is not a level.
 */
class CollectionAccessFilterTest extends \PHPUnit\Framework\TestCase
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
        $locator->addPath('page', '', 'tests/fake/access-filter-site/user/pages', false);
        $this->grav['pages']->init();
    }

    /**
     * @param array $levels
     * @return string[] Titles of the matching pages.
     */
    protected function filter(array $levels): array
    {
        /** @var Pages $pages */
        $pages = $this->grav['pages'];
        $collection = $pages->find('/list', true)->children()->ofOneOfTheseAccessLevels($levels);

        $titles = [];
        foreach ($collection as $page) {
            $titles[] = $page->title();
        }
        sort($titles);

        return $titles;
    }

    public function testNestedRuleMatchesByItsNameOnly(): void
    {
        self::assertSame(['Flat Free', 'Nested Free', 'Single Free'], $this->filter(['site.free']));
        self::assertSame(['Flat Premium', 'Nested Premium'], $this->filter(['site.premium']));
        self::assertSame([], $this->filter(['site.other']));
    }

    public function testNestedRuleMatchesTheNameReportedByAccessLevels(): void
    {
        /** @var Pages $pages */
        $pages = $this->grav['pages'];

        self::assertContains('premium', $pages->accessLevels());
        self::assertSame(['Nested Premium'], $this->filter(['premium']));
    }

    public function testCollectionFilterInPageHeaderUsesTheSameRules(): void
    {
        /** @var Pages $pages */
        $pages = $this->grav['pages'];
        $collection = $pages->find('/list', true)->collection();

        $titles = [];
        foreach ($collection as $page) {
            $titles[] = $page->title();
        }
        sort($titles);

        self::assertSame(['Flat Free', 'Nested Free', 'Single Free'], $titles);
    }
}
