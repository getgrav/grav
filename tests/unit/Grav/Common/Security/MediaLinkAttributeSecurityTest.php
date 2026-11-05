<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Common\Page\Medium\MediumFactory;

/**
 * `link()` takes a dictionary of anchor attributes and is a sandbox-allowed
 * media action, so page-content Twig can call it. Its attribute names go
 * through the same check as `attribute()` (GHSA-r7fx-8g49-7hhr): event
 * handlers and script-context names are dropped, ordinary names are kept.
 */
class MediaLinkAttributeSecurityTest extends \Codeception\Test\Unit
{
    /** @var Grav */
    protected $grav;

    /** @var string */
    protected $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $this->grav = $grav();

        $this->dir = sys_get_temp_dir() . '/grav-link-attrs-' . uniqid('', true);
        mkdir($this->dir);
        $image = imagecreatetruecolor(8, 8);
        imagepng($image, $this->dir . '/x.png');
        file_put_contents($this->dir . '/doc.txt', 'text');
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir . '/*'));
        rmdir($this->dir);
        parent::tearDown();
    }

    public static function unsafeNames(): array
    {
        return [
            'onfocus' => ['onfocus'],
            'onclick' => ['onclick'],
            'mixed case handler' => ['OnMouseOver'],
            'style' => ['style'],
            'srcdoc' => ['srcdoc'],
            'formaction' => ['formaction'],
            'quote in name' => ['x" onfocus="y'],
            'space in name' => ['a b'],
            'trailing newline' => ["style\n"],
        ];
    }

    /**
     * @dataProvider unsafeNames
     */
    public function testImageLinkDropsUnsafeNames(string $name): void
    {
        $html = MediumFactory::fromFile($this->dir . '/x.png')
            ->link(true, [$name => 'payload()', 'autofocus' => '', 'rel' => 'lightbox'])
            ->html();

        self::assertStringNotContainsString('payload()', $html);
        self::assertStringContainsString('rel="lightbox"', $html);
        self::assertStringContainsString('autofocus=""', $html);
    }

    /**
     * @dataProvider unsafeNames
     */
    public function testFileLinkDropsUnsafeNames(string $name): void
    {
        $html = MediumFactory::fromFile($this->dir . '/doc.txt')
            ->link(true, [$name => 'payload()', 'target' => '_blank'])
            ->html();

        self::assertStringNotContainsString('payload()', $html);
        self::assertStringContainsString('target="_blank"', $html);
    }

    public function testLightboxKeepsItsAttributes(): void
    {
        $html = MediumFactory::fromFile($this->dir . '/x.png')->lightbox(4, 4)->html();

        self::assertStringContainsString('rel="lightbox"', $html);
        self::assertStringContainsString('data-width="4"', $html);
        self::assertStringContainsString('data-height="4"', $html);
    }

    public function testSafeNamesAndValuesAreKept(): void
    {
        $html = MediumFactory::fromFile($this->dir . '/x.png')
            ->link(true, ['class' => 'zoom', 'data-gallery' => 'g1', 'aria-label' => 'Open', 'target' => '_blank'])
            ->html();

        self::assertStringContainsString('class="zoom"', $html);
        self::assertStringContainsString('data-gallery="g1"', $html);
        self::assertStringContainsString('aria-label="Open"', $html);
        self::assertStringContainsString('target="_blank"', $html);
    }
}
