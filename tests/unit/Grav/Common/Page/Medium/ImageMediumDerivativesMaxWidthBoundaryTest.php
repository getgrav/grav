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
}
