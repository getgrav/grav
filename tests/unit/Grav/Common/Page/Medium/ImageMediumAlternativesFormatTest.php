<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Common\Page\Markdown\Excerpts;
use Grav\Common\Page\Medium\ImageMedium;
use Grav\Common\Page\Medium\MediumFactory;

/**
 * Covers format() and quality() reaching an image's alternatives.
 *
 * getgrav/grav#4317: the magic actions (`resize`, `grayscale`, …) fan out to
 * every alternative from ImageMedium::__call(), but format() and quality() are
 * declared methods and never pass through it, so `?format=webp` or
 * `images.defaults: { format: webp }` converted the image and left every
 * derivative in the srcset in the source format. Both must now reach each
 * alternative, whether it was built by derivatives() before or after the call
 * or found on disk as a retina file.
 */
class ImageMediumAlternativesFormatTest extends \Codeception\Test\Unit
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

    /**
     * The first tests in the suite to encode WebP: CI asks for gd, not for gd
     * with WebP, so say why rather than fail on a build without it.
     */
    private function requireWebp(): void
    {
        if (!function_exists('imagewebp')) {
            $this->markTestSkipped('GD was built without WebP support');
        }
    }

    private function medium(string $image = null): ImageMedium
    {
        $medium = MediumFactory::fromFile(GRAV_ROOT . '/' . ($image ?? $this->image));
        $this->assertInstanceOf(ImageMedium::class, $medium);

        return $medium;
    }

    /**
     * The srcset as [width => cached file path] in srcset order, read without
     * resetting the medium so a test can keep asking it questions.
     *
     * @return array<int, string>
     */
    private function candidates(ImageMedium $medium): array
    {
        $srcset = $medium->srcset(false);
        $this->assertNotSame('', $srcset);

        $candidates = [];
        foreach (explode(', ', $srcset) as $candidate) {
            [$url, $descriptor] = explode(' ', $candidate);
            $path = GRAV_ROOT . substr($url, strpos($url, '/images/'));
            $this->assertFileExists($path);

            $candidates[(int) $descriptor] = $path;
        }

        return $candidates;
    }

    private function assertEveryCandidateIs(int $type, string $extension, array $candidates): void
    {
        foreach ($candidates as $width => $path) {
            $this->assertStringEndsWith('.' . $extension, $path, "{$width}w candidate");
            // The bytes, not just the name: getimagesize() reads the file's own signature.
            $this->assertSame($type, getimagesize($path)[2], "{$width}w candidate");
        }
    }

    private function assertCachedAs(int $type, string $extension, string $url): void
    {
        $path = GRAV_ROOT . substr($url, strpos($url, '/images/'));
        $this->assertFileExists($path);
        $this->assertStringEndsWith('.' . $extension, $path);
        $this->assertSame($type, getimagesize($path)[2]);
    }

    public function testDerivativesKeepTheSourceFormatWithoutFormat(): void
    {
        // Regression: nothing asked for a conversion, so nothing converts.
        $medium = $this->medium()->derivatives([200, 400]);

        $candidates = $this->candidates($medium);
        $this->assertSame([200, 400, 1024], array_keys($candidates));
        $this->assertEveryCandidateIs(IMAGETYPE_JPEG, 'jpg', $candidates);
    }

    public function testFormatReachesDerivativesBuiltBeforeIt(): void
    {
        $this->requireWebp();

        $medium = $this->medium()->derivatives([200, 400])->format('webp');

        $candidates = $this->candidates($medium);
        $this->assertSame([200, 400, 1024], array_keys($candidates));
        $this->assertEveryCandidateIs(IMAGETYPE_WEBP, 'webp', $candidates);
    }

    public function testFormatReachesDerivativesBuiltAfterIt(): void
    {
        $this->requireWebp();

        // `?format=webp&derivatives=[200,400]`: the order the actions arrive in
        // must not matter.
        $medium = $this->medium()->format('webp')->derivatives([200, 400]);

        $candidates = $this->candidates($medium);
        $this->assertSame([200, 400, 1024], array_keys($candidates));
        $this->assertEveryCandidateIs(IMAGETYPE_WEBP, 'webp', $candidates);
    }

    public function testFormatReachesARetinaAlternative(): void
    {
        $this->requireWebp();

        // Media::init() attaches a `name@2x.jpg` found next to the image the same
        // way. The fixture set has none, so attach a second file by hand; it is
        // the same size as the original, which is all this needs.
        $medium = $this->medium();
        $retina = $this->medium('tests/fake/nested-site/user/pages/01.item1/home-cache-image.jpg');
        $medium->addAlternative(2, $retina);
        $medium->format('webp');

        $this->assertCachedAs(IMAGETYPE_WEBP, 'webp', $retina->url(false));
        $this->assertCachedAs(IMAGETYPE_WEBP, 'webp', $medium->url(false));
    }

    public function testQualityReachesEveryAlternative(): void
    {
        $medium = $this->medium()->derivatives([200, 400])->quality(40);
        foreach ($medium->getAlternatives() as $alternative) {
            $this->assertSame(40, $alternative->quality());
        }
        $at40 = $this->candidates($medium);

        // Derivatives built after the call inherit it, and land on the very same
        // cache files: quality is part of the file's hash.
        $medium = $this->medium()->quality(40)->derivatives([200, 400]);
        foreach ($medium->getAlternatives() as $alternative) {
            $this->assertSame(40, $alternative->quality());
        }
        $this->assertSame($at40, $this->candidates($medium));

        // And those are different files from the default-quality ones, smaller
        // because 40 is a coarser JPEG.
        $atDefault = $this->candidates($this->medium()->derivatives([200, 400]));
        $this->assertSame([200, 400, 1024], array_keys($atDefault));
        $this->assertSame(array_keys($atDefault), array_keys($at40));
        foreach ([200, 400] as $width) {
            $this->assertNotSame($atDefault[$width], $at40[$width]);
            $this->assertLessThan(filesize($atDefault[$width]), filesize($at40[$width]));
        }
    }

    /**
     * @return array<string, array<int, array<string, string>>>
     */
    public function imagesDefaults(): array
    {
        return [
            'derivatives before format' => [['derivatives' => '[200,400]', 'format' => 'webp']],
            'format before derivatives' => [['format' => 'webp', 'derivatives' => '[200,400]']],
        ];
    }

    /**
     * The reported case: a Markdown image with `system.images.defaults` doing
     * the work, in either order the YAML might list the two keys.
     *
     * @dataProvider imagesDefaults
     */
    public function testImagesDefaultsConvertEveryCandidate(array $defaults): void
    {
        $this->requireWebp();

        $excerpts = new Excerpts(null, ['markdown' => [], 'images' => ['defaults' => $defaults]]);
        $medium = $excerpts->processMediaActions($this->medium(), 'home-sample-image.jpg');

        $candidates = $this->candidates($medium);
        $this->assertSame([200, 400, 1024], array_keys($candidates));
        $this->assertEveryCandidateIs(IMAGETYPE_WEBP, 'webp', $candidates);
    }
}
