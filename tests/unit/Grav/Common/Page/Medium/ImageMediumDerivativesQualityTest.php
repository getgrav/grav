<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Common\Page\Medium\ImageMedium;
use Grav\Common\Page\Medium\MediumFactory;

/**
 * Regression for https://github.com/getgrav/grav/issues/3146 -- quality()
 * and format() chained after derivatives() only ever touched the base
 * image, leaving every generated srcset alternative at its default.
 */
class ImageMediumDerivativesQualityTest extends \Codeception\Test\Unit
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

    /** @return string[] format property, read via reflection since format() has no getter branch */
    private function formatOf(ImageMedium $medium): string
    {
        $property = new \ReflectionProperty($medium, 'format');
        $property->setAccessible(true);

        return $property->getValue($medium);
    }

    public function testQualityAfterDerivativesAppliesToEachAlternative(): void
    {
        $medium = $this->medium()->derivatives(200, 800, 200)->quality(1);

        $alternatives = $medium->getAlternatives();
        $this->assertNotEmpty($alternatives);

        foreach ($alternatives as $alternative) {
            $this->assertSame(1, $alternative->quality());
        }
    }

    public function testFormatAfterDerivativesAppliesToEachAlternative(): void
    {
        $medium = $this->medium()->derivatives(200, 800, 200)->format('webp');

        $alternatives = $medium->getAlternatives();
        $this->assertNotEmpty($alternatives);

        foreach ($alternatives as $alternative) {
            $this->assertSame('webp', $this->formatOf($alternative));
        }
    }
}
