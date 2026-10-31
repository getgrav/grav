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

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $this->grav = $grav();
        $this->cache = GRAV_ROOT . '/tests/_output/format-' . bin2hex(random_bytes(4)) . '/images';
        mkdir($this->cache, 0777, true);
        $this->grav['locator']->addPath('cache', 'images', $this->cache);
    }

    protected function tearDown(): void
    {
        \Grav\Common\Filesystem\Folder::delete(dirname($this->cache));
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

        self::assertSame([], array_values(array_diff(scandir($this->cache), ['.', '..'])));
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
        } catch (InvalidArgumentException $e) {
            self::assertSame([], array_values(array_diff(scandir($this->cache), ['.', '..'])));
        }
    }

    public function testInvalidTypeCannotDeleteAnOlderFileOutsideTheCache(): void
    {
        $type = '/../../../../../../../sentinel.yaml';
        $image = ImageFile::open(GRAV_ROOT . '/' . $this->image)->setCacheDir($this->cache)->setActualCacheDir($this->cache)->setPrettyName('probe');
        $filename = $image->getHash($type) . '-probe.' . $type;
        $target = $image->getCacheSystem()->getCacheFile($filename, true);
        // Prepare the cache directories that the scanner creates in its first request.
        mkdir(substr($target, 0, strpos($target, '/..')), 0777, true);
        $sentinel = dirname($this->cache) . '/sentinel.yaml';
        file_put_contents($sentinel, 'keep');
        touch($sentinel, 1);
        try {
            $image->cacheFile($type);
        } catch (InvalidArgumentException $e) {
            // Rejection must happen before the cache looks up or removes the file.
        }
        self::assertFileExists($sentinel);
        self::assertSame('keep', file_get_contents($sentinel));
    }

    public function testInvalidExtensionFromMetadataIsIgnoredToo(): void
    {
        $medium = $this->medium();
        $medium->set('extension', 'invalid/type');
        $path = $medium->cache()->path();

        self::assertContains(pathinfo($path, PATHINFO_EXTENSION), ['jpg', 'jpeg']);
        self::assertStringNotContainsString('invalid/type', $path);
    }
}
