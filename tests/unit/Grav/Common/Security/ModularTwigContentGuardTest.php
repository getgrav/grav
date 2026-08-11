<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Common\Page\Interfaces\PageInterface;
use Grav\Common\Security;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * Class ModularTwigContentGuardTest
 *
 * Regression coverage for GHSA-fg8g-663r-f366 — an incomplete fix for
 * GHSA-2c4f-86xc-cr74.
 *
 * The save-time guard (Security::detectXssInEditorContent) and the render-time
 * sinks (Page::content(), PageContentTrait::processContent()) used to compute
 * *different* booleans for "will Twig run over this page's body?".
 *
 *   render: ($gate && $page->shouldProcess('twig')) || $page->isModule()
 *   save:    $gate && $page->shouldProcess('twig')          <-- no modular branch
 *
 * Since security.twig_content.process_enabled ships false, a modular page
 * executed its body Twig at render time while the save-time scan was skipped
 * entirely, letting a non-superadmin editor store a Twig-assembled payload that
 * fired for every visitor to the page embedding the module.
 *
 * Both sides now read Security::willProcessContentTwig(). These tests pin that
 * the shared boolean equals the render-time expression for every combination,
 * so the two halves cannot drift apart again.
 *
 * Naming convention: test{Method}_{GHSA_ID}_{description}
 */
class ModularTwigContentGuardTest extends \PHPUnit\Framework\TestCase
{
    /** @var Grav */
    protected $grav;

    /** @var Environment */
    protected $twig;

    /** @var bool|null */
    protected $previousGate;

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $this->grav = $grav();

        $this->previousGate = $this->grav['config']->get('security.twig_content.process_enabled');

