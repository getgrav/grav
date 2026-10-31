<?php

use Codeception\Util\Fixtures;
use Grav\Common\Assets;
use Grav\Common\Assets\Pipeline;
use Grav\Common\Grav;

/**
 * A pipeline bundle does not depend on the name the site is requested by: with
 * `system.absolute_urls` on, the scheme, host and port of the request are left
 * out of the bundle's name and content, so every host shares one bundle.
 */
class PipelineHostTest extends \Codeception\Test\Unit
{
    /** @var Grav */
    protected $grav;

    /** @var array */
    private $settings;

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $this->grav = $grav();

        $config = $this->grav['config'];
        $this->settings = [
            'system.absolute_urls' => $config->get('system.absolute_urls'),
            'system.assets.enable_asset_timestamp' => $config->get('system.assets.enable_asset_timestamp'),
        ];
        $config->set('system.absolute_urls', true);

        // A bundle is reused by name: start from an empty pipeline cache, as AssetsTest does.
        $this->removeBundles();
    }

    protected function tearDown(): void
    {
        $this->removeBundles();
        foreach ($this->settings as $name => $value) {
            $this->grav['config']->set($name, $value);
        }
    }

    private function bundleFiles(): array
    {
        return glob($this->grav['locator']->findResource('asset://') . '/*.{css,js}', GLOB_BRACE) ?: [];
    }

    private function removeBundles(): void
    {
        foreach ($this->bundleFiles() as $file) {
            @unlink($file);
        }
    }

    private function request(string $url): Assets
    {
        $this->grav['uri']->initializeWithUrl($url)->init();
        $this->grav['cache']->init($this->grav);

        /** @var Assets $assets */
        $assets = $this->grav['assets'];
        $assets->reset();

        return $assets;
    }

    public function testCssBundleIsSharedByAllHosts(): void
    {
        $names = [];
        foreach (['http://a.example', 'http://a.example:8080', 'https://b.example'] as $root) {
            $assets = $this->request($root . '/');
            $assets->setCssPipeline(true);
            $assets->addCss('/tests/unit/data/assets/minify-modern.css');
            $css = $assets->css();

            self::assertMatchesRegularExpression('#<link href="' . preg_quote($root, '#') . '/assets/([a-f0-9]{32}\.css)"#', $css);
            preg_match('#/assets/([a-f0-9]{32}\.css)#', $css, $matches);
            $names[] = $matches[1];
        }

        self::assertCount(1, array_unique($names));
        self::assertCount(1, $this->bundleFiles());

        // The host name of the first request is not part of the shared copy.
        $bundle = file_get_contents($this->grav['locator']->findResource('asset://' . $names[0]));
        self::assertStringContainsString('url(/tests/unit/data/images/dot.png)', $bundle);
        self::assertStringNotContainsString('a.example', $bundle);

        // The name is the one the same assets get with absolute URLs off.
        $this->grav['config']->set('system.absolute_urls', false);
        $assets = $this->request('http://c.example/');
        $assets->setCssPipeline(true);
        $assets->addCss('/tests/unit/data/assets/minify-modern.css');
        self::assertStringContainsString('<link href="/assets/' . $names[0] . '"', $assets->css());
        self::assertCount(1, $this->bundleFiles());
    }

    public function testJsBundleIsSharedByAllHosts(): void
    {
        $names = [];
        foreach (['http://a.example', 'http://a.example:8080', 'https://b.example'] as $root) {
            $assets = $this->request($root . '/');
            $assets->setJsPipeline(true);
            $assets->addJs('/tests/unit/data/assets/valid.js');
            $js = $assets->js();

            self::assertMatchesRegularExpression('#<script src="' . preg_quote($root, '#') . '/assets/([a-f0-9]{32}\.js)"#', $js);
            preg_match('#/assets/([a-f0-9]{32}\.js)#', $js, $matches);
            $names[] = $matches[1];
        }

        self::assertCount(1, array_unique($names));
        self::assertCount(1, $this->bundleFiles());
    }

    public function testGlobalCacheKeyOfRemoteAssetIsNotPartOfTheName(): void
    {
        $this->grav['config']->set('system.absolute_urls', false);
        $this->grav['config']->set('system.assets.enable_asset_timestamp', true);

        // The bundle is only stored once the remote file has been downloaded, which needs a network:
        // check what the uid is computed from instead.
        $serialize = new \ReflectionMethod(Pipeline::class, 'serializeAssets');
        $serialize->setAccessible(true);

        $keys = [];
        $serialized = [];
        foreach (['http://a.example/', 'http://b.example/'] as $url) {
            $assets = $this->request($url);
            $assets->config(['enable_asset_timestamp' => true]);
            $assets->addCss('https://cdn.example.com/remote.css');

            $keys[] = $this->grav['cache']->getKey();
            $serialized[] = $serialize->invoke(new Pipeline([]), $assets->getCss());
        }

        self::assertNotSame($keys[0], $keys[1]);
        self::assertSame($serialized[0], $serialized[1]);

        // A token set by hand is the same for every host and still gives a bundle of its own.
        $assets = $this->request('http://a.example/');
        $assets->setTimestamp('release-1');
        $assets->addCss('https://cdn.example.com/remote.css');
        self::assertStringContainsString('release-1', $serialize->invoke(new Pipeline([]), $assets->getCss()));
    }
}
