<?php

use Codeception\Util\Fixtures;
use Grav\Common\Language\Language;
use Grav\Common\Markdown\Parsedown;
use Grav\Common\Page\Markdown\Excerpts;
use Grav\Common\Uri;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

/**
 * A link or image whose path is longer than any real path, such as an inline image written
 * as `/data:image/png;base64,...`, made Uri::convertUrl() call file_exists() on it. With
 * open_basedir set PHP raises a warning for an over-long path instead of returning false,
 * and Grav turned that warning into an error page. Uri::pathExists() skips the check.
 */
class LongLinkPathTest extends \PHPUnit\Framework\TestCase
{
    /** @var Parsedown */
    protected $parsedown;

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $grav = $grav();
        $config = $grav['config'];
        $config->set('system.home.alias', '/item1');
        $config->set('system.absolute_urls', false);
        $config->set('system.languages.supported', []);

        unset($grav['language']);
        $grav['language'] = new Language($grav);

        /** @var UniformResourceLocator $locator */
        $locator = $grav['locator'];
        $locator->addPath('page', '', 'tests/fake/nested-site/user/pages', false);
        $grav['pages']->init();
        $grav['uri']->initializeWithURL('http://testing.dev/item2/item2-2')->init();

        $defaults = [
            'markdown' => [
                'extra' => false,
                'auto_line_breaks' => false,
                'auto_url_links' => false,
                'escape_markup' => false,
                'special_chars' => ['>' => 'gt', '<' => 'lt'],
            ],
            'images' => $config->get('system.images', []),
        ];
        $page = $grav['pages']->find('/item2/item2-2');
        $this->parsedown = new Parsedown(new Excerpts($page, $defaults));
    }

    private static function pathExists(?string $path): bool
    {
        $method = new \ReflectionMethod(Uri::class, 'pathExists');
        $method->setAccessible(true);

        return $method->invoke(null, $path);
    }

    public function testPathExists(): void
    {
        self::assertTrue(self::pathExists(__FILE__));
        self::assertFalse(self::pathExists(__FILE__ . '.missing'));
        self::assertFalse(self::pathExists(''));
        self::assertFalse(self::pathExists(null));
        // Never reaches file_exists(), which warns about this under open_basedir.
        self::assertFalse(self::pathExists(dirname(__FILE__) . '/' . str_repeat('a', PHP_MAXPATHLEN)));
    }

    public function testInlineImageWrittenAsAbsolutePath(): void
    {
        $data = '/data:image/png;base64,' . str_repeat('iVBORw0KGgo', 600);
        $html = $this->parsedown->text('[![](' . $data . ')](https://example.com/video)');

        self::assertStringContainsString('<img src="' . $data . '"', $html);
    }

    public function testRelativeLinkLongerThanAnyPath(): void
    {
        $html = $this->parsedown->text('[x](' . str_repeat('a', 5000) . ')');

        self::assertStringContainsString('>x</a>', $html);
    }
}
