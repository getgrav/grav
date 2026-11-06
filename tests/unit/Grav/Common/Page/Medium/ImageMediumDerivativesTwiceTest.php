<?php

use Codeception\Util\Fixtures;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use Grav\Common\Page\Media;
use Grav\Common\Page\Medium\ImageMedium;
use Grav\Common\Page\Medium\MediumFactory;

/**
 * A page's media hands out the same medium every time it is asked for, so a
 * second derivatives() call sees the derivatives of the first one.
 * getgrav/grav#4389.
 */
class ImageMediumDerivativesTwiceTest extends \Codeception\Test\Unit
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

        $this->dir = sys_get_temp_dir() . '/grav-derivatives-twice-' . uniqid('', true);
        Folder::create($this->dir);
    }

    protected function tearDown(): void
    {
        Folder::delete($this->dir);
        parent::tearDown();
    }

    private function jpeg(string $name, int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        $file = $this->dir . '/' . $name;
        imagejpeg($image, $file);

        return $file;
    }

    /**
     * @return array<int, int> generated height by width, read from the files
     */
    private function generated(ImageMedium $medium): array
    {
        $sizes = [];
        foreach ($medium->getAlternatives() as $width => $alternative) {
            if ($alternative === $medium) {
                continue;
            }
            $file = $alternative->path(false);
            if (!is_file($file)) {
                $file = GRAV_ROOT . '/' . ltrim($file, '/');
            }
            [$w, $h] = getimagesize($file);
            $this->assertSame($width, $w);
            $sizes[$w] = $h;
        }
        ksort($sizes);

        return $sizes;
    }

    public function testSecondCallKeepsTheAspectRatio(): void
    {
        $medium = MediumFactory::fromFile($this->jpeg('photo.jpg', 1188, 1113));
        $this->assertInstanceOf(ImageMedium::class, $medium);

        $medium->derivatives(480, 1921, 480);
        $medium->derivatives(400, 1201, 400);

        $this->assertSame([400 => 374, 480 => 449, 800 => 749, 960 => 899], $this->generated($medium));
    }

    public function testSecondCallCountsUpToTheSourceWidth(): void
    {
        $medium = MediumFactory::fromFile($this->jpeg('photo.jpg', 1188, 1113));

        $medium->derivatives(480, 1921, 480);
        $medium->derivatives([1000]);

        $this->assertArrayHasKey(1000, $this->generated($medium));
    }

    /**
     * @dataProvider retinaFolders
     */
    public function testRetinaFileIsStillTheBase(bool $withRegularFile): void
    {
        if ($withRegularFile) {
            $this->jpeg('photo.jpg', 1188, 1113);
        }
        $this->jpeg('photo@2x.jpg', 2376, 2226);

        $medium = (new Media($this->dir))['photo.jpg'];
        $this->assertInstanceOf(ImageMedium::class, $medium);

        $medium->derivatives(480, 1921, 480);
        $medium->derivatives(400, 1201, 400);

        $this->assertSame(
            [400 => 374, 480 => 449, 800 => 749, 960 => 899, 1200 => 1124, 1440 => 1349, 1920 => 1798, 2376 => 2226],
            $this->generated($medium)
        );
    }

    public static function retinaFolders(): array
    {
        return [
            'photo.jpg and photo@2x.jpg' => [true],
            'photo@2x.jpg only' => [false],
        ];
    }
}
