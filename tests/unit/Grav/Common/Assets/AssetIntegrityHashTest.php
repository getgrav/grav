<?php

use Codeception\Util\Fixtures;
use Grav\Common\Assets;
use Grav\Common\Assets\BaseAsset;
use Grav\Common\Grav;

/**
 * With `system.absolute_urls` on, asset URLs carry the scheme and host. The SRI lookup only
 * stripped the relative root from them, so it went looking for the file at
 * GRAV_WEBROOT . 'https://host/...': no integrity attribute was rendered, and under
 * open_basedir the is_file() call on that path was a fatal error (#4377).
 */
class AssetIntegrityHashTest extends \Codeception\Test\Unit
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

        $config = $this->grav['config'];
        $config->set('system.assets.enable_asset_sri', true);
        $config->set('system.assets.enable_asset_timestamp', false);
    }

    protected function tearDown(): void
    {
        $config = $this->grav['config'];
        $config->set('system.absolute_urls', false);
        $config->set('system.assets.enable_asset_sri', false);
        $this->assets->reset();
    }

    public static function urlProvider(): array
    {
        return [
            'root, relative urls' => ['http://localhost/', '', false],
            'root, absolute urls' => ['http://localhost/', '', true],
            'subfolder, relative urls' => ['https://example.com/grav/', '/grav', false],
            'subfolder, absolute urls' => ['https://example.com/grav/', '/grav', true],
            'subfolder with regex chars, relative urls' => ['https://example.com/my.site+1/', '/my.site+1', false],
            'subfolder with regex chars, absolute urls' => ['https://example.com/my.site+1/', '/my.site+1', true],
        ];
    }

    private function useUrl(string $url, string $rootPath, bool $absolute): void
    {
        $this->grav['config']->set('system.absolute_urls', $absolute);
        $this->grav['uri']->initializeWithUrlAndRootPath($url, $rootPath)->init();
    }

    private static function sri(string $file): string
    {
        return ' integrity="sha256-' . base64_encode(hash('sha256', (string) file_get_contents($file), true)) . '"';
    }

    /**
     * @dataProvider urlProvider
     */
    public function testIntegrityHashForLocalUrl(string $url, string $rootPath, bool $absolute): void
    {
        $this->useUrl($url, $rootPath, $absolute);

        $link = $this->grav['uri']->rootUrl($absolute) . '/tests/unit/data/assets/valid.css';

        self::assertSame(self::sri(GRAV_WEBROOT . '/tests/unit/data/assets/valid.css'), BaseAsset::integrityHash($link));
    }

    /**
     * @dataProvider urlProvider
     */
    public function testUnpipelinedAssetsRenderIntegrity(string $url, string $rootPath, bool $absolute): void
    {
        $this->useUrl($url, $rootPath, $absolute);

        $this->assets->addCss('/tests/unit/data/assets/valid.css');
        $this->assets->addJs('/tests/unit/data/assets/valid.js');

        $css = $this->assets->css();
        $js = $this->assets->js();

        $base = $this->grav['uri']->rootUrl($absolute);
        self::assertStringContainsString('href="' . $base . '/tests/unit/data/assets/valid.css"', $css);
        self::assertStringContainsString(self::sri(GRAV_WEBROOT . '/tests/unit/data/assets/valid.css'), $css);
        self::assertStringContainsString('src="' . $base . '/tests/unit/data/assets/valid.js"', $js);
        self::assertStringContainsString(self::sri(GRAV_WEBROOT . '/tests/unit/data/assets/valid.js'), $js);
    }

    /**
     * @dataProvider urlProvider
     */
    public function testPipelinedAssetsRenderIntegrity(string $url, string $rootPath, bool $absolute): void
    {
        $this->useUrl($url, $rootPath, $absolute);

        $this->assets->setCssPipeline(true);
        $this->assets->setJsPipeline(true);
        $this->assets->addCss('/tests/unit/data/assets/valid.css');
        $this->assets->addJs('/tests/unit/data/assets/valid.js');

        $assetsDir = $this->grav['locator']->findResource('asset://');
        $base = preg_quote($this->grav['uri']->rootUrl($absolute), '#');

        foreach (['css' => $this->assets->css(), 'js' => $this->assets->js()] as $ext => $html) {
            self::assertMatchesRegularExpression('#(?:href|src)="' . $base . '/assets/([a-f0-9]{32})\.' . $ext . '"#', $html);
            preg_match('#/assets/([a-f0-9]{32})\.' . $ext . '"#', $html, $m);
            self::assertStringContainsString(self::sri($assetsDir . '/' . $m[1] . '.' . $ext), $html);
        }
    }
}
