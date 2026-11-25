<?php

use Codeception\Util\Fixtures;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use Grav\Common\Page\Media;
use Grav\Common\Page\Medium\ImageMedium;

/**
 * measures that ratio from the widths of the two files, not from the `@2x`
 * in the name, and `team@2x.png` can be an upload (the Form plugin stores a
     */
class MediaAlternativeRatioSecurityTest extends \Codeception\Test\Unit
{
    /** @var Grav */
    protected $grav;

    /** @var string */
    protected $dir;

    /** @var array */
    protected $images;

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $this->grav = $grav();

        $this->dir = sys_get_temp_dir() . '/grav-alternative-ratio-' . uniqid('', true);
        Folder::create($this->dir);

        $config = $this->grav['config'];
        $this->images = $config->get('system.images');
        // A hundredth of the default, to keep the pictures small.
        $config->set('system.images.max_pixels', 250000);
    }

    protected function tearDown(): void
    {
        $this->grav['config']->set('system.images', $this->images);
        Folder::delete($this->dir);
        parent::tearDown();
    }

    private function png(string $name, int $width, int $height): void
    {
        $image = imagecreatetruecolor($width, $height);
        imagepng($image, $this->dir . '/' . $name);
    }

    private function medium(string $name): ImageMedium
    {
        $medium = (new Media($this->dir))[$name];
        self::assertInstanceOf(ImageMedium::class, $medium);

        return $medium;
    }

    /**
     * @return array<int, string>
     */
    private function rendered(ImageMedium $medium): array
    {
        $rendered = [];
        foreach (array_filter(explode(', ', $medium->srcset())) as $candidate) {
            [$url, $descriptor] = explode(' ', $candidate);
            $size = getimagesize(GRAV_ROOT . substr($url, strpos($url, '/images/')));
            $rendered[(int) $descriptor] = $size[0] . 'x' . $size[1];
        }
        ksort($rendered);

        return $rendered;
    }

    public function testProportionalAlternativeIsScaled(): void
    {
        $this->png('team.png', 40, 30);
        $this->png('team@2x.png', 80, 60);

        self::assertSame([40 => '30x30', 80 => '60x60'], $this->rendered($this->medium('team.png')->cropZoom(30, 30)));
        // The arguments of a Markdown image are strings.
        self::assertSame([40 => '30x30', 80 => '60x60'], $this->rendered($this->medium('team.png')->forceResize('30', '30')));
        // A ratio that is not whole.
        $this->png('team@3x.png', 100, 75);
        self::assertSame([40 => '30x30', 80 => '60x60', 100 => '75x75'], $this->rendered($this->medium('team.png')->cropZoom(30, 30)));
    }

    /**
     * @dataProvider providerEnlargingActions
     */
    public function testAlternativeAboveTheLimitIsLeftOut(string $action, array $args): void
    {
        $this->png('team.png', 40, 30);
        $this->png('team@2x.png', 80, 60);
        // Within the limit itself, 25 times as wide as team.png: 30 becomes 750.
        $this->png('team@3x.png', 1000, 250);

        $rendered = $this->rendered($this->medium('team.png')->{$action}(...$args));

        self::assertSame([40 => '30x30', 80 => '60x60'], $rendered);
    }

    public static function providerEnlargingActions(): array
    {
        return [
            'cropZoom' => ['cropZoom', [30, 30]],
            'zoomCrop' => ['zoomCrop', [30, 30]],
            'forceResize' => ['forceResize', [30, 30]],
            'crop' => ['crop', [0, 0, 30, 30]],
        ];
    }

    public function testActionsWhichNeverEnlargeKeepTheAlternative(): void
    {
        $this->png('team.png', 40, 30);
        $this->png('team@2x.png', 1000, 250);

        self::assertSame([40 => '30x23', 1000 => '750x188'], $this->rendered($this->medium('team.png')->cropResize(30, 30)));
        // A plain resize pads to the box, but no further than the image's own size.
        self::assertSame([40 => '30x30', 1000 => '750x250'], $this->rendered($this->medium('team.png')->resize(30, 30)));
    }

    public function testSizeIsFollowedThroughAChainOfActions(): void
    {
        $this->png('team.png', 40, 40);
        $this->png('team@2x.png', 80, 80);
        // 10 times as wide. 1x30 becomes 10x300, and 300 wide that is 300x9000,
        // where the 400x400 of the file would give 300x300.
        $this->png('team@3x.png', 400, 400);

        $rendered = $this->rendered($this->medium('team.png')->cropZoom(1, 30)->forceResize(30));

        self::assertSame([40 => '30x900', 80 => '60x1800'], $rendered);
    }

    /**
     * @dataProvider providerSubPixelActions
     */
    public function testAlternativeScaledBelowOnePixelIsLeftOut(string $action, array $args): void
    {
        $this->png('team.png', 40, 30);
        $this->png('team@2x.png', 80, 60);
        // A 40th: 30 becomes 0.75.
        $this->png('team@3x.png', 1, 1);

        $rendered = $this->rendered($this->medium('team.png')->{$action}(...$args));

        self::assertArrayNotHasKey(1, $rendered);
        self::assertSame('60x60', $rendered[80]);
    }

    public static function providerSubPixelActions(): array
    {
        return ['resize' => ['resize', [30, 30, 'transparent', true]]] + self::providerEnlargingActions();
    }

    public function testPercentageIsNotScaled(): void
    {
        $this->png('team.png', 40, 30);
        $this->png('team@2x.png', 80, 60);

        // Half of each file, not "50%" times the ratio.
        self::assertSame([40 => '20x15', 80 => '40x30'], $this->rendered($this->medium('team.png')->resize('50%')));
        self::assertSame([40 => '5x4', 80 => '10x8'], $this->rendered($this->medium('team.png')->cropResize('12.5%')));
        self::assertSame([40 => '160x120', 80 => '320x240'], $this->rendered($this->medium('team.png')->forceResize('400%')));
        // Above the limit for the alternative only.
        $this->png('team@2x.png', 400, 300);
        self::assertSame([], $this->rendered($this->medium('team.png')->forceResize('400%')));
    }

    /**
     * @dataProvider providerUnusableArguments
     */
    public function testAlternativeIsLeftOutWhenAnArgumentIsNotASize(string $action, array $args): void
    {
        $this->png('team.png', 40, 30);
        $this->png('team@2x.png', 80, 60);

        // `team.png?resize=` and the like are refused when the image is saved.
        // They must not fail while the action is passed on to team@2x.png.
        self::assertSame([], $this->rendered($this->medium('team.png')->{$action}(...$args)));

        // Nor when there is no limit to measure the alternative against.
        $this->grav['config']->set('system.images.max_pixels', 0);
        self::assertInstanceOf(ImageMedium::class, $this->medium('team.png')->{$action}(...$args));
    }

    public static function providerUnusableArguments(): array
    {
        return [
            'empty' => ['resize', ['']],
            'word' => ['resize', ['abc']],
            'percentage and height' => ['resize', ['50%', '20']],
            'word as height' => ['cropResize', ['20', 'x']],
            'list' => ['resize', [['1', '2']]],
            'percentages to zoomCrop' => ['zoomCrop', ['50%', '50%']],
        ];
    }

    public function testNoLimit(): void
    {
        $this->grav['config']->set('system.images.max_pixels', 0);
        $this->png('team.png', 40, 30);
        $this->png('team@2x.png', 1000, 250);

        self::assertSame([40 => '30x30', 1000 => '750x750'], $this->rendered($this->medium('team.png')->cropZoom(30, 30)));
    }
    public function testFourTimesAlternativeStaysWithinTheCanvasLimit(): void
    {
        $this->grav['config']->set('system.images.max_pixels', 25000000);
        $this->png('team.png', 40, 40);
        $this->png('team@4x.png', 160, 160);

        $medium = $this->medium('team.png')->cropZoom(2000, 2000);

        self::assertSame([40, 80], array_keys($medium->getAlternatives()));
    }
}
