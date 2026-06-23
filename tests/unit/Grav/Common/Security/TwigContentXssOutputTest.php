<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Common\Security;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * Class TwigContentXssOutputTest
 *
 * Regression coverage for GHSA-2c4f-86xc-cr74 — the blueprint XSS validator runs
 * on the *raw* page source, so a payload assembled at render time with Twig's
 * `~` operator (e.g. `{{ "on" ~ "error" }}`) slips past it and then renders as a
 * live `onerror=` / `<script>` / `javascript:` in the output.
 *
 * The fix (Page::processTwig) re-runs Security::detectXss() on the rendered
 * output of editor-authored content Twig. These tests pin both halves of that
 * contract: the raw source is NOT flagged (proving the bypass is real and the
 * pre-render check alone is insufficient), and the rendered output IS flagged
 * (proving the post-render backstop catches it).
 *
 * Naming convention: test{Method}_{GHSA_ID}_{description}
 */
class TwigContentXssOutputTest extends \PHPUnit\Framework\TestCase
{
    /** @var Grav */
    protected $grav;

    /** @var Environment */
    protected $twig;

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $this->grav = $grav();

        // A bare Twig environment mirrors how page content Twig is rendered —
        // the `~` operator and `{% set %}` tag are core Twig, always available.
        $this->twig = new Environment(new ArrayLoader());
    }

    /**
     * The documented bypass: a render-time-assembled payload is invisible to a
     * validator that only sees the raw source.
     *
     * @dataProvider providerConcatenationBypass
     */
    public function testDetectXss_GHSA2c4f_RawSourceIsNotFlagged(string $rawSource, string $description): void
    {
        $result = Security::detectXss($rawSource);
        self::assertNull(
            $result,
            "Raw Twig source should slip past the pre-render validator (that is the bug being backstopped): $description"
        );
    }

    /**
     * The backstop: once the Twig is rendered, the live markup it produces must
     * be caught by detectXss — this is exactly what Page::processTwig now runs.
     *
     * @dataProvider providerConcatenationBypass
     */
    public function testDetectXss_GHSA2c4f_RenderedOutputIsFlagged(string $rawSource, string $description, string $expectedRule): void
    {
        $rendered = $this->twig->createTemplate($rawSource)->render([]);

        $result = Security::detectXss($rendered);
        self::assertNotNull(
            $result,
            "Rendered Twig output must be flagged by the post-render scan: $description (rendered: $rendered)"
        );
        self::assertSame(
            $expectedRule,
            $result,
            "Wrong rule fired for: $description (rendered: $rendered)"
        );
    }

    public static function providerConcatenationBypass(): array
    {
        return [
            'event handler via ~' => [
                '{% set x = "on" ~ "error" %}<img src=1 {{ x }}=alert(document.domain)>',
                'onerror assembled with the ~ operator',
                'on_events',
            ],
            'event handler inline ~' => [
                '<img src=1 {{ "on" ~ "error" }}=alert(1)>',
                'onerror inline concatenation',
                'on_events',
            ],
            'script tag reconstructed' => [
                '<s{{ "c" ~ "r" ~ "i" ~ "p" ~ "t" }}>alert(1)</s{{ "c" ~ "r" ~ "i" ~ "p" ~ "t" }}>',
                'script tag rebuilt char-by-char',
                'dangerous_tags',
            ],
            'javascript protocol reconstructed' => [
                '<a href="{{ "java" ~ "script" }}:alert(1)">click</a>',
                'javascript: protocol rebuilt with ~',
                'invalid_protocols',
            ],
        ];
    }

    /**
     * Sanity guard: the equivalent literal payloads (no Twig) are already caught
     * by the pre-render validator, so the bypass is specifically the render-time
     * assembly, not a hole in detectXss itself.
     *
     * @dataProvider providerLiteralEquivalents
     */
    public function testDetectXss_GHSA2c4f_LiteralEquivalentsAreCaughtPreRender(string $literal, string $expectedRule, string $description): void
    {
        $result = Security::detectXss($literal);
        self::assertSame($expectedRule, $result, "Literal payload should already be caught pre-render: $description");
    }

    public static function providerLiteralEquivalents(): array
    {
        return [
            ['<img src=1 onerror=alert(1)>', 'on_events', 'literal onerror'],
            ['<script>alert(1)</script>', 'dangerous_tags', 'literal script tag'],
            ['<a href="javascript:alert(1)">click</a>', 'invalid_protocols', 'literal javascript: protocol'],
        ];
    }
}
