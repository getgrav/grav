<?php

use Codeception\Util\Fixtures;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use Grav\Common\Page\Header;
use Grav\Common\Page\Media;
use Grav\Common\Page\Page;

/**
 * Class PageSetterHardeningTest
 *
 * Page accessors double as setters. What they are given only ever changes the
 * page object they are called on: a header holds plain data, metadata content
 * is escaped like the metadata built from the header, and media or content set
 * in memory get an id of their own instead of sharing the page's cache entries.
 */
class PageSetterHardeningTest extends \Codeception\Test\Unit
{
    /** @var Grav */
    protected $grav;

    /** @var string */
    protected $folder;

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $this->grav = $grav();

        $this->folder = sys_get_temp_dir() . '/grav-page-setters-' . bin2hex(random_bytes(4));
        Folder::create($this->folder . '/01.page');
        Folder::create($this->folder . '/media');
        file_put_contents($this->folder . '/01.page/default.md', "---\ntitle: Page\n---\nBody\n");
        file_put_contents($this->folder . '/media/other.txt', 'x');
    }

    protected function tearDown(): void
    {
        Folder::delete($this->folder);
        parent::tearDown();
    }

    public function testHeaderKeepsPlainDataOnly(): void
    {
        $page = $this->page();
        $page->header(['title' => 'New', 'nested' => ['config' => $this->grav['config'], 'list' => [1, 2]], 'grav' => $this->grav]);

        $header = $page->header();
        $this->assertSame('New', $header->title);
        $this->assertSame(['config' => null, 'list' => [1, 2]], $header->nested);
        $this->assertNull($header->grav);

        $page->header($this->grav['config']);
        $this->assertSame([], (array) $page->header());
    }

    public function testHeaderPlainDataKeepsHeadersAndStdClass(): void
    {
        $object = new \stdClass();
        $object->title = 'T';
        $object->inner = (object) ['uri' => $this->grav['uri'], 'ok' => true];

        $plain = Header::plainData($object);
        $this->assertInstanceOf(\stdClass::class, $plain);
        $this->assertSame('T', $plain->title);
        $this->assertNull($plain->inner->uri);
        $this->assertTrue($plain->inner->ok);

        $this->assertSame(['a' => ['b' => 1]], Header::plainData(new Header(['a' => ['b' => 1]])));
    }

    public function testMetadataSetterEscapesContent(): void
    {
        $page = $this->page();
        $page->metadata([
            'note' => ['name' => 'note', 'content' => '"><b>x</b>'],
            'done' => ['name' => 'done', 'content' => 'Tom &amp; Jerry'],
            'object' => ['name' => 'object', 'content' => $this->grav['uri']],
            'scalar' => 'value',
        ]);

        $metadata = $page->metadata();
        $this->assertSame('&quot;&gt;&lt;b&gt;x&lt;/b&gt;', $metadata['note']['content']);
        $this->assertSame('Tom &amp; Jerry', $metadata['done']['content']);
        $this->assertNull($metadata['object']['content']);
        $this->assertArrayNotHasKey('scalar', $metadata);
    }

    public function testMediaSetterDoesNotChangeTheSharedMediaCache(): void
    {
        $page = $this->page();
        $id = $page->id();
        $own = $page->media();
        $other = new Media($this->folder . '/media');

        $page->media($other);

        $this->assertSame($other, $page->media());
        $this->assertNotSame($id, $page->id());

        // A fresh object for the same page still finds its own folder's media.
        $fresh = $this->page();
        $fresh->id($id);
        $this->assertSame(array_keys($own->all()), array_keys($fresh->media()->all()));
        $this->assertArrayNotHasKey('other.txt', $fresh->media()->all());
    }

    public function testContentSetterUsesAnIdTheIndexNeverGives(): void
    {
        $page = $this->page();
        $indexId = time() . md5((string) $page->filePath());
        $page->id($indexId);
        $page->process(['twig' => false, 'markdown' => false]);

        $page->content('Changed');

        $this->assertStringNotContainsString($indexId, $page->id());
        $this->assertStringStartsWith('mem-', substr($page->id(), strlen((string) $this->grav['language']->getLanguage())));

        $page->header(['title' => 'Other']);
        $this->assertStringContainsString('mem-', $page->id());
        $this->assertNotSame($indexId, $page->getCacheKey());
    }

    public function testDefaultIdIsNotIndexShaped(): void
    {
        $page = $this->page();

        $this->assertStringContainsString('mem-', $page->id());
    }

    private function page(): Page
    {
        $page = new Page();
        $page->init(new \SplFileInfo($this->folder . '/01.page/default.md'));

        return $page;
    }
}
