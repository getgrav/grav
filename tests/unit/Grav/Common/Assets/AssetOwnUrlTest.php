<?php

use Codeception\Util\Fixtures;
use Grav\Common\Assets;
use Grav\Common\Grav;

/**
 * An asset added by a URL of the site itself, such as url('theme://x.css', true) or a link
 * that starts with the subfolder, was treated as a path under the web root, so the rendered
 * link had the host or the subfolder twice.
 */
class AssetOwnUrlTest extends \Codeception\Test\Unit
{
    /** @var Grav */
    protected $grav;

    /** @var Assets */
    protected $assets;

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $this->grav = $grav();
        $this->assets = $this->grav['assets'];
        $this->assets->reset();
        $this->grav['config']->set('system.assets.enable_asset_timestamp', false);
    }

    protected function tearDown(): void
    {
        $this->grav['config']->set('system.absolute_urls', false);
        $this->grav['uri']->initializeWithUrlAndRootPath('http://localhost/', '')->init();
        $this->assets->reset();
    }

    public static function urlProvider(): array
    {
        return [
            'root, relative urls' => ['http://localhost/', '', false],
            'root, absolute urls' => ['http://localhost/', '', true],
            'subfolder, relative urls' => ['https://example.com/grav/', '/grav', false],
            'subfolder, absolute urls' => ['https://example.com/grav/', '/grav', true],
        ];
    }

    /**
     * @dataProvider urlProvider
     */
    public function testAssetAddedByOwnUrl(string $url, string $rootPath, bool $absolute): void
    {
        $this->grav['config']->set('system.absolute_urls', $absolute);
        $uri = $this->grav['uri'];
        $uri->initializeWithUrlAndRootPath($url, $rootPath)->init();

        $base = $uri->rootUrl($absolute);
        $this->assets->addCss($uri->rootUrl(true) . '/tests/unit/data/assets/valid.css');
        $this->assets->addJs($uri->rootUrl() . '/tests/unit/data/assets/valid.js');

        self::assertStringContainsString('href="' . $base . '/tests/unit/data/assets/valid.css"', $this->assets->css());
        self::assertStringContainsString('src="' . $base . '/tests/unit/data/assets/valid.js"', $this->assets->js());
    }
}
