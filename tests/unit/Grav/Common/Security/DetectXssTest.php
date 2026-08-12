<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Common\Security;

/**
 * Class DetectXssTest
 *
 * Tests for Security::detectXss() — specifically the on_events regex hardening
 * for GHSA-9695-8fr9-hw5q (unquoted event handlers), with parallel coverage
 * for the same bypass pattern called out in GHSA-c2q3-p4jr-c55f and
 * GHSA-w8cg-7jcj-4vv2.
 *
 * Naming convention: test{Method}_{GHSA_ID}_{description}
 */
class DetectXssTest extends \PHPUnit\Framework\TestCase
{
    /** @var Grav */
    protected $grav;

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $this->grav = $grav();
    }

    // =========================================================================
    // GHSA-9695-8fr9-hw5q: unquoted on* handlers must be detected
    // =========================================================================

    /**
     * @dataProvider providerGHSA9695_UnquotedOnEvents
     */
    public function testDetectXss_GHSA9695_FlagsUnquotedEventHandler(string $payload, string $description): void
    {
        $result = Security::detectXss($payload);
        self::assertSame('on_events', $result, "Should flag on_events for: $description");
    }

    public static function providerGHSA9695_UnquotedOnEvents(): array
    {
        return [
            ['<img src=x onerror=alert(1)>', 'advisory PoC: unquoted onerror, no space before >'],
            ['<img src=x onerror=eval(atob(/Y/.source))>', 'advisory PoC: atob/regex.source obfuscation'],
            ['<svg onload=alert(1)>', 'unquoted onload on svg'],
            ['<body onload=alert(1)>', 'unquoted onload on body'],
            ['<a href=# onclick=alert(1)>x</a>', 'unquoted onclick'],
            // GHSA-c2q3-p4jr-c55f payload — the exact taxonomy escape sequence:
            ['</option></select><img src=x onerror=alert(1)>', 'GHSA-c2q3 select-context break + unquoted onerror'],
            // Obfuscation: whitespace inside the event name (e.g. on  error=)
            ['<img src=x on error=alert(1)>', 'whitespace between on and event name'],
        ];
    }

    /**
     * xmlns detection was split out of the on_events regex into its own rule so
     * the render-time output scan (Page::processTwig) can suppress it without
     * losing on*-handler coverage. Raw-input sanitization must still flag it —
     * just under the dedicated `xmlns` label now.
     *
     * @dataProvider providerXmlns
     */
    public function testDetectXss_FlagsXmlnsNamespaceDeclaration(string $payload, string $description): void
    {
        $result = Security::detectXss($payload);
        self::assertSame('xmlns', $result, "Should flag xmlns for: $description");
    }

    public static function providerXmlns(): array
    {
        return [
            ['<svg xmlns=http://example.com/ns>', 'unquoted xmlns'],
            ['<svg xmlns="http://www.w3.org/2000/svg">', 'quoted xmlns'],
        ];
    }

    /**
     * @dataProvider providerGHSA9695_QuotedOnEvents
     */
    public function testDetectXss_GHSA9695_StillFlagsQuotedEventHandlersAfterFix(string $payload, string $description): void
    {
        // Make sure tightening the regex didn't regress the previously-working
        // quoted forms.
        $result = Security::detectXss($payload);
        self::assertSame('on_events', $result, "Should still flag quoted on_events for: $description");
    }

    public static function providerGHSA9695_QuotedOnEvents(): array
    {
        return [
            ['<img src="x" onerror="alert(1)">', 'double-quoted onerror'],
            ["<img src='x' onerror='alert(1)'>", 'single-quoted onerror'],
            ['<body onload="document.location=\'evil\'">', 'quoted onload'],
            ['<svg onload="fetch(\'/x\')">', 'svg with quoted onload'],
        ];
    }

    // =========================================================================
    // GHSA-269c-h76q-8cxw: a `>` inside a quoted attribute value must not blind
    // the tag-body scan. `[^>]*?` stopped at the first literal `>` even when it
    // sat inside quotes (data to the HTML parser, not a tag boundary), so a
    // handler placed after it executed while the scan never saw it.
    // =========================================================================

    /**
     * @dataProvider providerGHSA269c_QuotedAngleBracketBypass
     */
    public function testDetectXss_GHSA269c_FlagsHandlerAfterQuotedAngleBracket(string $payload, string $description): void
    {
        $result = Security::detectXss($payload);
        self::assertSame('on_events', $result, "Should flag on_events for: $description");
    }

    public static function providerGHSA269c_QuotedAngleBracketBypass(): array
    {
        return [
            ['<img src=x title=">" onerror=alert(document.domain)>', 'advisory PoC: double-quoted > before onerror'],
            ["<img src=x title='>' onerror=alert(1)>", 'single-quoted > before onerror'],
            ['<img src=x alt="a>b>c" onmouseover=alert(1)>', 'multiple > inside quoted value'],
            ['<svg title=">" onload=alert(1)>', 'quoted > before onload on svg'],
        ];
    }

    /**
     * GHSA-269c-h76q-8cxw follow-up: a quoted attribute value butted directly
     * against the handler needs no separator, because after a quoted value the
     * HTML parser reconsumes the next char in the before-attribute-name state.
     * The first fix still required a delimiter char before `on`, so
     * `<img title="y"onerror=...>` slipped through even though it executes.
     *
     * @dataProvider providerGHSA269c_QuotedValueAdjacentToHandler
     */
    public function testDetectXss_GHSA269c_FlagsHandlerAdjacentToQuotedValue(string $payload, string $description): void
    {
        $result = Security::detectXss($payload);
        self::assertSame('on_events', $result, "Should flag on_events for: $description");
    }

    public static function providerGHSA269c_QuotedValueAdjacentToHandler(): array
    {
        return [
            ['<img title="y"onerror=alert(1)>', 'double-quoted value adjacent to onerror, no separator'],
            ["<img title='y'onerror=alert(1)>", 'single-quoted value adjacent to onerror, no separator'],
            ['<img title=""onerror=alert(1)>', 'empty double-quoted value adjacent to onerror'],
            ['<img src=x alt=">"onmouseover=alert(1)>', 'quoted > then adjacent onmouseover'],
        ];
    }

    /**
     * The xmlns rule shares the same quote-aware tag-body scan, so a quoted `>`
     * must not hide a later xmlns= declaration either.
     */
    public function testDetectXss_GHSA269c_FlagsXmlnsAfterQuotedAngleBracket(): void
    {
        $result = Security::detectXss('<svg title=">" xmlns="http://www.w3.org/2000/svg">');
        self::assertSame('xmlns', $result, 'quoted > must not blind the xmlns scan');
    }

    /**
     * xmlns adjacency mirror: a quoted value butted straight against xmlns=.
     */
    public function testDetectXss_GHSA269c_FlagsXmlnsAdjacentToQuotedValue(): void
    {
        $result = Security::detectXss('<svg title="y"xmlns="http://www.w3.org/2000/svg">');
        self::assertSame('xmlns', $result, 'quoted value adjacent to xmlns must be flagged');
    }

    /**
     * Control: a legitimate `>` inside a quoted attribute value, with no event
     * handler, must not be misread as an on_events hit.
     */
    public function testDetectXss_GHSA269c_DoesNotFlagBenignQuotedAngleBracket(): void
    {
        $result = Security::detectXss('<a title="a > b" href="/x">link</a>');
        self::assertNotSame('on_events', $result, 'benign quoted > must not trip on_events');
    }

    // =========================================================================
    // Negative coverage: legitimate content should not trip on_events
    // =========================================================================

    /**
     * @dataProvider providerSafeContent
     */
    public function testDetectXss_SafeContentReturnsNullOnEventsRule(string $payload, string $description): void
    {
        // Some safe content may still trip OTHER rules (e.g. the dangerous_tags
        // list), but the on_events rule specifically should not fire.
        $result = Security::detectXss($payload);
        self::assertNotSame('on_events', $result, "on_events must not fire for: $description");
    }

    public static function providerSafeContent(): array
    {
        return [
            ['<p>Hello world</p>', 'plain paragraph'],
            ['<a href="https://example.com">link</a>', 'link with href'],
            ['<img src="/foo.png" alt="bar">', 'plain img'],
            ['Pricing on demand', 'word starting with "on" outside any tag'],
            ['<button>Click me</button>', 'button tag (ends in "on")'],
            ['<section>content</section>', 'section tag'],
        ];
    }

    // =========================================================================
    // GHSA-w8cg-7jcj-4vv2: svg/math added to default dangerous_tags
    // =========================================================================

    /**
     * @dataProvider providerGHSAw8cg_NewlyDangerousTags
     */
    public function testDetectXss_GHSAw8cg_FlagsNewlyDangerousTags(string $payload, string $description): void
    {
        $result = Security::detectXss($payload);
        // Either dangerous_tags (new) or on_events (already covered by #1) is
        // an acceptable trip — both indicate the payload is flagged.
        self::assertNotNull($result, "Should flag: $description");
        self::assertContains(
            $result,
            ['dangerous_tags', 'on_events'],
            "Expected dangerous_tags or on_events for: $description, got '$result'"
        );
    }

    public static function providerGHSAw8cg_NewlyDangerousTags(): array
    {
        return [
            ['<svg><script>alert(1)</script></svg>', 'GHSA-w8cg svg with embedded script'],
            ['<svg></svg>', 'svg tag alone'],
            ['<math><mtext>x</mtext></math>', 'math tag (similar XML namespace risk)'],
        ];
    }

    // =========================================================================
    // GHSA-c2q3-p4jr-c55f: option/select were removed from default dangerous_tags
    // (security-config audit 2026-08-12). The tags carry no script capability;
    // the stored-XSS sink was fixed at source (grav-plugin-form select.html.twig
    // dropped its |raw filters) and the real attack payload — a context break
    // followed by an unquoted event handler — is still caught by on_events.
    // These tests lock in that split: bare option/select no longer trips the
    // heuristic, but the combined attack sequence still does.
    // =========================================================================

    /**
     * @dataProvider providerC2q3_BareOptionSelectNotFlagged
     */
    public function testDetectXss_C2q3_BareOptionSelectNotFlagged(string $payload, string $description): void
    {
        self::assertNull(
            Security::detectXss($payload),
            "Bare option/select markup should no longer be flagged: $description"
        );
    }

    public static function providerC2q3_BareOptionSelectNotFlagged(): array
    {
        return [
            ['</option></select>injected', 'option/select context break, no handler'],
            ['<select><option>x</option></select>', 'option/select wrapping'],
        ];
    }

    public function testDetectXss_C2q3_CombinedAttackStillFlagged(): void
    {
        // The exact taxonomy escape sequence from the advisory: the select-context
        // break is inert, but the unquoted onerror handler still trips on_events.
        self::assertSame(
            'on_events',
            Security::detectXss('</option></select><img src=x onerror=alert(1)>'),
            'GHSA-c2q3 real attack payload must still be flagged via on_events'
        );
    }
}
