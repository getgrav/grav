<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Common\Page\Medium\ImageFile;
use Grav\Common\Page\Medium\ImageMedium;
use Grav\Common\Page\Medium\MediumFactory;


class ImageFormatPathSecurityTest extends \Codeception\Test\Unit
{
    /** @var Grav */
    protected $grav;

    /** @var string */
    protected $image = 'tests/fake/nested-site/user/pages/01.item1/home-sample-image.jpg';

    /** @var string */
    protected $cache;

    /** @var string string */
    protected $marker;

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $this->grav = $grav();
        $this->cache = $this->grav['locator']->findResource('cache://images', true, true);
        $this->marker = 'format-check-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        @rmdir($this->cache . '/' . $this->marker);
        parent::tearDown();
    }

    private function medium(): ImageMedium
    {
        $medium = MediumFactory::fromFile(GRAV_ROOT . '/' . $this->image);
        $this->assertInstanceOf(ImageMedium::class, $medium);

        return $medium;
    }

    public function testFormatThatIsNotAnOutputTypeIsIgnored(): void
    {
        $path = $this->medium()->format("invalid/type")->path();

        $this->assertDirectoryDoesNotExist($this->cache . '/' . $this->marker);
        $this->assertStringNotContainsString('..', $path);
        $this->assertStringEndsWith('.jpg', $path);
    }


    public function testFormatThatIsNotAStringIsIgnored(): void
    {
        $this->assertStringEndsWith('.jpg', $this->medium()->format(['png', 'gif'])->path());
    }

    public function testOutputTypesStillWork(): void
    {
        $this->assertStringEndsWith('.png', $this->medium()->format('png')->path());
        $this->assertStringEndsWith('.png', $this->medium()->format('PNG')->path());
        $this->assertStringEndsWith('.gif', $this->medium()->format('gif')->path());
        $this->assertStringEndsWith('.jpg', $this->medium()->format('png')->format('guess')->path());
    }

    public function testCacheFileRefusesATypeBeforeNamingAFileAfterIt(): void
    {
        $image = ImageFile::open(GRAV_ROOT . '/' . $this->image)->setCacheDir($this->cache)->setActualCacheDir($this->cache);

        try {
            $image->cacheFile("invalid/type");
            $this->fail('An unknown type must be refused');
        } catch (InvalidArgumentException) {
            $this->assertDirectoryDoesNotExist($this->cache . '/' . $this->marker);
        }
    }


    public function testExtensionFromMetadataIsRefusedToo(): void
    {
        $medium = $this->medium();
        $medium->set('extension', "invalid/type");

        try {
            $medium->cache()->path();
            $this->fail('An unknown type must be refused');
        } catch (InvalidArgumentException) {
            $this->assertDirectoryDoesNotExist($this->cache . '/' . $this->marker);
        }
    }
}
