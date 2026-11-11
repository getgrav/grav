<?php

use Codeception\Util\Fixtures;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use Grav\Framework\Flex\FlexDirectory;

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
     */
    private function directory(array $pages): FlexDirectory
    {
        foreach ($pages as $folder => $frontmatter) {
            $path = GRAV_ROOT . "/{$this->folder}/{$folder}";
            mkdir($path, 0777, true);
            file_put_contents("{$path}/default.md", "---\ntitle: Page\n{$frontmatter}---\n");
        }

        return new FlexDirectory('pages', 'blueprints://flex/pages.yaml', [
            'enabled' => true,
            'data' => ['storage' => ['options' => ['folder' => $this->folder, 'indexed' => false]]],
            'object' => ['cache' => ['index' => ['enabled' => false], 'object' => ['enabled' => false], 'render' => ['enabled' => false]]],
        ]);
    }
}
