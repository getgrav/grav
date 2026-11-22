<?php

use Codeception\Util\Fixtures;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use Grav\Common\Helpers\Excerpts as ExcerptsHelper;
use Grav\Common\Language\Language;
use Grav\Common\Markdown\Parsedown;
use Grav\Common\Page\Interfaces\PageInterface;
use Grav\Common\Page\Markdown\Excerpts;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

/**
 * A stream image whose first folder is a number (`media://2022/09/photo.jpg`, a
 * year/month upload folder) rendered as `//media:2022//09/photo.jpg`: the link
 * pass wrote the stream back as `media:2022/09/...`, which parse_url() reads as
 * host `media` and port 2022, so the image pass lost the stream. getgrav/grav#4417.
 */
class ExcerptsStreamNumericFolderTest extends \PHPUnit\Framework\TestCase
{
    private const ROOT = 'tests/cache/grav-4417';

    /** @var Grav */
    protected $grav;

    /** @var Parsedown */
    protected $parsedown;

    /** @var PageInterface|null */
    protected $page;

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $this->grav = $grav();

        $config = $this->grav['config'];
        $config->set('system.absolute_urls', false);
        $config->set('system.languages.supported', []);
        unset($this->grav['language']);
        $this->grav['language'] = new Language($this->grav);

        $sample = 'tests/fake/nested-site/user/pages/02.item2/02.item2-2/sample-image.jpg';
        foreach (['2022/09', 'folder'] as $folder) {
            Folder::create(self::ROOT . '/' . $folder);
            copy($sample, self::ROOT . '/' . $folder . '/photo.jpg');
        }

        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];
        $locator->addPath('page', '', 'tests/fake/nested-site/user/pages', false);
        $locator->addPath('media', '', self::ROOT, false);
        $this->grav['pages']->init();
        $this->grav['uri']->initializeWithURL('http://testing.dev/item2/item2-2')->init();

        $this->page = $this->grav['pages']->find('/item2/item2-2');
        $excerpts = new Excerpts($this->page, [
            'markdown' => ['extra' => false, 'auto_line_breaks' => false, 'auto_url_links' => false, 'escape_markup' => false, 'special_chars' => []],
            'images' => $config->get('system.images', []),
        ]);
        $this->parsedown = new Parsedown($excerpts);
    }

    protected function tearDown(): void
    {
        Folder::delete(self::ROOT);
        parent::tearDown();
    }

    /**
     * @dataProvider providerStreamImages
     */
    public function testStreamImageResolves(string $markdown, string $expected): void
    {
        self::assertSame($expected, $this->parsedown->text($markdown));
    }

    public static function providerStreamImages(): array
    {
        $src = '/' . self::ROOT;

        return [
            'numeric first folder' => [
                '![a](media://2022/09/photo.jpg)',
                '<p><img alt="a" src="' . $src . '/2022/09/photo.jpg" /></p>',
            ],
            'numeric first folder with fragment' => [
                '![a](media://2022/09/photo.jpg#top)',
                '<p><img alt="a" src="' . $src . '/2022/09/photo.jpg#top" /></p>',
            ],
            'non-numeric first folder' => [
                '![a](media://folder/photo.jpg)',
                '<p><img alt="a" src="' . $src . '/folder/photo.jpg" /></p>',
            ],
            'link to numeric first folder' => [
                '[l](media://2022/09/photo.jpg)',
                '<p><a href="' . $src . '/2022/09/photo.jpg">l</a></p>',
            ],
        ];
    }

    public function testStreamImageWithNumericFolderRunsMediaActions(): void
    {
        self::assertMatchesRegularExpression(
            '|^<p><img alt="a" class="big" src="/images/[0-9a-f/]+-photo\.jpe?g" /></p>$|',
            $this->parsedown->text('![a](media://2022/09/photo.jpg?cropZoom=20,20&classes=big)')
        );
    }

    public function testImageHtmlHelperWithNumericFolder(): void
    {
        self::assertStringContainsString(
            'src="/' . self::ROOT . '/2022/09/photo.jpg"',
            ExcerptsHelper::processImageHtml('<img src="media://2022/09/photo.jpg" alt="a">', $this->page)
        );
    }
}
