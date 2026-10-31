<?php

use Codeception\Util\Fixtures;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use Grav\Common\Page\Media;
use Grav\Common\Page\Medium\AbstractMedia;
use Grav\Common\Page\Medium\GlobalMedia;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

class MediaRetinaScaleLimitTest extends \Codeception\Test\Unit
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

        $this->dir = sys_get_temp_dir() . '/grav-retina-scale-' . uniqid('', true);
        Folder::create($this->dir);

        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];
        $locator->resetScheme('retinatest');
        $locator->addPath('retinatest', '', $this->dir);

        $config = $this->grav['config'];
        $this->roots = $config->get('security.media.allowed_roots');
        $config->set('security.media.allowed_roots', ['retinatest://']);
    }

    protected function tearDown(): void
    {
        $this->grav['config']->set('security.media.allowed_roots', $this->roots);
        Folder::delete($this->dir);
        parent::tearDown();
    }

    private function png(string $name, int $width, int $height): void
    {
        $image = imagecreatetruecolor($width, $height);
        imagepng($image, $this->dir . '/' . $name);
        imagedestroy($image);
    }

    public function testScaleAboveTheLimitIsARegularFileName(): void
    {
        $this->png('staff@200x.png', 200, 200);

        $media = new Media($this->dir);

        $names = array_keys($media->all());
        sort($names);
        self::assertSame(['staff@200x.png'], $names);
        self::assertSame([200], array_keys($media['staff@200x.png']->getAlternatives()));
        self::assertSame(200, (int) $media['staff@200x.png']->get('width'));
    }

    public function testStreamMediaAboveTheLimitIsARegularFile(): void
    {
        $this->png('staff@200x.png', 200, 200);

        $medium = (new GlobalMedia())['retinatest://staff@200x.png'];

        self::assertNotNull($medium);
        self::assertSame(200, (int) $medium->get('width'));
        self::assertSame([200], array_keys($medium->getAlternatives()));
    }

    public function testRetinaImagesStillGetTheirSteps(): void
    {
        $max = AbstractMedia::MAX_RETINA_SCALE;
        $this->png('team@3x.png', 30, 30);
        $this->png("logo@{$max}x.png", 10 * $max, 10 * $max);

        $media = new Media($this->dir);

        $names = array_keys($media->all());
        sort($names);
        self::assertSame(['logo.png', 'team.png'], $names);
        self::assertSame([10, 20, 30], array_keys($media['team.png']->getAlternatives()));
        self::assertCount($max, $media['logo.png']->getAlternatives());
    }

    public function testScalingNeverReachesZeroPixels(): void
    {
        $this->png('dot@3x.png', 1, 1);
        $this->png('line@4x.png', 100, 1);

        $media = new Media($this->dir);

        self::assertSame(1, (int) $media['dot.png']->get('width'));
        self::assertSame(25, (int) $media['line.png']->get('width'));
        self::assertSame(1, (int) $media['line.png']->get('height'));
    }
    public function testVeryLargeScaleIsARegularFileName(): void
    {
        $media = new class($this->dir, null, false) extends Media {
            public function fileParts(string $filename): array
            {
                return $this->getFileParts($filename);
            }
        };

        self::assertSame(['staff@99999999999999999999x', 'png', 'base', null], $media->fileParts('staff@99999999999999999999x.png'));
    }
}
