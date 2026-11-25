<?php

use Codeception\Util\Fixtures;
use Grav\Common\Flex\Types\Pages\PageObject;
use Grav\Common\Grav;
use Grav\Framework\Flex\Flex;
use Grav\Framework\Flex\FlexDirectory;

/**
 * Class FlexPageTemplateBlueprintTest
 *
 * A Flex page names its template in a header, which is content. Only a registered page type
 * selects the page's blueprint: a path, a stream URI or a dotted value is read as `default`.
 */
class FlexPageTemplateBlueprintTest extends \PHPUnit\Framework\TestCase
{
    /** @var FlexDirectory */
    protected $directory;

    /** @var string */
    protected $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $grav = Fixtures::get('grav');
        /** @var Grav $grav */
        $grav = $grav();

        $this->dir = sys_get_temp_dir() . '/grav-flex-template-' . bin2hex(random_bytes(6));
        mkdir($this->dir . '/blueprints', 0775, true);
        $grav['locator']->addPath('blueprints', '', $this->dir . '/blueprints');

        $flex = new Flex([], ['object' => $grav['config']->get('system.flex', [])]);
        $flex->addDirectoryType('pages', 'blueprints://flex/pages.yaml', ['enabled' => true]);

        $directory = $flex->getDirectory('pages');
        self::assertNotNull($directory);
        $this->directory = $directory;
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/blueprints/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->dir . '/blueprints');
        @rmdir($this->dir);
        parent::tearDown();
    }

    public function testBlueprint_RegisteredTemplateIsUsed(): void
    {
        $blueprint = $this->page('default')->getBlueprint();

        self::assertNotNull($blueprint->get('form/fields/tabs'));
    }

    /**
     * @dataProvider providerTemplate
     */
    public function testBlueprint_TemplateThatIsNotAPageTypeIsDefault(string $template): void
    {
        $file = $this->dir . '/blueprints/probe';
        file_put_contents($file, <<<'YAML'
title: Probe
form:
  fields:
    header.probe:
      type: text
YAML);

        $default = $this->page('default')->getBlueprint();
        $blueprint = $this->page(str_replace('%s', $file, $template))->getBlueprint();

        self::assertNull($blueprint->get('form/fields/header.probe'), 'The file must not define the page blueprint');
        self::assertSame($default->get('form/fields'), $blueprint->get('form/fields'));
    }

    public static function providerTemplate(): array
    {
        return [
            'path' => ['%s'],
            'stream' => ['blueprints://probe'],
            'dotted' => ['default.'],
            'dotted type' => ['default.x'],
            'other scheme' => ['ftp://127.0.0.1:1/probe'],
        ];
    }

    public function testUpdate_TemplateThatIsNotAPageTypeKeepsGuardedHeaderFields(): void
    {
        $page = $this->page('default.');
        $page->update(['header' => ['title' => 'Changed', 'permissions' => ['authors' => ['member']]]]);

        $header = $page->header();
        self::assertSame('Changed', $header->get('title'));
        self::assertSame(['authors' => ['editor']], $header->get('permissions'));
    }

    protected function page(string $template): PageObject
    {
        $meta = ['key' => 'notes', 'storage_key' => 'notes', 'storage_timestamp' => 1];
        $header = ['title' => 'Notes', 'template' => $template, 'permissions' => ['authors' => ['editor']]];

        /** @var PageObject $page */
        $page = $this->directory->createObject(['__META' => $meta, 'header' => $header], 'notes');

        return $page;
    }
}
