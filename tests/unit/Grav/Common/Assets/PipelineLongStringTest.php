<?php

use Codeception\Util\Fixtures;
use Grav\Common\Assets\Pipeline;

/**
 * Regression for https://github.com/getgrav/grav/issues/4372 -- the CSS pipeline scanned
 * strings and comments with patterns that cost PCRE one stack frame or backtrack per
 * character. A quoted string of a few KB (an inlined data: URI) ran out of JIT stack, so the
 * file fell back to unminified and its @imports stayed where they were, and a data: URI of
 * about 1 MB made the url() rewriter return nothing, which emptied the whole file.
 */
class PipelineLongStringTest extends \Codeception\Test\Unit
{
    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $grav();
    }

    /**
     * @param string $method
     * @param mixed ...$args
     * @return mixed
     */
    private function call(string $method, ...$args)
    {
        $pipeline = new Pipeline([]);
        $reflection = new \ReflectionMethod($pipeline, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke($pipeline, ...$args);
    }

    /**
     * Each case is a stylesheet template with a LONG placeholder and the text that goes in it.
     */
    public function longContentProvider(): array
    {
        $kb50 = 'data:image/png;base64,' . str_repeat('QUJD', 12500);
        $mb1 = 'data:image/png;base64,' . str_repeat('QUJD', 275000);

        return [
            'double-quoted url, 50 KB' => [".a {\n  background: url(\"LONG\");\n}\n", $kb50],
            'single-quoted url, 50 KB' => [".a {\n  background: url('LONG');\n}\n", $kb50],
            'double-quoted url, 1 MB' => [".a {\n  background: url(\"LONG\");\n}\n", $mb1],
            'content string with escapes, 20 KB' => [".b::before {\n  content: \"\\\"LONG\\\\\";\n}\n", str_repeat('x', 20000)],
            'license comment, 1 MB' => ["/*! LONG */\n.c {\n  color: red;\n}\n", str_repeat('x', 1100000)],
        ];
    }

    /**
     * A long string or comment minifies exactly like a short one.
     *
     * @dataProvider longContentProvider
     */
    public function testLongContentMinifiesLikeShortContent(string $template, string $long): void
    {
        $short = 'SHORT';
        $expected = str_replace($short, $long, $this->call('minifyCss', str_replace('LONG', $short, $template)));

        $this->assertSame($expected, $this->call('minifyCss', str_replace('LONG', $long, $template)));
    }

    public function testLongOrdinaryCommentIsDropped(): void
    {
        $css = '/* ' . str_repeat('x', 1100000) . " */\n.c {\n  color: red;\n}\n";

        $this->assertSame('.c{color:red}', $this->call('minifyCss', $css));
    }

    public function testUnterminatedStringStillFails(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unterminated string');

        $this->call('minifyCss', ".a { content: \"" . str_repeat('x', 50000) . "\n}\n.b { color: red; }");
    }

    /**
     * @dataProvider longContentProvider
     */
    public function testImportIsHoistedPastLongContent(string $template, string $long): void
    {
        $css = str_replace('LONG', $long, $template) . "@import \"print.css\";\n";

        $result = $this->call('moveImports', $css);

        $this->assertStringStartsWith("@import \"print.css\";\n\n", $result);
        $this->assertStringContainsString($long, $result);
    }

    /**
     * @dataProvider longContentProvider
     */
    public function testUrlRewriteKeepsLongContent(string $template, string $long): void
    {
        $css = str_replace('LONG', $long, $template) . ".d { background: url(images/bg.png); }\n";

        $result = $this->call('cssRewrite', $css, 'assets/css', true);

        $this->assertStringContainsString($long, $result);
        $this->assertStringContainsString('assets/css/images/bg.png', $result);
    }
}
