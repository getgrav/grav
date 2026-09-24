<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Common\Page\Interfaces\PageInterface;
use Grav\Common\Page\Page;
use Grav\Common\Page\Pages;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

/**
 * The pages cache stores pages without the raw frontmatter text; frontmatter() reads it
 * back from the file.
 */
class PageSerializeTest extends \PHPUnit\Framework\TestCase
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

        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];
        $locator->addPath('page', '', 'tests/fake/simple-site/user/pages', false);
        $this->pages->init();
    }

    public function testFreedFrontmatterIsReadBackFromTheFile(): void
    {
        $page = $this->pages->find('/blog');
        self::assertInstanceOf(PageInterface::class, $page);
        $frontmatter = $page->frontmatter();
        self::assertSame('title: Blog', $frontmatter);

        $page->freeFrontmatter();
        $serialized = serialize($page);
        self::assertStringNotContainsString('title: Blog', $serialized);

        $copy = unserialize($serialized);
        self::assertInstanceOf(Page::class, $copy);
        self::assertEquals($page->header(), $copy->header());
        self::assertSame($page->title(), $copy->title());
        self::assertSame($page->route(), $copy->route());
        self::assertSame($frontmatter, $copy->frontmatter());
        self::assertSame($frontmatter, $copy->value('frontmatter'));

        // The page in memory reads it back too.
        self::assertSame($frontmatter, $page->frontmatter());
    }
}
