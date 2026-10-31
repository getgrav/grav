<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Common\Page\Medium\GlobalMedia;
use Grav\Common\Twig\Extension\GravExtension;
use Grav\Common\Utils;

/**
 * Class SandboxUrlStreamTest
 *
 * `url()` resolves a stream URI through the locator, which answers `false` when
 * the file is not there. In a sandboxed render (page content) it now resolves a
 * stream only inside the folders in `security.media.allowed_roots`, the same
 * rule as media_directory(). Anywhere else it answers the same for a file that
 * exists and one that does not. Trusted templates and PHP callers of
 * Utils::url() are unchanged.
 */
class SandboxUrlStreamTest extends \PHPUnit\Framework\TestCase
{
    /** @var Grav */
    protected $grav;

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $this->grav = $grav();
        $this->grav['locator']->addPath('theme', '', 'tests/fake/twig-first-site/user/themes/testing', false);
        $this->grav['twig']->init();
    }

    /**
     * Render page-content Twig: `@Var:` sources are always sandboxed.
     */
    protected function sandboxed(string $twig): string
    {
        return $this->grav['twig']->processString($twig);
    }

    public function testSandboxedUrl_AnswersTheSameForPresentAndMissingFilesOutsideMediaFolders(): void
    {
        self::assertFileExists(GRAV_ROOT . '/system/config/system.yaml');
        self::assertFileDoesNotExist(GRAV_ROOT . '/system/config/nope.yaml');

        self::assertSame('[]', $this->sandboxed("[{{ url('system://config/system.yaml') }}]"));
        self::assertSame('[]', $this->sandboxed("[{{ url('system://config/nope.yaml') }}]"));
        self::assertSame('[]', $this->sandboxed("[{{ url('system://config') }}]"));

        // fail_gracefully returns the would-be URL for a missing file, so it must be refused too.
        self::assertSame('[]', $this->sandboxed("[{{ url('system://config/system.yaml', false, true) }}]"));
        self::assertSame('[]', $this->sandboxed("[{{ url('system://config/nope.yaml', false, true) }}]"));
    }

    /**
     * The check runs on the URI the locator looks up, so shapes that parse_url()
     * and the locator split differently, or that try to step out of a media
     * folder, are refused as well.
     */
    public function testSandboxedUrl_RefusesUrisThatLeaveTheMediaFolders(): void
    {
        self::assertSame('[]', $this->sandboxed("[{{ url('system:/config/system.yaml?x=://') }}]"));
        self::assertSame('[]', $this->sandboxed("[{{ url('system:///config/system.yaml') }}]"));
        self::assertSame('[]', $this->sandboxed("[{{ url('system://images/../config/system.yaml') }}]"));
        self::assertSame('[]', $this->sandboxed("[{{ url('page://../../system/config/system.yaml') }}]"));
    }

    public function testSandboxedUrl_StillResolvesMediaFolders(): void
    {
        self::assertFileExists(GRAV_ROOT . '/system/images/media/thumb.png');

        self::assertSame('[/system/images/media/thumb.png]', $this->sandboxed("[{{ url('system://images/media/thumb.png') }}]"));
        self::assertSame('[/user/pages]', $this->sandboxed("[{{ url('page://') }}]"));
        // Inside a media folder a missing file still behaves as before: false, or the would-be URL.
        self::assertSame('[]', $this->sandboxed("[{{ url('system://images/media/nope.png') }}]"));
        self::assertSame('[/system/images/media/nope.png]', $this->sandboxed("[{{ url('system://images/media/nope.png', false, true) }}]"));
        // Routes and external URLs are not streams and are untouched.
        self::assertSame('[https://example.com/x]', $this->sandboxed("[{{ url('https://example.com/x') }}]"));
    }

    public function testTrustedCallers_AreUnchanged(): void
    {
        self::assertSame('/system/config/system.yaml', Utils::url('system://config/system.yaml'));
        self::assertFalse(Utils::url('system://config/nope.yaml'));

        $extension = new GravExtension();
        self::assertSame('/system/config/system.yaml', $extension->urlFunc('system://config/system.yaml'));
        self::assertSame('/system/config/system.yaml', $extension->urlGuarded(false, 'system://config/system.yaml'));
        self::assertFalse($extension->urlGuarded(true, 'system://config/system.yaml'));
    }

    public function testIsMediaUri_JudgesAMissingFileByItsClosestExistingFolder(): void
    {
        foreach (['system://config/system.yaml', 'system://config/nope.yaml', 'user://', 'user://data/nope.txt', 'tmp://nope', 'log://nope.log', 'backup://', 'not-a-stream://x'] as $uri) {
            self::assertFalse(GlobalMedia::isMediaUri($uri), $uri);
        }
        foreach (['system://images/media/thumb.png', 'system://images/nope/nope.png', 'page://', 'page://nope/nope.md', 'user://pages'] as $uri) {
            self::assertTrue(GlobalMedia::isMediaUri($uri), $uri);
        }
    }
}
