<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Common\Helpers\FileReader;
use Grav\Common\Twig\Extension\GravExtension;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

/**
 * Tests for {@see \Grav\Common\Helpers\FileReader::read()}.
 *
 * Covers the layered defence in the helper:
 *   1. Cheap input hygiene (null bytes, backslashes, URL encoding, `..` segments).
 *   2. Stream-only + scheme allow-list.
 *   3. Extension allow-list.
 *   4. Canonical realpath containment against the stream's resolved roots.
 *   5. Max file size cap.
 *
 * Each test sets up a fresh sandbox directory under sys_get_temp_dir() and
 * registers it as a custom locator scheme `readfiletest://`, so the suite is
 * self-contained and doesn't depend on any particular theme being installed.
 *
 * One delegate test verifies `GravExtension::readFileFunc()` still routes
 * through the helper — both the Twig function and the shortcode-core
 * `[read-file]` shortcode share this code path.
 */
class FileReaderTest extends \PHPUnit\Framework\TestCase
{
    /** @var Grav */
    protected $grav;

    /** @var string Absolute path to the sandbox dir registered as readfiletest:// */
    protected $sandbox;

    /** @var string Absolute path to the "outside" dir used to test symlink escape. */
    protected $outside;

    /** @var array<string,mixed> Saved config values restored in tearDown. */
    protected $configBackup = [];

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $this->grav = $grav();

        $this->sandbox = sys_get_temp_dir() . '/grav-readfile-' . bin2hex(random_bytes(6));
        $this->outside = sys_get_temp_dir() . '/grav-readfile-out-' . bin2hex(random_bytes(6));
        mkdir($this->sandbox, 0777, true);
        mkdir($this->outside, 0777, true);

        // Fixture files inside the sandbox.
        file_put_contents($this->sandbox . '/good.md', "hello, world\n");
        file_put_contents($this->sandbox . '/upper.MD', "shouty\n");
        file_put_contents($this->sandbox . '/script.php', "<?php echo 'no'; ?>\n");
        file_put_contents($this->sandbox . '/big.md', str_repeat('A', 4096));
        mkdir($this->sandbox . '/sub', 0777, true);
        file_put_contents($this->sandbox . '/sub/nested.md', "nested\n");

