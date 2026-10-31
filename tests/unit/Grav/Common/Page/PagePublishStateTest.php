<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Common\Page\Page;

/**
 * Class PagePublishStateTest
 *
 * A page is visible from the second of its `publish_date` on and gone from the second of its `unpublish_date` on,
 * so the cache is never asked to end in the current second.
 */
class PagePublishStateTest extends \PHPUnit\Framework\TestCase
{
    /** @var Grav $grav */
    protected $grav;

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $this->grav = $grav();
        $this->grav['config']->set('system.pages.publish_dates', true);
    }

    /**
     * @param array $header
     * @return Page
     */
    protected function pageWithHeader(array $header): Page
    {
        $page = new Page();
        $page->header((object)$header);
        foreach ($header as $key => $value) {
            $page->{$key === 'publish_date' ? 'publishDate' : 'unpublishDate'}($value);
        }

        $method = new ReflectionMethod(Page::class, 'setPublishState');
        $method->setAccessible(true);
        $method->invoke($page);

        return $page;
    }

    public function testUnpublishDateInTheFutureKeepsPagePublished(): void
    {
        $page = $this->pageWithHeader(['unpublish_date' => time() + 60]);
        self::assertTrue($page->published());
    }

    public function testUnpublishDateInCurrentSecondUnpublishesPage(): void
    {
        $page = $this->pageWithHeader(['unpublish_date' => time()]);
        self::assertFalse($page->published());
    }

    public function testUnpublishDateInThePastUnpublishesPage(): void
    {
        $page = $this->pageWithHeader(['unpublish_date' => time() - 60]);
        self::assertFalse($page->published());
    }

    public function testPublishDateInCurrentSecondPublishesPage(): void
    {
        $page = $this->pageWithHeader(['publish_date' => time()]);
        self::assertTrue($page->published());
    }

    public function testPublishDateInTheFutureKeepsPageUnpublished(): void
    {
        $page = $this->pageWithHeader(['publish_date' => time() + 60]);
        self::assertFalse($page->published());
    }
}
