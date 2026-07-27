<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Common\Security;

/**
 * Class DetectXssFailClosedTest
 *
 * Tests for GHSA-q2j8-x8hf-63ch and the sibling defect found while confirming
 * it. Every earlier fix in this area (GHSA-9695-8fr9-hw5q, -c2q3-p4jr-c55f,
 * -w8cg-7jcj-4vv2, -269c-h76q-8cxw) patched the regex *logic*. These two sit a
 * level below that: the PCRE engine declines to evaluate the pattern at all and
 * returns false, which the old truthiness test read as "no match".
 *
 *   1. Invalid UTF-8 — every pattern carries /u, so one stray byte anywhere in
 *      the value made preg_match() return false with PREG_BAD_UTF8_ERROR and
 *      turned the whole scan into "clean".
 *   2. JIT stack exhaustion — the quote-aware scan added for GHSA-269c gives up
 *      on a single tag body of roughly 10KB or more with
 *      PREG_JIT_STACKLIMIT_ERROR. That payload is valid UTF-8, so the JSON API
 *      layer's incidental rejection of malformed input does not cover it.
 *
 * Naming convention: test{Method}_{GHSA_ID}_{description}
 */
class DetectXssFailClosedTest extends \PHPUnit\Framework\TestCase
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
    // GHSA-q2j8-x8hf-63ch: an invalid UTF-8 byte must not disable the scan
    // =========================================================================

    /**
     * @dataProvider providerGHSAq2j8_InvalidUtf8
     */
    public function testDetectXss_GHSAq2j8_FlagsPayloadCarryingAnInvalidByte(
        string $payload,
        string $expected,
        string $description
    ): void {
        self::assertSame($expected, Security::detectXss($payload), "Should flag for: $description");
    }

    public function providerGHSAq2j8_InvalidUtf8(): array
    {
        return [
            'lone 0x80 before the tag (the reported payload)' => [
                "Hello world \x80<img src=x onerror=alert(document.cookie)>",
                'on_events',
                'invalid byte earlier in the value',
            ],
            'lone 0x80 after the tag' => [
                "<img src=x onerror=alert(1)>\x80 trailing",
                'on_events',
                'invalid byte later in the value',
            ],
            'invalid byte inside the attribute value' => [
                "<img src=\x80x onerror=alert(1)>",
                'on_events',
                'invalid byte between attributes',
            ],
            'truncated multi-byte sequence with a dangerous tag' => [
                "\xC3<script>alert(1)</script>",
                'dangerous_tags',
                'incomplete UTF-8 sequence',
            ],
        ];
    }

    /**
     * A byte glued directly to the handler name is inert: after the substitution
     * the attribute reads `<replacement>onerror`, which no browser dispatches
     * either. Pinning it so a future "just strip invalid bytes" refactor, which
     * WOULD turn this into a live handler, fails loudly here.
     */
    public function testDetectXss_GHSAq2j8_ByteGluedToHandlerStaysInert(): void
    {
        self::assertNull(Security::detectXss("<img src=x \x80onerror=alert(1)>"));
    }

    /**
     * @dataProvider providerGHSAq2j8_BenignEncoding
     */
    public function testDetectXss_GHSAq2j8_DoesNotFlagBenignContent(string $payload, string $description): void
    {
        self::assertNull(Security::detectXss($payload), "Should not flag: $description");
    }

    public function providerGHSAq2j8_BenignEncoding(): array
    {
        return [
            'plain prose' => ['Just some ordinary prose.', 'no markup at all'],
            'accented utf8' => ['Café naïve, Ünicode and 日本語', 'valid multi-byte content'],
            'mis-encoded prose' => ["Caf\xE9 in latin-1", 'invalid bytes but no payload'],
        ];
    }

    // =========================================================================
    // Sibling defect: JIT stack exhaustion on an oversized tag body
    // =========================================================================

    /**
     * @dataProvider providerJitPadding
     */
    public function testDetectXss_GHSAq2j8_FlagsHandlerBehindAnOversizedTagBody(int $padding): void
    {
        $payload = '<img ' . str_repeat('a', $padding) . ' onerror=alert(1)>';

        self::assertTrue(mb_check_encoding($payload, 'UTF-8'), 'payload must be valid UTF-8');
        self::assertSame('on_events', Security::detectXss($payload), "padding of {$padding} bytes");
    }

    public function providerJitPadding(): array
    {
        // 20k is comfortably past where the JIT stack gives out; 200 and 2000
        // passed even before the fix and guard against a regression the other way.
        return [[200], [2000], [20000], [60000]];
    }

    /**
     * The same oversized body with no handler must still come back clean, so the
     * fail-closed retry is not just blanket-flagging everything large.
     */
    public function testDetectXss_GHSAq2j8_DoesNotFlagAnOversizedBenignTagBody(): void
    {
        self::assertNull(Security::detectXss('<img ' . str_repeat('a', 20000) . ' src=x>'));
    }

    /**
     * A realistic long page is many small tags rather than one huge one, and must
     * not trip anything.
     */
    public function testDetectXss_GHSAq2j8_DoesNotFlagALongOrdinaryPage(): void
    {
        self::assertNull(Security::detectXss(str_repeat("<p>Ordinary paragraph text here.</p>\n", 3000)));
    }
}
