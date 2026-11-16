<?php

use Codeception\Util\Fixtures;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Flex\Types\Pages\PageObject;
use Grav\Common\Grav;
use Grav\Common\Page\Interfaces\PageInterface;
use Grav\Common\Page\Page;
use Grav\Framework\Flex\Flex;

/**
 * Class SummaryConfigTest
 *
 * A `summary:` key left empty in user/config/site.yaml (every child commented
 * out) replaces the shipped settings with null. summary() must then use the
 * shipped defaults instead of failing on the missing `enabled` key
 * (grav-plugin-simplesearch#211).
 */
class SummaryConfigTest extends \PHPUnit\Framework\TestCase
{
    /** @var Grav */
    protected $grav;

    /** @var string */
    protected $folder;

    /** @var mixed */
    protected $saved;

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $this->grav = $grav();
        $this->saved = $this->grav['config']->get('site.summary');

        $this->folder = sys_get_temp_dir() . '/grav-summary-config-' . uniqid('', true);
        Folder::create($this->folder);
    }

    protected function tearDown(): void
    {
        $this->grav['config']->set('site.summary', $this->saved);
        Folder::delete($this->folder);
        parent::tearDown();
    }

    public static function cases(): array
    {
        return [
            'Page, null' => ['page', null],
            'Page, empty string' => ['page', ''],
            'Flex page, null' => ['flexPage', null],
            'Flex page, empty string' => ['flexPage', ''],
        ];
    }

    /**
     * @dataProvider cases
     * @param mixed $setting
     */
    public function testEmptySummarySettingsUseTheDefaults(string $type, $setting): void
    {
        $this->grav['config']->set('site.summary', $setting);

        $summary = $this->{$type}(str_repeat('Grav is a flat-file CMS. ', 40))->summary();

        // Defaults: enabled, format short, size 300.
        self::assertLessThan(400, mb_strlen(strip_tags($summary)));
        self::assertStringStartsWith('<p>Grav is a flat-file CMS.', $summary);
    }

    protected function page(string $content): PageInterface
    {
        $file = $this->folder . '/default.md';
        file_put_contents($file, "---\ntitle: Summary\ncache_enable: false\nprocess:\n  twig: false\n---\n" . $content);

        $page = new Page();
        $page->init(new \SplFileInfo($file));

        return $page;
    }

    protected function flexPage(string $content): PageInterface
    {
        $flex = new Flex([], ['object' => $this->grav['config']->get('system.flex', [])]);
        $flex->addDirectoryType('pages', 'blueprints://flex/pages.yaml', ['enabled' => true]);

        return new PageObject(
            ['header' => ['title' => 'Summary', 'cache_enable' => false, 'process' => ['twig' => false]], 'markdown' => $content],
            'summary',
            $flex->getDirectory('pages')
        );
    }
}
