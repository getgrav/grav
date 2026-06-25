<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Common\Page\Pages;
use Grav\Common\Security;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

/**
 * Phase 1 of the "Twig in Content" streamline plan: the structured diagnostics
 * channel in Security.php.
 *
 * Covers:
 *  - the file-backed, capped, newest-first ring buffer of Twig-content security
 *    events (recordTwigContentEvent / recentTwigContentEvents / clearTwigContentEvents)
 *  - the structured event record shape
 *  - detectTwigLeaksFromPages(): pages whose raw {{ }} / {% %} will leak because
 *    the per-page request flag and/or the master gate is off.
 */
class TwigContentDiagnosticsTest extends \PHPUnit\Framework\TestCase
{
    /** @var Grav */
    protected $grav;

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $this->grav = $grav();
        // Start every test from an empty buffer.
        Security::clearTwigContentEvents();
    }

    protected function tearDown(): void
    {
        Security::clearTwigContentEvents();
        parent::tearDown();
    }

    /**
     * Invoke the private recordTwigContentEvent() helper.
     */
    private function record(string $type, string $route, string $token = '', string $class = '', string $hint = ''): void
    {
        $m = new ReflectionMethod(Security::class, 'recordTwigContentEvent');
        $m->setAccessible(true);
        $m->invoke(null, $type, $route, $token, $class, $hint);
    }

    // =========================================================================
    // Ring buffer
    // =========================================================================

    public function testRecentEvents_EmptyByDefault(): void
    {
        self::assertSame([], Security::recentTwigContentEvents());
    }

    public function testRecord_RoundTripsWithExpectedShape(): void
    {
        $this->record('sandbox_function', '/blog/post', 'read_file', '', 'add to allowed_functions');

        $events = Security::recentTwigContentEvents();
        self::assertCount(1, $events);

        $event = $events[0];
        self::assertSame('sandbox_function', $event['type']);
        self::assertSame('/blog/post', $event['route']);
        self::assertSame('read_file', $event['token']);
        self::assertSame('', $event['class']);
        self::assertSame('add to allowed_functions', $event['hint']);
        self::assertIsInt($event['timestamp']);
        self::assertEqualsCanonicalizing(
            ['type', 'route', 'token', 'class', 'hint', 'timestamp'],
            array_keys($event)
        );
    }

    public function testRecord_IsNewestFirst(): void
    {
        $this->record('gate_blocked', '/one', 'content');
        $this->record('gate_blocked', '/two', 'content');
        $this->record('xss_blanked', '/three', '<script>');

        $events = Security::recentTwigContentEvents();
        self::assertCount(3, $events);
        self::assertSame('/three', $events[0]['route']);
        self::assertSame('/two', $events[1]['route']);
        self::assertSame('/one', $events[2]['route']);
    }

    public function testRecord_CapsAtFifty(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $this->record('gate_blocked', '/page-' . $i, 'content');
        }

        $events = Security::recentTwigContentEvents();
        self::assertCount(50, $events);
        // Newest (page-59) retained, oldest beyond the cap (page-0) dropped.
        self::assertSame('/page-59', $events[0]['route']);
        self::assertSame('/page-10', $events[49]['route']);
    }

    public function testRecentEvents_LimitTruncates(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->record('gate_blocked', '/page-' . $i, 'content');
        }

        $events = Security::recentTwigContentEvents(3);
        self::assertCount(3, $events);
        self::assertSame('/page-9', $events[0]['route']);
    }

    public function testClear_RemovesBuffer(): void
    {
        $this->record('gate_blocked', '/one', 'content');
        self::assertNotEmpty(Security::recentTwigContentEvents());

        self::assertTrue(Security::clearTwigContentEvents());
        self::assertSame([], Security::recentTwigContentEvents());
    }

    public function testBuffer_PersistsToLogStream(): void
    {
        $this->record('gate_blocked', '/one', 'content');

        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];
        $file = $locator->findResource('log://twig-content-events.json');
        self::assertIsString($file);
        self::assertFileExists($file);
    }

    // =========================================================================
    // Leak detection
    // =========================================================================

    private function leakPages(): Pages
    {
        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];
        $locator->addPath('page', '', 'tests/fake/twig-leak-site/user/pages', false);
        $this->grav['config']->set('system.home.alias', '/home');

        $pages = $this->grav['pages'];
        $pages->init();

        return $pages;
    }

    public function testDetectLeaks_GateOff_FlagsAllUnrenderedTwig(): void
    {
        $config = $this->grav['config'];
        $prevGate = $config->get('security.twig_content.process_enabled');
        $config->set('security.twig_content.process_enabled', false);

        try {
            $leaks = Security::detectTwigLeaksFromPages($this->leakPages());

            // The clean page (no markers) is never flagged.
            self::assertArrayNotHasKey('/clean', $leaks);

            // Pages that carry markers and won't render them all leak. With the
            // gate off the reason is gate_off for every one, regardless of the
            // per-page flag.
            self::assertArrayHasKey('/leaky', $leaks);
            self::assertSame('gate_off', $leaks['/leaky']['reason']);

            self::assertArrayHasKey('/requested', $leaks);
            self::assertSame('gate_off', $leaks['/requested']['reason']);
            self::assertTrue($leaks['/requested']['requested']);

            self::assertArrayHasKey('/pageoff', $leaks);
            self::assertSame('gate_off', $leaks['/pageoff']['reason']);
        } finally {
            $config->set('security.twig_content.process_enabled', $prevGate);
        }
    }

    public function testDetectLeaks_GateOn_OnlyFlagsPageLevelOptOut(): void
    {
        $config = $this->grav['config'];
        $prevGate = $config->get('security.twig_content.process_enabled');
        $config->set('security.twig_content.process_enabled', true);

        try {
            $leaks = Security::detectTwigLeaksFromPages($this->leakPages());

            // Gate on: pages that inherit the gate (leaky) or explicitly request
            // Twig (requested) now render, so they don't leak.
            self::assertArrayNotHasKey('/leaky', $leaks);
            self::assertArrayNotHasKey('/requested', $leaks);
            self::assertArrayNotHasKey('/clean', $leaks);

            // The page that explicitly opted out (process.twig: false) still
            // leaks its markers — reason page_off, not gate_off.
            self::assertArrayHasKey('/pageoff', $leaks);
            self::assertSame('page_off', $leaks['/pageoff']['reason']);
            self::assertFalse($leaks['/pageoff']['requested']);
        } finally {
            $config->set('security.twig_content.process_enabled', $prevGate);
        }
    }

    // =========================================================================
    // Profile selector (Phase 3): the {process_enabled, editor_enabled} <-> named
    // profile mapping that the admin selector reads/writes.
    // =========================================================================

    public function testProfileFromFlags_AllFourCombinations(): void
    {
        self::assertSame('off', Security::twigContentProfileFromFlags(false, false));
        self::assertSame('trusted', Security::twigContentProfileFromFlags(true, false));
        self::assertSame('all', Security::twigContentProfileFromFlags(true, true));
        // Gate off but editor flag on is inert → custom, not a named profile.
        self::assertSame('custom', Security::twigContentProfileFromFlags(false, true));
    }

    public function testFlagsForProfile_NamedProfilesExpand_CustomDoesNot(): void
    {
        self::assertSame(['process_enabled' => false, 'editor_enabled' => false], Security::twigContentFlagsForProfile('off'));
        self::assertSame(['process_enabled' => true, 'editor_enabled' => false], Security::twigContentFlagsForProfile('trusted'));
        self::assertSame(['process_enabled' => true, 'editor_enabled' => true], Security::twigContentFlagsForProfile('all'));
        // custom (and any unknown value) must never rewrite the underlying keys.
        self::assertNull(Security::twigContentFlagsForProfile('custom'));
        self::assertNull(Security::twigContentFlagsForProfile('nonsense'));
    }

    public function testProfileOptions_CustomOnlyShownWhenCurrentIsCustom(): void
    {
        $config = $this->grav['config'];
        $prevProcess = $config->get('security.twig_content.process_enabled');
        $prevEditor  = $config->get('security.twig_content.editor_enabled');

        try {
            // Named state → custom not offered.
            $config->set('security.twig_content.process_enabled', true);
            $config->set('security.twig_content.editor_enabled', false);
            self::assertSame('trusted', Security::twigContentProfile());
            self::assertSame(['off', 'trusted', 'all'], array_keys(Security::twigContentProfileOptions()));

            // Odd combo → custom appears so the selector can show/preserve it.
            $config->set('security.twig_content.process_enabled', false);
            $config->set('security.twig_content.editor_enabled', true);
            self::assertSame('custom', Security::twigContentProfile());
            self::assertSame(['off', 'trusted', 'all', 'custom'], array_keys(Security::twigContentProfileOptions()));
        } finally {
            $config->set('security.twig_content.process_enabled', $prevProcess);
            $config->set('security.twig_content.editor_enabled', $prevEditor);
        }
    }

    // =========================================================================
    // Content Twig-token extractor + scan (Phase 4): the shared extractor that
    // suggests what content needs and that migrate-grav#11 reuses.
    // =========================================================================

    public function testExtractTwigTokens_PullsTagsFiltersFunctions(): void
    {
        $tokens = Security::extractTwigTokens(
            '{% if x %}{{ "a" | upper | truncate(5) }} {{ date("now") }}{% endif %}'
        );

        self::assertContains('if', $tokens['tags']);
        self::assertContains('endif', $tokens['tags']);
        self::assertContains('upper', $tokens['filters']);
        self::assertContains('truncate', $tokens['filters']);
        self::assertContains('date', $tokens['functions']);
    }

    public function testExtractTwigTokens_SkipsMethodCallsAndPlainText(): void
    {
        $tokens = Security::extractTwigTokens(
            'Plain text with maybe(parens). {{ page.media("x.jpg").url }} {{ foo() }}'
        );

        // page.media(...) is a method call, not a function; only foo() counts.
        self::assertContains('foo', $tokens['functions']);
        self::assertNotContains('media', $tokens['functions']);
        // Text outside Twig islands is ignored entirely.
        self::assertNotContains('maybe', $tokens['functions']);
    }

    public function testExtractTwigTokens_CapturesMethodChain(): void
    {
        $tokens = Security::extractTwigTokens(
            "{{ page.media['x.jpg'].cropResize(300,200).lightbox().html() }}"
        );

        // The media chain members feed allowed_methods, not functions.
        self::assertContains('cropResize', $tokens['methods']);
        self::assertContains('lightbox', $tokens['methods']);
        self::assertContains('html', $tokens['methods']);
        self::assertNotContains('cropResize', $tokens['functions']);
    }

    public function testExtractTwigTokens_ExcludesLocalMacros(): void
    {
        $tokens = Security::extractTwigTokens(
            "{% macro field(name) %}{{ name }}{% endmacro %}{{ field('x') }} {{ real_fn() }}"
        );

        // A locally-declared macro is not a sandbox function; a real call is.
        self::assertNotContains('field', $tokens['functions']);
        self::assertContains('real_fn', $tokens['functions']);
    }

    public function testScanContentTwigUsage_ReportsOnlyNotAllowedTokens(): void
    {
        $usage = Security::scanContentTwigUsage($this->leakPages());

        // `upper` is allowed and `if` is structural → not reported.
        self::assertArrayNotHasKey('upper', $usage['filters']);
        self::assertArrayNotHasKey('if', $usage['tags']);

        // The not-allowed filter and function ARE reported, with the route.
        self::assertArrayHasKey('frobnicate', $usage['filters']);
        self::assertContains('/scan', $usage['filters']['frobnicate']);
        self::assertArrayHasKey('evaluate', $usage['functions']);
        self::assertContains('/scan', $usage['functions']['evaluate']);
    }
}
