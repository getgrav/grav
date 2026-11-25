<?php

use Codeception\Util\Fixtures;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Flex\Types\Pages\PageObject;
use Grav\Common\Grav;
use Grav\Framework\Flex\FlexDirectory;

/**
 * Counts how many times pages are asked whether they cascade: unpublishedBy() asks each ancestor once.
 */
class CascadeCountingPageObject extends PageObject
{
    /** @var int */
    public static $asked = 0;

    public function cascadesUnpublish(): bool
    {
        self::$asked++;

        return parent::cascadesUnpublish();
    }
}

/**
 * Flex pages follow `cascade_unpublish` the same way regular pages do: while a page that cascades is
 * unpublished, by its `published` setting or by its dates, every page below it is unpublished too.
 */
class FlexPageCascadeUnpublishTest extends \PHPUnit\Framework\TestCase
{
    /** @var string */
    protected $folder;

    /** @var array<string,mixed> */
    protected $configBefore = [];

    protected function setUp(): void
    {
        parent::setUp();

        $grav = Fixtures::get('grav');
        /** @var Grav $grav */
        $grav = $grav();

        foreach (['system.pages.publish_dates', 'system.pages.cascade_unpublish'] as $key) {
            $this->configBefore[$key] = $grav['config']->get($key);
        }
        $grav['config']->set('system.pages.publish_dates', true);
        $grav['config']->set('system.pages.cascade_unpublish', false);

        $this->folder = 'tmp/flex-cascade-unpublish-' . uniqid('', false);
    }

    protected function tearDown(): void
    {
        foreach ($this->configBefore as $key => $value) {
            Grav::instance()['config']->set($key, $value);
        }

        Folder::delete(GRAV_ROOT . '/' . $this->folder);
        parent::tearDown();
    }

    public function testCascadingParentUnpublishesThePagesBelow(): void
    {
        $directory = $this->directory([
            'section' => "published: false\ncascade_unpublish: true\n",
            'section/child' => "published: true\nvisible: true\n",
            'section/child/grandchild' => '',
        ]);

        foreach (['section/child', 'section/child/grandchild'] as $key) {
            $page = $directory->getObject($key);
            self::assertFalse($page->published(), $key);
            self::assertFalse($page->routable(), $key);
            self::assertFalse($page->visible(), $key);
            self::assertSame('/section', $page->unpublishedBy(), $key);
        }

        self::assertTrue($directory->getObject('section/child')->getNestedProperty('header.published'), 'The header is left alone');
        self::assertNull($directory->getObject('section')->unpublishedBy(), 'Unpublished by its own setting');
    }

    public function testParentWithoutCascadeLeavesChildrenPublished(): void
    {
        $directory = $this->directory(['plain' => "published: false\n", 'plain/child' => '']);

        self::assertTrue($directory->getObject('plain/child')->published());
        self::assertNull($directory->getObject('plain/child')->unpublishedBy());
    }

    public function testDatesOnTheParentCascade(): void
    {
        $directory = $this->directory([
            'scheduled' => "publish_date: '2099-01-01 00:00'\ncascade_unpublish: true\n",
            'scheduled/child' => '',
            'expired' => "unpublish_date: '2020-01-01 00:00'\ncascade_unpublish: true\n",
            'expired/child' => '',
            'live' => "publish_date: '2020-01-01 00:00'\ncascade_unpublish: true\n",
            'live/child' => '',
        ]);

        self::assertFalse($directory->getObject('scheduled/child')->published());
        self::assertFalse($directory->getObject('expired/child')->published());
        self::assertTrue($directory->getObject('live/child')->published());
    }

    public function testSiteDefaultAndPageOverride(): void
    {
        Grav::instance()['config']->set('system.pages.cascade_unpublish', true);
        $directory = $this->directory([
            'plain' => "published: false\n",
            'plain/child' => '',
            'opt-out' => "published: false\ncascade_unpublish: false\n",
            'opt-out/child' => '',
        ]);

        self::assertFalse($directory->getObject('plain/child')->published());
        self::assertTrue($directory->getObject('opt-out/child')->published());
    }

    /**
     * Each page asks every page above it once. unpublishedBy() used to walk the ancestors a second time through
     * the parent's published(), which doubled the work at every level of a tree of published pages that cascade.
     */
    public function testDeepTreeOfCascadingPagesIsWalkedOnce(): void
    {
        $depth = 16;
        $pages = [];
        foreach (['live', 'cut'] as $top) {
            $folder = $top;
            for ($level = 2; $level <= $depth; $level++) {
                $pages[$folder] = "cascade_unpublish: true\n";
                $folder .= "/p{$level}";
            }
            $pages[$folder] = '';
        }
        $pages['cut/p2/p3'] = $pages['cut/p2/p3/p4/p5'] = "published: false\ncascade_unpublish: true\n";
        $directory = $this->directory($pages, CascadeCountingPageObject::class);
        $deepest = substr($folder, strlen('cut'));

        CascadeCountingPageObject::$asked = 0;
        self::assertTrue($directory->getObject("live{$deepest}")->published());
        self::assertSame($depth - 1, CascadeCountingPageObject::$asked, 'Every ancestor is asked once');
        self::assertNull($directory->getObject("live{$deepest}")->unpublishedBy());

        self::assertFalse($directory->getObject("cut{$deepest}")->published());
        self::assertSame('/cut/p2/p3', $directory->getObject("cut{$deepest}")->unpublishedBy(), 'The ancestor furthest up wins');
        self::assertNull($directory->getObject('cut/p2/p3')->unpublishedBy(), 'Unpublished by its own setting');
        self::assertFalse($directory->getObject('cut/p2/p3')->published());
    }

    /**
     * With regular pages on the site, PageObject::parent() returns a regular page outside of admin.
     */
    public function testRegularParentCascadesToAFlexChild(): void
    {
        $directory = $this->directory(['section' => "published: false\ncascade_unpublish: true\n", 'section/child' => '']);

        $grav = Grav::instance();
        $grav['flex']->addDirectory($directory);
        $grav['locator']->addPath('page', '', $this->folder, false);

        /** @var \Grav\Common\Page\Pages $pages */
        $pages = $grav['pages'];
        $pages->register();
        $pages->init();

        $child = $directory->getObject('section/child');
        self::assertNotInstanceOf(\Grav\Framework\Flex\Interfaces\FlexObjectInterface::class, $child->parent());
        self::assertFalse($child->published());
        self::assertSame('/section', $child->unpublishedBy());
    }

    /**
     * @param array<string,string> $pages Frontmatter of every page, by its folder.
     * @param string $object Class of the page objects.
     */
    private function directory(array $pages, string $object = PageObject::class): FlexDirectory
    {
        foreach ($pages as $folder => $frontmatter) {
            $path = GRAV_ROOT . "/{$this->folder}/{$folder}";
            mkdir($path, 0777, true);
            file_put_contents("{$path}/default.md", "---\ntitle: Page\n{$frontmatter}---\n");
        }

        return new FlexDirectory('pages', 'blueprints://flex/pages.yaml', [
            'enabled' => true,
            'data' => ['object' => $object, 'storage' => ['options' => ['folder' => $this->folder, 'indexed' => false]]],
            'object' => ['cache' => ['index' => ['enabled' => false], 'object' => ['enabled' => false], 'render' => ['enabled' => false]]],
        ]);
    }
}
