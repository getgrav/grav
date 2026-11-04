<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Common\Twig\Extension\GravExtension;

/**
 * svg_image() inlines a file's markup and is printed with |raw by themes, so
 * both of its inputs have to be safe at the sink: the classes it writes into
 * the class attribute, and the file it reads.
 */
class SvgImageFunctionTest extends \PHPUnit\Framework\TestCase
{
    /** @var Grav */
    protected $grav;

    /** @var string */
    private $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->grav = Fixtures::get('grav');
        $this->dir = GRAV_ROOT . '/tmp/svg-image-test';
        @mkdir($this->dir, 0775, true);
        file_put_contents($this->dir . '/with-class.svg', '<svg xmlns="http://www.w3.org/2000/svg" class="logo"><rect/></svg>');
        file_put_contents($this->dir . '/no-class.svg', '<svg xmlns="http://www.w3.org/2000/svg"><rect/></svg>');
        file_put_contents($this->dir . '/secret.yaml', "salt: not-for-publishers\n");
        file_put_contents($this->dir . '/secret-name.svg.txt', "salt: not-for-publishers\n");
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->dir);
        parent::tearDown();
    }

    public function testClassesAreAddedToExistingClassAttribute(): void
    {
        $svg = GravExtension::svgImageFunction($this->dir . '/with-class.svg', 'h-8 w-auto');
        self::assertStringContainsString('class="logo inline-block h-8 w-auto"', $svg);
    }

    public function testClassesAreAddedWhenNoClassAttribute(): void
    {
        $svg = GravExtension::svgImageFunction($this->dir . '/no-class.svg', 'h-8');
        self::assertStringStartsWith('<svg class="inline-block h-8"', $svg);
    }

    public function testClassesCannotBreakOutOfExistingClassAttribute(): void
    {
        $svg = GravExtension::svgImageFunction($this->dir . '/with-class.svg', 'x" onload="alert(1)');
        self::assertStringNotContainsString('onload="alert(1)"', $svg);
        self::assertStringContainsString('x&quot; onload=&quot;alert(1)', $svg);
    }

    public function testClassesCannotBreakOutOfNewClassAttribute(): void
    {
        $svg = GravExtension::svgImageFunction($this->dir . '/no-class.svg', 'x" onload="alert(1)');
        self::assertStringNotContainsString('onload="alert(1)"', $svg);
        self::assertStringContainsString('x&quot; onload=&quot;alert(1)', $svg);
    }

    public function testRegexReplacementTokensInClassesAreLiteral(): void
    {
        $svg = GravExtension::svgImageFunction($this->dir . '/no-class.svg', '$0 \\1');
        self::assertStringStartsWith('<svg class="inline-block $0 \\1"', $svg);
    }

    public function testOnlySvgFilesAreRead(): void
    {
        self::assertNull(GravExtension::svgImageFunction($this->dir . '/secret.yaml'));
        self::assertNull(GravExtension::svgImageFunction($this->dir . '/secret-name.svg.txt'));
        // Traversal, the way a page header value reaches it through a theme.
        self::assertNull(GravExtension::svgImageFunction($this->dir . '/../svg-image-test/secret.yaml'));
    }

    public function testNonSvgContentInSvgFileIsNotInlined(): void
    {
        file_put_contents($this->dir . '/not-really.svg', "salt: not-for-publishers\n");
        self::assertNull(GravExtension::svgImageFunction($this->dir . '/not-really.svg'));
    }
}
