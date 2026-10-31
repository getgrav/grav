<?php

use Codeception\Util\Fixtures;
use Grav\Common\Data\Blueprint;
use Grav\Common\Grav;
use Grav\Common\Page\Pages;
use Grav\Framework\Flex\FlexDirectory;

/**
 * Class BlueprintFileProvenanceTest
 *
 * A blueprint file may call any `data-*@` provider only when it lives in a
 * blueprint, plugin or theme folder. A file anywhere else still loads, but its
 * providers go through the same allowlist as a form in page frontmatter, and
 * Pages::blueprints() treats a page type that is a path or stream URI as
 * `default`.
 */
class BlueprintFileProvenanceTest extends \PHPUnit\Framework\TestCase
{
    /** @var Grav */
    protected $grav;

    /** @var string A folder on the `blueprints://` stream. */
    protected $blueprints;

    /** @var string A folder on the `page://` stream. */
    protected $media;

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $this->grav = $grav();

        $dir = sys_get_temp_dir() . '/grav-bp-file-' . bin2hex(random_bytes(6));
        $this->blueprints = $dir . '/blueprints';
        $this->media = $dir . '/pages';
        mkdir($this->blueprints, 0775, true);
        mkdir($this->media . '/gallery', 0775, true);

        $this->grav['locator']->addPath('blueprints', '', $this->blueprints);
        $this->grav['locator']->addPath('page', '', $this->media);
    }

    protected function tearDown(): void
    {
        @unlink($this->blueprints . '/linked');
        @unlink($this->media . '/gallery/blueprint');
        foreach ([$this->blueprints, $this->media . '/gallery'] as $dir) {
            foreach (glob($dir . '/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($dir);
        }
        @rmdir($this->media);
        @rmdir(dirname($this->media));
        parent::tearDown();
    }

    /** A blueprint whose provider is deliberately NOT on the static allowlist. */
    protected function write(string $path): string
    {
        file_put_contents($path, <<<'YAML'
title: Probe
form:
  fields:
    header.note:
      type: text
      data-default@: ['\Grav\Common\Utils::truncate', 'provider ran', 8]
YAML);

        return $path;
    }

    protected function load(string $filename): Blueprint
    {
        $blueprint = new Blueprint($filename);
        $blueprint->load()->init();

        return $blueprint;
    }

    public function testLoad_FileInBlueprintFolderIsTrusted(): void
    {
        $blueprint = $this->load($this->write($this->blueprints . '/probe.yaml'));

        self::assertTrue($blueprint->isTrusted());
        self::assertNotNull($blueprint->get('form/fields/header.note/default'));
    }

    public function testLoad_SymlinkedFolderInBlueprintFolderIsTrusted(): void
    {
        $this->write($this->media . '/gallery/probe.yaml');
        if (!@symlink($this->media . '/gallery', $this->blueprints . '/linked')) {
            self::markTestSkipped('Cannot create symlinks');
        }

        self::assertTrue($this->load('blueprints://linked/probe.yaml')->isTrusted());
    }

    /**
     * @dataProvider providerUploadedFile
     */
    public function testLoad_FileOutsideBlueprintFoldersIsUntrusted(string $spelling): void
    {
        $this->write($this->media . '/gallery/blueprint.txt');

        $blueprint = $this->load(sprintf($spelling, $this->media, $this->blueprints));

        self::assertFalse($blueprint->isTrusted());
        self::assertSame('text', $blueprint->get('form/fields/header.note/type'), 'The file itself still loads');
        self::assertNull($blueprint->get('form/fields/header.note/default'), 'The provider must not run');
    }

    public function testLoad_ParentOutsideBlueprintFoldersIsUntrusted(): void
    {
        $parent = $this->write($this->media . '/gallery/blueprint.txt');
        file_put_contents($this->blueprints . '/child.yaml', "title: Child\nextends@: '{$parent}'\n");

        $blueprint = $this->load($this->blueprints . '/child.yaml');

        self::assertNull($blueprint->get('form/fields/header.note/default'), 'The provider must not run');
    }

    /**
     * Flex pages hand the page template to FlexDirectory::getBlueprint(), which
     * loads it as the parent of the directory blueprint without going through
     * Pages::blueprints(). The file still loads, but as an untrusted blueprint.
     */
    public function testFlexGetBlueprint_TemplateOutsideBlueprintFoldersIsUntrusted(): void
    {
        $template = $this->media . '/gallery/blueprint';
        if (str_contains($template, '.')) {
            self::markTestSkipped('Temporary folder name contains a dot');
        }
        $this->write($template);
        file_put_contents($this->blueprints . '/flexprobe.yaml', "title: Flex Probe\ntype: flexprobe\n");

        $directory = new FlexDirectory('flexprobe', $this->blueprints . '/flexprobe.yaml');
        $blueprint = $directory->getBlueprint($template, 'blueprints://pages');
        $blueprint->init();

        self::assertFalse($blueprint->isTrusted());
        self::assertSame('text', $blueprint->get('form/fields/header.note/type'), 'The file itself still loads');
        self::assertNull($blueprint->get('form/fields/header.note/default'), 'The provider must not run');
    }

    /**
     * Negative control for the Flex loader: a template in a blueprint folder keeps
     * its providers.
     */
    public function testFlexGetBlueprint_TemplateInBlueprintFolderIsTrusted(): void
    {
        $this->write($this->blueprints . '/probe.yaml');
        file_put_contents($this->blueprints . '/flexprobe.yaml', "title: Flex Probe\ntype: flexprobe\n");

        $directory = new FlexDirectory('flexprobe', $this->blueprints . '/flexprobe.yaml');
        $blueprint = $directory->getBlueprint('probe', 'blueprints://');
        $blueprint->init();

        self::assertTrue($blueprint->isTrusted());
        self::assertNotNull($blueprint->get('form/fields/header.note/default'));
    }

    /**
     * @dataProvider providerUploadedFile
     */
    public function testPagesBlueprints_TypeIsNotAPath(string $spelling): void
    {
        $this->write($this->media . '/gallery/blueprint.txt');

        /** @var Pages $pages */
        $pages = $this->grav['pages'];
        $blueprint = $pages->blueprints(sprintf($spelling, $this->media, $this->blueprints));

        self::assertNull($blueprint->get('form/fields/header.note'));
        self::assertSame($pages->blueprints('default')->get('title'), $blueprint->get('title'));
    }

    /** Ways to name the uploaded file; `%1$s` is the pages folder, `%2$s` the blueprints one. */
    public static function providerUploadedFile(): array
    {
        return [
            'path' => ['%1$s/gallery/blueprint.txt'],
            'stream' => ['page://gallery/blueprint.txt'],
            'path starting in a blueprint folder' => ['%2$s/../pages/gallery/blueprint.txt'],
        ];
    }
}
