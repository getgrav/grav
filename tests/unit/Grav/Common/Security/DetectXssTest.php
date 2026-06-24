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
    // GHSA-w8cg-7jcj-4vv2: svg/math + GHSA-c2q3 option/select added to defaults
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
            ['</option></select>injected', 'GHSA-c2q3 option/select context break'],
            ['<select><option>x</option></select>', 'option/select wrapping'],
        ];
    }
}
