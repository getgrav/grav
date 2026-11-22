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
        $max = AbstractMedia::MAX_GENERATED_RETINA_SCALE;
        $this->png('team@3x.png', 30, 30);
        $this->png("logo@{$max}x.png", 10 * $max, 10 * $max);

        $media = new Media($this->dir);

        $names = array_keys($media->all());
        sort($names);
        self::assertSame(['logo.png', 'team.png'], $names);
        self::assertSame([10, 20, 30], array_keys($media['team.png']->getAlternatives()));
        self::assertCount($max, $media['logo.png']->getAlternatives());
    }

    /**
     * A full set of retina files up to `@Nx` is one medium, as on 1.7 (admin2#214).
     *
     * @dataProvider retinaSetProvider
     */
    public function testFullRetinaSetIsOneMedium(int $top): void
    {
        $this->png('pic.png', 10, 10);
        for ($i = 2; $i <= $top; $i++) {
            $this->png("pic@{$i}x.png", 10 * $i, 10 * $i);
        }

        $media = new Media($this->dir);

        self::assertSame(['pic.png'], array_keys($media->all()));
        self::assertSame(range(10, 10 * $top, 10), array_keys($media['pic.png']->getAlternatives()));
    }

    public static function retinaSetProvider(): array
    {
        return [
            '5x' => [5],
            '6x' => [6],
            'limit' => [AbstractMedia::MAX_RETINA_SCALE],
        ];
    }

    /**
     * A lone top-scale file is grouped, but missing steps are only rendered up
     * to MAX_GENERATED_RETINA_SCALE, so an upload can't make Grav render more.
     */
    public function testMissingStepsAreOnlyRenderedUpToTheGenerationLimit(): void
    {
        $top = AbstractMedia::MAX_RETINA_SCALE;
        $this->png("pic@{$top}x.png", 10 * $top, 10 * $top);

        $media = new Media($this->dir);
        $expected = array_merge(range(10, 10 * AbstractMedia::MAX_GENERATED_RETINA_SCALE, 10), [10 * $top]);

        self::assertSame(['pic.png'], array_keys($media->all()));
        self::assertSame($expected, array_keys($media['pic.png']->getAlternatives()));

        $medium = (new GlobalMedia())["retinatest://pic@{$top}x.png"];
        self::assertSame($expected, array_keys($medium->getAlternatives()));
    }

    /**
     * A stream URI naming the base image gets the srcset of its `@Nx` files,
     * as the same file does in page media (admin2#222).
     */
    public function testStreamBaseImageGetsItsRetinaFiles(): void
    {
        $this->png('pic.png', 10, 10);
        $this->png('pic@2x.png', 20, 20);
        $this->png('pic@3x.png', 30, 30);

        $medium = (new GlobalMedia())['retinatest://pic.png'];

        self::assertSame($this->dir . '/pic.png', $medium->get('filepath'));
        self::assertSame([10, 20, 30], array_keys($medium->getAlternatives()));
        self::assertStringContainsString('pic@3x.png 30w', $medium->parsedownElement()['attributes']['srcset'] ?? '');
    }

    /**
     * Missing steps below the largest file are rendered as in page media, but
     * only up to MAX_GENERATED_RETINA_SCALE.
     */
    public function testStreamBaseImageRendersMissingStepsUpToTheLimit(): void
    {
        $top = AbstractMedia::MAX_RETINA_SCALE;
        $this->png('pic.png', 10, 10);
        $this->png("pic@{$top}x.png", 10 * $top, 10 * $top);

        $medium = (new GlobalMedia())['retinatest://pic.png'];
        $expected = array_merge(range(10, 10 * AbstractMedia::MAX_GENERATED_RETINA_SCALE, 10), [10 * $top]);

        self::assertSame($expected, array_keys($medium->getAlternatives()));
    }

    public function testStreamBaseImageWithoutRetinaFilesHasNoSrcset(): void
    {
        $this->png('pic.png', 10, 10);
        $this->png('pic@200x.png', 2000, 2000);

        $medium = (new GlobalMedia())['retinatest://pic.png'];

        self::assertSame([10], array_keys($medium->getAlternatives()));
    }

    public function testDeleteKeepsFilesAboveTheLimit(): void
    {
        $above = AbstractMedia::MAX_RETINA_SCALE + 2;
        $this->png('pic.png', 10, 10);
        $this->png('pic@2x.png', 20, 20);
        $this->png("pic@{$above}x.png", 10 * $above, 10 * $above);

        (new Media($this->dir))->deleteFile('pic.png');

        // `pic@12x.png` is listed as a medium of its own, so deleting `pic.png` leaves it.
        self::assertSame(["pic@{$above}x.png"], array_values(array_diff(scandir($this->dir), ['.', '..'])));
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
