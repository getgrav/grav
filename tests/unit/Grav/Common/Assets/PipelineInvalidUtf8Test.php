<?php

use Codeception\Util\Fixtures;
use Grav\Common\Assets;
use Grav\Common\Grav;

/**
 * A pipeline bundle is named after the list of its assets. json_encode() cannot encode a string
 * that is not UTF-8: lists holding one must not all end up with the same name, or each of them is
 * served the bundle of whichever list was built first.
 */
class PipelineInvalidUtf8Test extends \Codeception\Test\Unit
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

        // A bundle is reused by name: start from an empty pipeline cache, as AssetsTest does.
        $this->removeBundles();
    }

    protected function tearDown(): void
    {
        $this->removeBundles();
    }

    private function removeBundles(): void
    {
        $files = glob($this->grav['locator']->findResource('asset://') . '/*.{css,js}', GLOB_BRACE) ?: [];
        foreach ($files as $file) {
            @unlink($file);
        }
    }

    /**
     * Bundle one asset with a byte that is not UTF-8 in its query string and in an attribute.
     *
     * @return string File name of the bundle the group is rendered with.
     */
    private function bundle(string $type, string $asset, string $byte = "\xff"): string
    {
        $this->assets->reset();
        if ($type === 'js') {
            $this->assets->setJsPipeline(true);
            $this->assets->addJs($asset . '?key=' . $byte, ['data-key' => $byte]);
            $html = $this->assets->js();
        } else {
            $this->assets->setCssPipeline(true);
            $this->assets->addCss($asset . '?key=' . $byte, ['data-key' => $byte]);
            $html = $this->assets->css();
        }

        self::assertSame(1, preg_match('#/assets/([a-f0-9]{32}\.' . $type . ')"#', $html, $matches), $html);

        return $matches[1];
    }

    private function contents(string $bundle): string
    {
        return (string) file_get_contents($this->grav['locator']->findResource('asset://' . $bundle));
    }

    public function testJsListsWithInvalidUtf8GetBundlesOfTheirOwn(): void
    {
        $first = $this->bundle('js', '/tests/unit/data/assets/valid.js');
        $second = $this->bundle('js', '/tests/unit/data/assets/timestamp-a.js');

        self::assertNotSame($first, $second);
        self::assertStringContainsString('valid', $this->contents($first));
        self::assertStringNotContainsString('valid', $this->contents($second));
        self::assertStringContainsString('timestamp fixture', $this->contents($second));

        // The same list still finds its bundle again.
        self::assertSame($first, $this->bundle('js', '/tests/unit/data/assets/valid.js'));

        // Lists that differ only in the bytes json_encode() rejects are different lists too.
        self::assertNotSame($first, $this->bundle('js', '/tests/unit/data/assets/valid.js', "\xfe"));
    }

    public function testCssListsWithInvalidUtf8GetBundlesOfTheirOwn(): void
    {
        $first = $this->bundle('css', '/tests/unit/data/assets/valid.css');
        $second = $this->bundle('css', '/tests/unit/data/assets/timestamp-a.css');

        self::assertNotSame($first, $second);
        self::assertStringContainsString('.valid', $this->contents($first));
        self::assertStringNotContainsString('.valid', $this->contents($second));
        self::assertStringContainsString('.timestamp-a', $this->contents($second));

        self::assertSame($first, $this->bundle('css', '/tests/unit/data/assets/valid.css'));
        self::assertNotSame($first, $this->bundle('css', '/tests/unit/data/assets/valid.css', "\xfe"));
    }
}
