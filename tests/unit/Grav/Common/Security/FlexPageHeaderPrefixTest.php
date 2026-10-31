<?php

use Codeception\Util\Fixtures;
use Grav\Common\Flex\Types\Pages\PageObject;
use Grav\Common\Grav;
use Grav\Framework\Flex\Flex;
use Grav\Framework\Flex\FlexDirectory;

/**
 * A Flex page stores `header.*` properties in its header. Only the leading `header.` is the prefix,
 * so a name which repeats it (`header.header.foo`) is stored under `header.foo`, the same key the
 * blueprint checked.
 */
class FlexPageHeaderPrefixTest extends \PHPUnit\Framework\TestCase
{
    /** @var FlexDirectory */
    protected $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $grav = Fixtures::get('grav');
        /** @var Grav $grav */
        $grav = $grav();

        $flex = new Flex([], ['object' => $grav['config']->get('system.flex', [])]);
        $flex->addDirectoryType('pages', 'blueprints://flex/pages.yaml', ['enabled' => true]);

        $directory = $flex->getDirectory('pages');
        self::assertNotNull($directory);
        $this->directory = $directory;
    }

    public function testSetNestedPropertyStripsThePrefixOnly(): void
    {
        $page = $this->page();

        $page->setNestedProperty('header.title', 'Changed');
        $page->setNestedProperty('header.header.permissions.authors', ['member']);
        $page->setNestedProperty('header.subheader.text', 'Text');
        $page->setNestedProperty('header/header/permissions/inherit', false, '/');

        $header = $page->header();
        self::assertSame('Changed', $header->get('title'));
        self::assertSame(['authors' => ['editor']], $header->get('permissions'));
        self::assertSame(['authors' => ['member'], 'inherit' => false], $header->get('header.permissions'));
        self::assertSame('Text', $header->get('subheader.text'));
        self::assertNull($header->get('subtext'));
    }

    public function testUnsetNestedPropertyStripsThePrefixOnly(): void
    {
        $page = $this->page();

        $page->unsetNestedProperty('header.header.permissions.authors');
        self::assertSame(['editor'], $page->header()->get('permissions.authors'));

        $page->unsetNestedProperty('header.permissions.authors');
        self::assertNull($page->header()->get('permissions.authors'));
    }

    public function testUpdateDropsGuardedHeaderFields(): void
    {
        $page = $this->page();

        // No logged-in user: security@ on header.permissions.* marks those fields as ignored.
        $page->update(['header' => [
            'title' => 'Changed',
            'permissions' => ['authors' => ['member']],
            'header.permissions.authors' => ['member'],
            'header.permissions' => ['groups' => ['defaults' => 'crudl']],
        ]]);

        $header = $page->header();
        self::assertSame('Changed', $header->get('title'));
        self::assertSame(['authors' => ['editor']], $header->get('permissions'));
    }

    protected function page(): PageObject
    {
        $meta = ['key' => 'notes', 'storage_key' => 'notes', 'storage_timestamp' => 1];
        $header = ['title' => 'Notes', 'permissions' => ['authors' => ['editor']]];

        /** @var PageObject $page */
        $page = $this->directory->createObject(['__META' => $meta, 'header' => $header], 'notes');

        return $page;
    }
}
