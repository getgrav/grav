<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Common\Page\Media;
use Grav\Common\Page\Medium\GlobalMedia;
use Grav\Common\Page\Medium\ImageMedium;
use Grav\Common\Page\Medium\MediumFactory;
use Grav\Common\Twig\Extension\GravExtension;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

/**
 * Page content takes media by stream URI (a markdown image, `media['...']`,
 * the watermark action's image, media_directory()) only from the media
 * folders in `security.media.allowed_roots`. A stream such as `user://` has
 * a root that also holds accounts, configuration and site data, so a file
 * inside that stream's root is not media just because the stream resolves it.
 *
 * `mediatest://` stands in for `user://`: its root holds a `media/` folder,
 * which is listed as a media root, and a `private/` folder, which is not.
 */
class MediaStreamRootsTest extends \Codeception\Test\Unit
{
    /** @var Grav */
    protected $grav;

    /** @var string */
    protected $dir;

    /** @var mixed */
    protected $roots;

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $this->grav = $grav();

        $this->dir = sys_get_temp_dir() . '/grav-media-roots-' . uniqid('', true);
        mkdir($this->dir . '/media', 0777, true);
        mkdir($this->dir . '/private', 0777, true);
        $this->png($this->dir . '/source.png', 200, 100, [255, 255, 255]);
        $this->png($this->dir . '/media/stamp.png', 20, 10, [255, 0, 0]);
        $this->png($this->dir . '/private/stamp.png', 20, 10, [255, 0, 0]);

        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];
        $locator->resetScheme('mediatest');
        $locator->addPath('mediatest', '', $this->dir);
        $locator->resetScheme('mediatestroot');
        $locator->addPath('mediatestroot', '', $this->dir . '/media');

        $config = $this->grav['config'];
        $this->roots = $config->get('security.media.allowed_roots');
        $config->set('security.media.allowed_roots', ['mediatestroot://']);
    }

    protected function tearDown(): void
    {
        $this->grav['config']->set('security.media.allowed_roots', $this->roots);
        foreach (['media/stamp.png', 'private/stamp.png', 'source.png'] as $file) {
            @unlink($this->dir . '/' . $file);
        }
        @rmdir($this->dir . '/media');
        @rmdir($this->dir . '/private');
        @rmdir($this->dir);
        parent::tearDown();
    }

    private function png(string $path, int $width, int $height, array $rgb): void
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, ...$rgb));
        imagepng($image, $path);
    }

    private function watermarked(string $stamp): bool
    {
        $medium = MediumFactory::fromFile($this->dir . '/source.png');
        $this->assertInstanceOf(ImageMedium::class, $medium);
        $image = imagecreatefromstring(file_get_contents($medium->watermark($stamp, 'center', 10)->path(false)));
        $color = imagecolorat($image, 100, 50);

        return ($color >> 16 & 255) > 200 && ($color >> 8 & 255) < 80;
    }

    public function testWatermarkTakesAnImageFromAMediaFolder(): void
    {
        $this->assertTrue($this->watermarked('mediatest://media/stamp.png'));
        $this->assertTrue($this->watermarked('mediatestroot://stamp.png'));
    }

    public function testWatermarkIgnoresAnImageOutsideTheMediaFolders(): void
    {
        $this->assertFalse($this->watermarked('mediatest://private/stamp.png'));
        $this->assertFalse($this->watermarked('mediatest://media/../private/stamp.png'));
    }

    /**
     * A plain path is resolved against the install root, so it reaches every
     * folder in the install, including the ones no stream maps to media.
     */
    public function testWatermarkIgnoresAPlainPath(): void
    {
        $name = 'tmp/' . basename($this->dir) . '.png';
        $this->png(GRAV_ROOT . '/' . $name, 20, 10, [255, 0, 0]);

        try {
            $this->assertFalse($this->watermarked($name));
            $this->assertFalse($this->watermarked('tmp://' . basename($name)));
            $this->assertFalse($this->watermarked($this->dir . '/media/stamp.png'));
        } finally {
            unlink(GRAV_ROOT . '/' . $name);
        }
    }

    public function testStreamMediaComesOnlyFromAMediaFolder(): void
    {
        $media = GlobalMedia::getInstance();

        $this->assertNotNull($media['mediatest://media/stamp.png']);
        $this->assertNull($media['mediatest://private/stamp.png']);
        $this->assertFalse(isset($media['mediatest://private/stamp.png']));
    }

    public function testMediaDirectoryListsOnlyAMediaFolder(): void
    {
        $extension = new GravExtension();

        $this->assertInstanceOf(Media::class, $extension->mediaDirFunc('mediatest://media'));
        $this->assertNull($extension->mediaDirFunc('mediatest://private'));
        $this->assertNull($extension->mediaDirFunc('mediatest://'));
    }

    public function testDefaultMediaFoldersLeaveOutSiteDataAndConfiguration(): void
    {
        $this->grav['config']->set('security.media.allowed_roots', null);

        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];
        foreach (['user://', 'user://data', 'user://config', 'user://accounts', 'tmp://', 'cache://'] as $uri) {
            $path = $locator->findResource($uri, true, true);
            $this->assertDirectoryExists($path, $uri);
            $this->assertFalse(GlobalMedia::isMediaPath($path), $uri);
        }
        foreach (['user://pages', 'user://themes', 'user://plugins', 'page://', 'system://images'] as $uri) {
            $path = $locator->findResource($uri, true, true);
            $this->assertDirectoryExists($path, $uri);
            $this->assertTrue(GlobalMedia::isMediaPath($path), $uri);
        }
    }
}
