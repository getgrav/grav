<?php

use Codeception\Util\Fixtures;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use Grav\Common\Page\Media;


class ImageMediumOutputFormatTest extends \Codeception\Test\Unit
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

        $this->dir = sys_get_temp_dir() . '/grav-output-format-' . uniqid('', true);
        Folder::create($this->dir);
    }

    protected function tearDown(): void
    {
        Folder::delete($this->dir);
        parent::tearDown();
    }

    private function jpeg(string $name): void
    {
        $image = imagecreatetruecolor(40, 30);
        imagejpeg($image, $this->dir . '/' . $name);
        imagedestroy($image);
    }


    public function testExtensionThatIsNotAnOutputTypeIsSavedAsItsContent(): void
    {
        $this->jpeg('photo.jpe');

        $path = (new Media($this->dir))['photo.jpe']->resize(20, 15)->path();

        self::assertStringEndsWith('.jpeg', $path);
        self::assertSame([20, 15, IMAGETYPE_JPEG], array_slice(getimagesize($path), 0, 3));
    }

    public function testRetinaImageWithSuchAnExtensionLoads(): void
    {
        $this->jpeg('selfie@2x.jpe');

        $media = new Media($this->dir);

        self::assertSame(['selfie.jpe'], array_keys($media->all()));
        self::assertSame(20, (int) $media['selfie.jpe']->get('width'));
    }


    public function testRetinaImageInAFormatTheAdapterMayNotWriteLoads(): void
    {
        $this->jpeg('selfie@2x.avif');

        self::assertSame(['selfie.avif'], array_keys((new Media($this->dir))->all()));
    }
    public function testCollectionKeepsOtherFilesWhenAMediumCannotBeCreated(): void
    {
        $this->jpeg('ok.jpg');
        $this->jpeg('unreadable.jpg');
        $media = new class($this->dir) extends Media {
            public function createFromFile($file, array $params = [])
            {
                if (basename($file) === 'unreadable.jpg') {
                    throw new \RuntimeException('Cannot read this medium');
                }

                return parent::createFromFile($file, $params);
            }
        };

        self::assertSame(['ok.jpg'], array_keys($media->all()));
    }
}
