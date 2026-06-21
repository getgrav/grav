<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Common\Page\Medium\ImageMedium;
use Grav\Common\Page\Medium\MediumFactory;

/**
 * Covers the `url` override that ImageMedium honors for unmodified originals
 * (used by the Flex Object media proxy). A queued image operation must ignore
 * the override and keep serving from the image cache.
 */
class ImageMediumUrlTest extends \Codeception\Test\Unit
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

    public function testPlainUrlIsUnchangedWithoutOverride(): void
    {
        // Regression: a normal image still resolves to its on-disk path.
        $url = $this->medium()->url();
        $this->assertStringContainsString('home-sample-image.jpg', $url);
        $this->assertStringNotContainsString('/flex-media/', $url);
    }

    public function testOverrideIsReturnedForUnmodifiedOriginal(): void
    {
        $medium = $this->medium();
        $medium->set('url', '/flex-media/contacts/0001/home-sample-image.jpg');

        $this->assertSame('/flex-media/contacts/0001/home-sample-image.jpg', $medium->url());
    }

    public function testOverrideIsIgnoredOnDerivative(): void
    {
        $medium = $this->medium();
        $medium->set('url', '/flex-media/contacts/0001/home-sample-image.jpg');

        // A queued operation produces a cached derivative under images/ — the
        // override must NOT apply here.
        $url = $medium->cropResize(50, 50)->url();
        $this->assertStringNotContainsString('/flex-media/', $url);
        $this->assertStringContainsString('/images/', $url);
    }
}
