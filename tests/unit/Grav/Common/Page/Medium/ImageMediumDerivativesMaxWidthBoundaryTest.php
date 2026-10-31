<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Common\Page\Medium\ImageMedium;
use Grav\Common\Page\Medium\MediumFactory;

class ImageMediumDerivativesMaxWidthBoundaryTest extends \Codeception\Test\Unit
{
    /** @var Grav */
    protected $grav;

    /** @var string */
    protected $image = 'tests/fake/nested-site/user/pages/01.item1/home-sample-image.jpg';

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $this->grav = $grav();
    }

    private function medium(): ImageMedium
    {
        $medium = MediumFactory::fromFile(GRAV_ROOT . '/' . $this->image);
        $this->assertInstanceOf(ImageMedium::class, $medium);

        return $medium;
    }

    private function widths(ImageMedium $medium): array
    {
        $srcset = $medium->srcset(false);
        $this->assertNotSame('', $srcset);

        $widths = [];
        foreach (explode(', ', $srcset) as $candidate) {
            [, $descriptor] = explode(' ', $candidate);
            $widths[] = $descriptor;
        }

        return $widths;
    }

    public function testMaximumWidthIsIncludedWithoutDuplicatingTheBaseWidth(): void
    {
        $widths = $this->widths($this->medium()->derivatives(200, 1000, 200));
        $this->assertContains('1000w', $widths);
        $this->assertSame(['200w', '400w', '600w', '800w', '1000w', '1024w'], $widths);
        $this->assertCount(1, array_keys($widths, '1024w', true));

        foreach ([1024, 1280] as $maximum) {
            $widths = $this->widths($this->medium()->derivatives(256, $maximum, 256));
            $this->assertSame(['256w', '512w', '768w', '1024w'], $widths);
            $this->assertCount(1, array_keys($widths, '1024w', true));
        }
    }

    /**
     * `![](image.jpg?derivatives=1024,1024,0)`: the arguments arrive as strings,
     * and a range that cannot be counted adds no derivatives.
     *
     * @dataProvider rangesThatCannotBeCounted
     */
    public function testRangeThatCannotBeCountedAddsNoDerivatives(string $min, string $max, string $step): void
    {
        $this->assertSame('', $this->medium()->derivatives($min, $max, $step)->srcset(false));
    }

    public static function rangesThatCannotBeCounted(): array
    {
        return [
            'step zero, on the base width' => ['1024', '1024', '0'],
            'step zero, below the base width' => ['200', '1000', '0'],
            'step negative' => ['200', '1000', '-200'],
            'step below one' => ['200', '1000', '0.5'],
            'step empty' => ['200', '1000', ''],
            'step not a number' => ['200', '1000', 'x'],
            'width below one' => ['0', '1000', '200'],
        ];
    }

    public function testRangeGivenAsStringsIsCounted(): void
    {
        $widths = $this->widths($this->medium()->derivatives('200', '1000', '400'));
        $this->assertSame(['200w', '600w', '1000w', '1024w'], $widths);
    }

    public function testListedWidthsBelowOneAreSkipped(): void
    {
        $widths = $this->widths($this->medium()->derivatives(['0', '-100', 'x', '300']));
        $this->assertSame(['300w', '1024w'], $widths);

        $this->assertSame('', $this->medium()->derivatives('0')->srcset(false));
    }
}
