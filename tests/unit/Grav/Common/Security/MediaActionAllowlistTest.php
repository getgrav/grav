<?php

use Codeception\Util\Fixtures;
use Grav\Common\Page\Markdown\Excerpts;
use Grav\Common\Page\Medium\ImageMedium;
use Grav\Common\Page\Medium\Link;
use Grav\Common\Page\Medium\Medium;
use Grav\Common\Page\Medium\MediumFactory;

/**
 * Class MediaActionAllowlistTest
 *
 * Covers the hardening added alongside GHSA-ffmg-hfvg-jhg9: processMediaActions()
 * invoked arbitrary public methods named in an editor-authored image URL. Media
 * actions are now gated by Medium::ALLOWED_ACTIONS so only documented actions
 * (https://learn.getgrav.org/content/media) reach the medium.
 *
 * Naming convention: test{Subject}_{description}
 */
class MediaActionAllowlistTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @dataProvider providerDocumentedActions
     */
    public function testIsAllowedAction_AcceptsDocumentedActions(string $action): void
    {
        self::assertTrue(Medium::isAllowedAction($action), "$action should be allowed");
    }

    public static function providerDocumentedActions(): array
    {
        // A representative slice of each category in Medium::ALLOWED_ACTIONS.
        return array_map(static fn($a) => [$a], [
            'resize', 'forceResize', 'cropResize', 'crop', 'zoomCrop', 'cropZoom',
            'grayscale', 'sepia', 'gaussianBlur', 'rotate', 'quality', 'format',
            'watermark', 'derivatives',
            'lightbox', 'link', 'classes', 'style', 'id', 'attribute',
            'width', 'height', 'sizes', 'loading', 'decoding', 'fetchpriority',
            'controls', 'controlsList', 'autoplay', 'muted', 'preload', 'poster',
        ]);
    }

    /**
     * PHP method dispatch is case-insensitive, so the gate must be too — else
     * `?Resize=` would slip past the allowlist yet still call resize().
     *
     * @dataProvider providerCaseVariants
     */
    public function testIsAllowedAction_IsCaseInsensitive(string $action): void
    {
        self::assertTrue(Medium::isAllowedAction($action), "$action should be allowed case-insensitively");
    }

    public static function providerCaseVariants(): array
    {
        return [['RESIZE'], ['ReSiZe'], ['CropZoom'], ['cropzoom'], ['STYLE'], ['ForceResize']];
    }

    /**
     * @dataProvider providerDisallowedMethods
     */
    public function testIsAllowedAction_RejectsUndocumentedMethods(string $method): void
    {
        self::assertFalse(Medium::isAllowedAction($method), "$method must not be allowed");
    }

    public static function providerDisallowedMethods(): array
    {
        return array_map(static fn($m) => [$m], [
            // Real public medium/Data methods that are not media actions.
            'setImagePrettyName', 'getImagePrettyName', 'copy', 'addMetaFile',
            'url', 'path', 'set', 'def', 'merge', 'querystring', 'metadata',
            '__construct', '__call', '__toString',
            // Pure nonsense.
            'evilMethod', '', 'system', 'exec',
        ]);
    }

    /**
     * End-to-end: a real, undocumented method named in the URL is never
     * dispatched to the medium, while documented actions still are. Names that
     * are not real methods fall through to __call() (the URL-querystring
     * passthrough used for image filters and arbitrary params) — that path runs
     * no code, so it stays open.
     */
    public function testProcessMediaActions_BlocksRealUndocumentedMethods(): void
    {
        $recorder = new class {
            /** @var string[] */
            public array $called = [];
            // Real, documented action — must run.
            public function resize($w = null, $h = null) { $this->called[] = 'resize'; return $this; }
            // Real, UNDOCUMENTED method — must be blocked.
            public function setImagePrettyName($name) { $this->called[] = 'setImagePrettyName'; return $this; }
            // Everything else (filters, unknown params) → __call passthrough.
            public function __call($name, $args) { $this->called[] = "call:$name"; return $this; }
            public function urlHash($hash) { return $this; }
        };

        $excerpts = new Excerpts(null, ['markdown' => [], 'images' => []]);
        $excerpts->processMediaActions(
            $recorder,
            'image.png?resize=100,200&setImagePrettyName=updated&grayscale&foo=1'
        );

        self::assertContains('resize', $recorder->called, 'documented real method runs');
        self::assertNotContains('setImagePrettyName', $recorder->called, 'undocumented real method must be blocked');
        self::assertContains('call:grayscale', $recorder->called, 'image filter passthrough preserved');
        self::assertContains('call:foo', $recorder->called, 'arbitrary URL param passthrough preserved');
    }

    /**
     * @dataProvider providerScopedNames
     */
    public function testProcessMediaActions_ScopedNameIsNotTheRealMethod(string $scope): void
    {
        $grav = Fixtures::get('grav');
        $grav();

        $excerpts = new Excerpts(null, ['markdown' => [], 'images' => []]);
        $action = $scope . '::set=title,updated';

        $medium = $excerpts->processMediaActions($this->image(), 'image.jpg?' . $action);
        self::assertInstanceOf(ImageMedium::class, $medium);
        self::assertNull($medium->get('title'), 'scoped name must not reach the real method');
        self::assertStringContainsString($action, $medium->querystring(), 'unknown names stay URL params');

        $link = $excerpts->processMediaActions($this->image(), 'image.jpg?link&' . $action);
        self::assertInstanceOf(Link::class, $link);
        self::assertStringNotContainsString('title="updated"', $link->html(), 'nor that of the linked thumbnail');
    }

    public static function providerScopedNames(): array
    {
        return [['self'], ['SELF'], ['parent'], ['static'], [ImageMedium::class], [\Grav\Common\Data\Data::class]];
    }

    public function testProcessMediaActions_NonMediumReturnValueDoesNotReplaceMedium(): void
    {
        $recorder = new class {
            /** @var string[] */
            public array $called = [];
            public function display($mode = 'source') { $this->called[] = 'display'; return null; }
            public function thumbnail($type = 'auto') { $this->called[] = 'thumbnail'; return 'plain value'; }
            public function __call($name, $args) { $this->called[] = "call:$name"; return $this; }
            public function urlHash($hash) { return $this; }
        };

        $excerpts = new Excerpts(null, ['markdown' => [], 'images' => []]);
        $result = $excerpts->processMediaActions($recorder, 'image.png?display=thumbnail&thumbnail=auto&custom=value');

        self::assertSame($recorder, $result, 'medium is kept when an action returns something else');
        self::assertSame(['display', 'thumbnail', 'call:custom'], $recorder->called);
    }

    public function testProcessMediaActions_ChecksReplacementMediumMethods(): void
    {
        $grav = Fixtures::get('grav');
        $grav();
        $image = $this->image();
        $originalName = $image->getImagePrettyName();
        $document = new class($image) {
            private ImageMedium $image;
            public function __construct(ImageMedium $image) { $this->image = $image; }
            public function display($mode) { return $this->image; }
        };

        $excerpts = new Excerpts(null, ['markdown' => [], 'images' => []]);
        $result = $excerpts->processMediaActions($document, 'doc.pdf?display=thumbnail&setImagePrettyName=updated');

        self::assertSame($image, $result);
        self::assertSame($originalName, $image->getImagePrettyName());
    }

    public function testProcessMediaActions_ChecksLinkedMediumMethods(): void
    {
        $grav = Fixtures::get('grav');
        $grav();
        $excerpts = new Excerpts(null, ['markdown' => [], 'images' => []]);

        $link = $excerpts->processMediaActions($this->image(), 'image.jpg?link&set=title,updated&foo=1');
        self::assertInstanceOf(Link::class, $link);
        $source = (new \ReflectionProperty(Link::class, 'source'))->getValue($link);
        self::assertNull($source->get('title'));
        self::assertStringContainsString('foo=1', $source->querystring());
    }

    private function image(): ImageMedium
    {
        $medium = MediumFactory::fromFile(GRAV_ROOT . '/tests/fake/nested-site/user/pages/01.item1/home-sample-image.jpg');
        self::assertInstanceOf(ImageMedium::class, $medium);

        return $medium;
    }
}