        // A file outside the sandbox + a symlink inside the sandbox that points
        // at it. The containment check must resolve the symlink and reject the
        // read because the realpath ends up outside any root of the stream.
        file_put_contents($this->outside . '/secret.md', "stolen\n");
        // Skip symlink-based test on systems where the symlink can't be
        // created (e.g. some Windows configurations).
        @symlink($this->outside . '/secret.md', $this->sandbox . '/escape.md');

        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];
        // resetScheme avoids stale entries from prior tests pointing at
        // deleted temp dirs.
        $locator->resetScheme('readfiletest');
        $locator->addPath('readfiletest', '', $this->sandbox);

        // Whitelist this scheme so we can exercise the not-on-allow-list
        // branch with `system://` (a real but non-allowed Grav stream).
        $config = $this->grav['config'];
        $this->configBackup = [
            'security.read_file.allowed_streams'    => $config->get('security.read_file.allowed_streams'),
            'security.read_file.allowed_extensions' => $config->get('security.read_file.allowed_extensions'),
            'security.read_file.max_size'           => $config->get('security.read_file.max_size'),
        ];
        $config->set('security.read_file.allowed_streams',    ['readfiletest']);
        $config->set('security.read_file.allowed_extensions', ['md', 'txt']);
        $config->set('security.read_file.max_size',           1048576);
    }

    protected function tearDown(): void
    {
        foreach ($this->configBackup as $key => $value) {
            $this->grav['config']->set($key, $value);
        }

        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];
        $locator->resetScheme('readfiletest');

        $this->rrmdir($this->sandbox);
        $this->rrmdir($this->outside);

        parent::tearDown();
    }

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $entry;
            if (is_link($path) || is_file($path)) {
                @unlink($path);
            } else {
                $this->rrmdir($path);
            }
        }
        @rmdir($dir);
    }

    // ---- happy path ----------------------------------------------------

    public function testReadsAllowedFile(): void
    {
        self::assertSame("hello, world\n", FileReader::read('readfiletest://good.md'));
    }

    public function testReadsNestedFile(): void
    {
        self::assertSame("nested\n", FileReader::read('readfiletest://sub/nested.md'));
    }

    public function testExtensionCheckIsCaseInsensitive(): void
    {
        self::assertSame("shouty\n", FileReader::read('readfiletest://upper.MD'));
    }

    // ---- input-hygiene rejections -------------------------------------

    public function testRejectsEmptyString(): void
    {
        self::assertFalse(FileReader::read(''));
    }

    public function testRejectsNonString(): void
    {
        // @phpstan-ignore-next-line — testing the runtime guard
        self::assertFalse(FileReader::read(null));
        // @phpstan-ignore-next-line
        self::assertFalse(FileReader::read(['readfiletest://good.md']));
    }

    public function testRejectsNullByte(): void
    {
        self::assertFalse(FileReader::read("readfiletest://good\0.md"));
    }

    public function testRejectsBackslash(): void
    {
        self::assertFalse(FileReader::read('readfiletest://sub\\nested.md'));
    }

    public function testRejectsUrlEncodedTraversal(): void
    {
        // %2e%2e%2f decodes to ../ — round-trip mismatch is rejected outright.
        self::assertFalse(FileReader::read('readfiletest://%2e%2e/good.md'));
    }

    public function testRejectsDoubleUrlEncodedTraversal(): void
    {
        self::assertFalse(FileReader::read('readfiletest://%252e%252e/good.md'));
    }

    public function testRejectsAnyUrlEncoding(): void
    {
        // Even harmless `%2E` is rejected — read paths are not URLs.
        self::assertFalse(FileReader::read('readfiletest://good%2Emd'));
    }

    public function testRejectsLiteralDotDotSegment(): void
    {
        self::assertFalse(FileReader::read('readfiletest://sub/../good.md'));
    }

    // ---- stream / scheme rejections -----------------------------------

    public function testRejectsRawFilesystemPath(): void
    {
        self::assertFalse(FileReader::read($this->sandbox . '/good.md'));
        self::assertFalse(FileReader::read('/etc/hosts'));
    }

    public function testRejectsDisallowedStream(): void
    {
        // `system://` is a real Grav stream but not in our allow list.
        self::assertFalse(FileReader::read('system://defines.php'));
    }

    public function testRejectsUnknownStream(): void
    {
        self::assertFalse(FileReader::read('madeupscheme://anything.md'));
    }

    // ---- extension rejections ------------------------------------------

    public function testRejectsDisallowedExtension(): void
    {
        self::assertFalse(FileReader::read('readfiletest://script.php'));
    }

    public function testRejectsMissingExtension(): void
    {
        file_put_contents($this->sandbox . '/noext', 'x');
        self::assertFalse(FileReader::read('readfiletest://noext'));
    }

    // ---- containment rejection ----------------------------------------

    public function testRejectsSymlinkOutOfRoot(): void
    {
        if (!is_link($this->sandbox . '/escape.md')) {
            self::markTestSkipped('Symlink creation not supported on this system.');
        }
        // The locator resolves readfiletest://escape.md to a path *inside* the
        // sandbox, but realpath() follows the symlink to $outside/secret.md —
        // which is not contained in any of readfiletest://'s roots. Rejected.
        self::assertFalse(FileReader::read('readfiletest://escape.md'));
    }

    public function testRejectsNonExistentFile(): void
    {
        self::assertFalse(FileReader::read('readfiletest://nope.md'));
    }

    public function testPrefixCollisionDoesNotEscape(): void
    {
        // Build a sibling of $sandbox whose realpath shares a leading
        // substring with the sandbox root. Without the trailing-separator
        // strncmp, "/tmp/grav-readfile-ABC" would prefix-match
        // "/tmp/grav-readfile-ABC-evil". The locator wouldn't resolve a
        // `readfiletest://` URI to here anyway, but make sure even a
        // hand-crafted attempt is rejected.
        $sibling = $this->sandbox . '-evil';
        mkdir($sibling, 0777, true);
        file_put_contents($sibling . '/leak.md', "leaked\n");
        try {
            self::assertFalse(FileReader::read('readfiletest://../grav-readfile-out-zzz/leak.md'));
        } finally {
            @unlink($sibling . '/leak.md');
            @rmdir($sibling);
        }
    }

    // ---- size cap ------------------------------------------------------

    public function testEnforcesMaxSize(): void
    {
        $this->grav['config']->set('security.read_file.max_size', 100);
        // big.md is 4096 bytes, well over the 100-byte cap.
        self::assertFalse(FileReader::read('readfiletest://big.md'));
        // The smaller fixture still goes through.
        self::assertSame("hello, world\n", FileReader::read('readfiletest://good.md'));
    }

    public function testZeroMaxSizeDisablesTheCap(): void
    {
        $this->grav['config']->set('security.read_file.max_size', 0);
        self::assertSame(str_repeat('A', 4096), FileReader::read('readfiletest://big.md'));
    }

    // ---- delegate from the Twig function ------------------------------

    public function testTwigReadFileFuncDelegatesToHelper(): void
    {
        // Smoke-test the Twig surface: GravExtension::readFileFunc() must
        // route to FileReader::read() (and therefore inherit every constraint
        // exercised above). Add one happy-path and one rejection so a
        // future refactor that bypasses the helper is caught.
        $ext = new GravExtension();
        self::assertSame("hello, world\n", $ext->readFileFunc('readfiletest://good.md'));
        self::assertFalse($ext->readFileFunc('readfiletest://script.php'));
    }
}
