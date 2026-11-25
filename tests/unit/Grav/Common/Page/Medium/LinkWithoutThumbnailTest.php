<?php

use Codeception\Util\Fixtures;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use Grav\Common\Media\Interfaces\MediaObjectInterface;
use Grav\Common\Page\Media;

/**
 * link() and lightbox() wrap the thumbnail of a medium, and which thumbnail a
 * medium has depends on the files in its folder. An empty image has none of its
 * own, and `webp` names a placeholder (`media/thumb-webp.png`) which is not
 * shipped, so one empty `.webp` uploaded to a page folder made every page which
 * renders that folder with lightbox() answer 500 ("Media has no thumbnail set").
 */
class LinkWithoutThumbnailTest extends \Codeception\Test\Unit
{
    /** @var Grav */
    protected $grav;

    /** @var string */
    protected $folder;

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $this->grav = $grav();

        $this->folder = sys_get_temp_dir() . '/grav-link-without-thumbnail-' . uniqid('', true);
        Folder::create($this->folder);
    }

    protected function tearDown(): void
    {
        Folder::delete($this->folder);
        parent::tearDown();
    }

    private function medium(string $filename): MediaObjectInterface
    {
        return (new Media($this->folder))[$filename];
    }

    /**
     * @param string $expected What the address of the image inside the link has to contain.
     */
    private function assertIsLinked(string $filename, string $expected): void
    {
        $rendered = [
            $this->medium($filename)->lightbox(600, 400)->html(),
            $this->medium($filename)->link()->html(),
        ];

        foreach ($rendered as $html) {
            $this->assertMatchesRegularExpression('#^<a [^>]*href="[^"]*' . preg_quote($filename, '#') . '"#', $html);
            $this->assertMatchesRegularExpression('#<img [^>]*src="[^"]*' . preg_quote($expected, '#') . '#', $html);
        }
    }

    public static function emptyImageProvider(): array
    {
        return [
            'png' => ['a.png'],
            'webp' => ['b.webp'],
        ];
    }

    /**
     * @dataProvider emptyImageProvider
     */
    public function testEmptyImageIsLinkedWithAPlaceholder(string $filename): void
    {
        file_put_contents($this->folder . '/' . $filename, '');

        $this->assertTrue($this->medium($filename)->thumbnailExists('default'));
        $this->assertInstanceOf(MediaObjectInterface::class, $this->medium($filename)->display('thumbnail'));
        $this->assertIsLinked($filename, '/system/images/media/thumb');
    }

    public function testMissingPlaceholderFallsBackToTheGenericOne(): void
    {
        $this->grav['config']->set('media.types.png.thumb', 'media/thumb-not-shipped.png');
        file_put_contents($this->folder . '/a.png', '');

        $this->assertIsLinked('a.png', '/system/images/media/thumb.png"');
    }
}
