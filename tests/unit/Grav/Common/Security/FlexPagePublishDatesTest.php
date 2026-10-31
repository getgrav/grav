<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Framework\Flex\Flex;
use Grav\Framework\Flex\Pages\FlexPageObject;

/**
 * With `system.pages.publish_dates` on, a Flex page without an explicit `published` header is only
 * published between its `publish_date` and `unpublish_date`, the same as Page::setPublishState().
 * Routing (`routable()`) and menus (`visible()`) ask `published()`, so they follow the dates too.
 */
class FlexPagePublishDatesTest extends \PHPUnit\Framework\TestCase
{
    /** @var Grav */
    protected $grav;

    /** @var Flex */
    protected $flex;

    /** @var mixed */
    protected $publishDates;

    protected function setUp(): void
    {
        parent::setUp();

        $grav = Fixtures::get('grav');
        $this->grav = $grav();

        $this->publishDates = $this->grav['config']->get('system.pages.publish_dates');
        $this->grav['config']->set('system.pages.publish_dates', true);

        $this->flex = new Flex([], ['object' => $this->grav['config']->get('system.flex', [])]);
        $this->flex->addDirectoryType('pages', 'blueprints://flex/pages.yaml', ['enabled' => true]);
    }

    protected function tearDown(): void
    {
        $this->grav['config']->set('system.pages.publish_dates', $this->publishDates);

        parent::tearDown();
    }

    public function testDatesDecideWithoutExplicitPublished(): void
    {
        self::assertTrue($this->page([])->published());
        self::assertFalse($this->page(['publish_date' => '01-01-2099 00:00'])->published(), 'Scheduled page');
        self::assertFalse($this->page(['unpublish_date' => '01-01-2020 00:00'])->published(), 'Expired page');
        self::assertTrue($this->page(['publish_date' => '01-01-2020 00:00', 'unpublish_date' => '01-01-2099 00:00'])->published());
    }

    public function testRoutableAndVisibleFollowTheDates(): void
    {
        foreach ([['publish_date' => '01-01-2099 00:00'], ['unpublish_date' => '01-01-2020 00:00']] as $header) {
            $page = $this->page($header + ['visible' => true, 'routable' => true]);

            self::assertFalse($page->routable());
            self::assertFalse($page->visible());
            self::assertFalse($page->isPublished());
        }

        $page = $this->page(['visible' => true, 'routable' => true]);
        self::assertTrue($page->published());
        self::assertTrue($page->visible());
    }

    public function testExplicitPublishedWins(): void
    {
        self::assertTrue($this->page(['published' => true, 'publish_date' => '01-01-2099 00:00'])->published());
        self::assertTrue($this->page(['published' => true, 'unpublish_date' => '01-01-2020 00:00'])->published());
        self::assertFalse($this->page(['published' => false, 'publish_date' => '01-01-2020 00:00'])->published());
    }

    public function testSetterOverridesTheDates(): void
    {
        $page = $this->page(['publish_date' => '01-01-2099 00:00']);
        self::assertFalse($page->published());

        $page->published(true);
        self::assertTrue($page->published());
    }

    public function testDatesAreIgnoredWhenTheOptionIsOff(): void
    {
        $this->grav['config']->set('system.pages.publish_dates', false);

        self::assertTrue($this->page(['publish_date' => '01-01-2099 00:00'])->published());
        self::assertTrue($this->page(['unpublish_date' => '01-01-2020 00:00'])->published());
    }

    /**
     * The answer is not kept on the object: a page loaded before its publish or unpublish
     * date changes state when the date passes.
     */
    public function testLoadedPageFollowsTheDatesOverTime(): void
    {
        $format = 'Y-m-d H:i:s';
        $scheduled = $this->page(['publish_date' => date($format, time() + 1), 'visible' => true]);
        $expiring = $this->page(['unpublish_date' => date($format, time() + 1), 'visible' => true]);

        self::assertFalse($scheduled->published());
        self::assertFalse($scheduled->visible());
        self::assertTrue($expiring->published());
        self::assertTrue($expiring->visible());

        sleep(2);

        self::assertTrue($scheduled->published());
        self::assertTrue($scheduled->visible());
        self::assertFalse($expiring->published());
        self::assertFalse($expiring->visible());
    }

    public function testCacheLifetimeEndsAtTheNextDate(): void
    {
        $cache = $this->grav['cache'];
        $lifetime = $cache->getLifetime();

        $this->page(['publish_date' => date('Y-m-d H:i:s', time() + 3600)])->published();
        self::assertLessThanOrEqual(3600, $cache->getLifetime());
        self::assertGreaterThan(3500, $cache->getLifetime());

        $this->page(['unpublish_date' => date('Y-m-d H:i:s', time() + 600)])->published();
        self::assertLessThanOrEqual(600, $cache->getLifetime());
        self::assertLessThan($lifetime, $cache->getLifetime());
    }

    protected function page(array $header): FlexPageObject
    {
        $directory = $this->flex->getDirectory('pages');
        self::assertNotNull($directory);

        $page = $directory->createObject(['header' => ['title' => 'Page'] + $header, 'markdown' => 'Text'], 'page');
        self::assertInstanceOf(FlexPageObject::class, $page);

        return $page;
    }
}
