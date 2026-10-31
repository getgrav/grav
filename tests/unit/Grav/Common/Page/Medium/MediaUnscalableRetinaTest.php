<?php

use Codeception\Util\Fixtures;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use Grav\Common\Page\Media;
use Grav\Common\Page\Medium\GlobalMedia;
use Grav\Common\Page\Medium\MediumFactory;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

/**
 * SVG with an `@3x` file has to load without the missing `@2x` step instead of
     */
class MediaUnscalableRetinaTest extends \Codeception\Test\Unit
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

        $this->dir = sys_get_temp_dir() . '/grav-unscalable-retina-' . uniqid('', true);
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

    private function image(string $name, int $size): void
    {
        $image = imagecreatetruecolor($size, $size);
        str_ends_with($name, '.gif') ? imagegif($image, $this->dir . '/' . $name) : imagepng($image, $this->dir . '/' . $name);
        imagedestroy($image);
    }

    public function testGifWithAGapInItsRetinaFilesLoads(): void
    {
        $this->image('dot.gif', 10);
        $this->image('dot@3x.gif', 30);
        $this->image('team.png', 10);

        $media = new Media($this->dir);

        $names = array_keys($media->all());
        sort($names);
        self::assertSame(['dot.gif', 'team.png'], $names);
    }

    public function testSvgWithAGapInItsRetinaFilesLoads(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="%1$d" height="%1$d"/>';
        file_put_contents($this->dir . '/logo.svg', sprintf($svg, 10));
        file_put_contents($this->dir . '/logo@4x.svg', sprintf($svg, 40));

        $media = new Media($this->dir);

        self::assertSame(['logo.svg'], array_keys($media->all()));
    }

    public function testStreamRetinaImageKeepsTheOriginalAsAlternative(): void
    {
        $this->image('team@3x.png', 30);

        $medium = (new GlobalMedia())['retinatest://team@3x.png'];

        self::assertNotNull($medium);
        self::assertSame(10, (int) $medium->get('width'));
        self::assertSame([10, 20, 30], array_keys($medium->getAlternatives()));
    }

    public function testStreamRetinaGifIsNotMedia(): void
    {
        $this->image('dot@3x.gif', 30);

        self::assertNull((new GlobalMedia())['retinatest://dot@3x.gif']);
    }

    public function testScaledFromMediumReturnsAnEmptyPairForAnUnscalableMedium(): void
    {
        $this->image('dot@3x.gif', 30);

        $scaled = MediumFactory::scaledFromMedium(MediumFactory::fromFile($this->dir . '/dot@3x.gif'), 3, 2);

        self::assertSame(['file' => null, 'size' => 0], $scaled);
    }
}