        // Bare environment: the `~` operator, {% autoescape %} and the escaping
        // behaviour under test are all core Twig.
        $this->twig = new Environment(new ArrayLoader(), ['autoescape' => 'html']);
    }

    protected function tearDown(): void
    {
        $this->grav['config']->set('security.twig_content.process_enabled', $this->previousGate);
        parent::tearDown();
    }

    /**
     * @param bool $isModule
     * @param bool $shouldProcessTwig
     * @return PageInterface
     */
    protected function page(bool $isModule, bool $shouldProcessTwig): PageInterface
    {
        $page = $this->createMock(PageInterface::class);
        $page->method('isModule')->willReturn($isModule);
        $page->method('shouldProcess')->willReturn($shouldProcessTwig);

        return $page;
    }

    /**
     * The whole point of the fix: one boolean, and it must equal the render-time
     * expression for every combination of gate / process:twig / modular.
     *
     * @dataProvider providerTwigContentDecision
     */
    public function testWillProcessContentTwig_GHSAfg8g_MatchesRenderTimeDecision(
        bool $gate,
        bool $shouldProcessTwig,
        bool $isModule,
        bool $expected,
        string $description
    ): void {
        $this->grav['config']->set('security.twig_content.process_enabled', $gate);
        $page = $this->page($isModule, $shouldProcessTwig);

        // The literal render-time expression, as it appears in Page::content()
        // and PageContentTrait::processContent() before the refactor.
        $renderTimeDecision = ($gate && $shouldProcessTwig) || $isModule;

        self::assertSame(
            $expected,
            $renderTimeDecision,
            "Test fixture disagrees with the render-time expression: $description"
        );

        self::assertSame(
            $expected,
            Security::willProcessContentTwig($page),
            "Save-time guard must agree with the render: $description"
        );
    }

    /**
     * @return array<string, array{0: bool, 1: bool, 2: bool, 3: bool, 4: string}>
     */
    public function providerTwigContentDecision(): array
    {
        return [
            // The vulnerable combination: shipped default gate, module renders
            // Twig anyway, and the save guard used to return null here.
            'module, gate off, no explicit process:twig' => [false, false, true, true,
                'a module runs body Twig regardless of the gate, so the save must scan it'],
            'module, gate off, process:twig requested' => [false, true, true, true,
                'same, with the header opting in'],
            'module, gate on' => [true, true, true, true,
                'module with the gate on'],
            'module, gate on, no process:twig' => [true, false, true, true,
                'a module never needs to request Twig'],

            // Non-modular behaviour must be untouched by the fix.
            'ordinary page, gate off, process:twig requested' => [false, true, false, false,
                'gate off blocks editor Twig, raw-source validator covers it'],
            'ordinary page, gate off, no process:twig' => [false, false, false, false,
                'nothing to scan'],
            'ordinary page, gate on, process:twig requested' => [true, true, false, true,
                'the pre-existing scanned case'],
            'ordinary page, gate on, no process:twig' => [true, false, false, false,
                'gate on but the page never asked for Twig'],
        ];
    }

    /**
     * The specific regression: with the shipped default gate, a modular page is
     * in scope for the save-time scan. Before the fix this returned false and the
     * guard bailed out before rendering anything.
     */
    public function testWillProcessContentTwig_GHSAfg8g_ModuleIsInScopeOnDefaultConfig(): void
    {
        $this->grav['config']->set('security.twig_content.process_enabled', false);

        self::assertTrue(
            Security::willProcessContentTwig($this->page(true, false)),
            'A modular page must be scanned at save time even though the content-Twig gate is off'
        );
        self::assertFalse(
            Security::willProcessContentTwig($this->page(false, true)),
            'A non-modular page with the gate off is still out of scope (unchanged behaviour)'
        );
    }

    /**
     * The payloads that actually work. Twig autoescape neutralises the naive
     * `{{ "on" ~ "error" }}` attribute-splitting form, so a real exploit has to
     * defeat escaping — `{% autoescape false %}` is allow-listed for content
     * Twig, and the `markdown` filter is marked `is_safe: html`.
     *
     * Raw source is clean (the pre-render validator cannot see it) but the
     * rendered output must be flagged, which is what the save-time scan runs on.
     *
     * @dataProvider providerAssembledModulePayloads
     */
    public function testDetectXss_GHSAfg8g_AssembledPayloadIsCaughtOnlyAfterRender(
        string $rawSource,
        string $expectedRule,
        string $description
    ): void {
        self::assertNull(
            Security::detectXss($rawSource),
            "Raw source must slip past the pre-render validator, that is the bug: $description"
        );

        $rendered = $this->twig->createTemplate($rawSource)->render([]);

        self::assertSame(
            $expectedRule,
            Security::detectXss($rendered),
            "Rendered output must be flagged by the save-time scan: $description (rendered: $rendered)"
        );
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public function providerAssembledModulePayloads(): array
    {
        return [
            'autoescape disabled, split event handler' => [
                '{% autoescape false %}{{ "<img src=x on" ~ "error=alert(1)>" }}{% endautoescape %}',
                'on_events',
                'autoescape is allow-listed for content Twig',
            ],
            'autoescape disabled, split script tag' => [
                '{% autoescape false %}{{ "<scr" ~ "ipt>alert(1)</scr" ~ "ipt>" }}{% endautoescape %}',
                'dangerous_tags',
                'tag name split across a concatenation',
            ],
        ];
    }

    /**
     * The attribute-splitting form from the original report.
     *
     * Autoescape does NOT neutralise it: the `{{ }}` output is the bare string
     * "onerror", which has nothing to escape, and the surrounding `<img src=x`
     * is literal template text. So the save-time scan — which renders the raw
     * body through Twig with no markdown pass — sees live markup and rejects it.
     *
     * It nevertheless fails to fire at render time, because Grav runs markdown
     * first by default (system.pages.twig_first: false) and Parsedown escapes
     * `<img src=x {{ ... }}=alert(1)>` as not-a-valid-tag. That makes the guard
     * strictly more conservative than the render, which is the correct
     * direction, and it is why the published advisory does not use this payload
     * as its proof of concept.
     */
    public function testDetectXss_GHSAfg8g_AttributeSplittingIsCaughtBySaveScan(): void
    {
        $rendered = $this->twig
            ->createTemplate('<img src=x {{ "on" ~ "error" }}=alert(1)>')
            ->render([]);

        self::assertStringContainsString(
            'onerror=alert(1)',
            $rendered,
            'Twig autoescape does not defuse a handler name assembled outside a tag it can see'
        );

        self::assertSame(
            'on_events',
            Security::detectXss($rendered),
            'The save-time scan must reject the attribute-splitting form too'
        );
    }
}
