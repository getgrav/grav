<?php

use Grav\Common\Page\Markdown\Excerpts;
use Grav\Common\Page\Medium\Medium;

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
            'image.png?resize=100,200&setImagePrettyName=pwned&grayscale&foo=1'
        );

        self::assertContains('resize', $recorder->called, 'documented real method runs');
        self::assertNotContains('setImagePrettyName', $recorder->called, 'undocumented real method must be blocked');
        self::assertContains('call:grayscale', $recorder->called, 'image filter passthrough preserved');
        self::assertContains('call:foo', $recorder->called, 'arbitrary URL param passthrough preserved');
    }
}
