<?php

use Codeception\Util\Fixtures;
use Grav\Common\Assets;
use Grav\Common\Grav;

/**
 * Once a pipeline bundle is on disk, later requests reuse it: a linked bundle is only referenced
 * by URL (its file isn't read again), an inline one is read to print it.
 */
class PipelineWarmBundleTest extends \Codeception\Test\Unit
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
    }

    private function js(array $attributes = []): string
    {
        $this->assets->reset();
        $this->assets->setJsPipeline(true);
        $this->assets->addJs('/tests/unit/data/assets/valid.js');

        return $this->assets->js('head', $attributes);
    }

    private function css(array $attributes = []): string
    {
        $this->assets->reset();
        $this->assets->setCssPipeline(true);
        $this->assets->addCss('/tests/unit/data/assets/valid.css');

        return $this->assets->css('head', $attributes);
    }

    public function testLinkedBundlesAreReusedFromDisk(): void
    {
        $cold = $this->js();
        $warm = $this->js();
        self::assertMatchesRegularExpression('#<script src="[^"]*/[a-f0-9]{32}\.js"#', $warm);
        self::assertSame($cold, $warm);

        $cold = $this->css();
        $warm = $this->css();
        self::assertMatchesRegularExpression('#<link href="[^"]*/[a-f0-9]{32}\.css"#', $warm);
        self::assertSame($cold, $warm);
    }

    public function testInlineBundlesPrintTheFileContents(): void
    {
        $this->js(['loading' => 'inline']);
        $warm = $this->js(['loading' => 'inline']);
        self::assertStringContainsString('<script', $warm);
        self::assertStringContainsString("valid", $warm);
        self::assertStringNotContainsString(' src=', $warm);

        $this->css(['loading' => 'inline']);
        $warm = $this->css(['loading' => 'inline']);
        self::assertStringStartsWith('<style>', $warm);
        self::assertStringNotContainsString('<link', $warm);
    }

    public function testAnEmptyBundleOnDiskRendersNothing(): void
    {
        $cold = $this->js();
        preg_match('#/([a-f0-9]{32}\.js)#', $cold, $m);
        $file = $this->grav['locator']->findResource('asset://') . '/' . $m[1];
        $contents = file_get_contents($file);
        file_put_contents($file, '');
        try {
            self::assertSame('', trim($this->js()), 'A zero-byte bundle is treated as empty, as before');
        } finally {
            file_put_contents($file, $contents);
        }
    }
}
