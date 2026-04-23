<?php

/**
 * Class SvgXxeSecurityTest
 *
 * Covers: GHSA-3446-6mgw-f79p (XXE via SVG upload). Grav reads dimensions
 * from uploaded SVGs via simplexml_load_string in VectorImageMedium; the
 * hardening pre-strips DOCTYPE/ENTITY declarations and parses with
 * LIBXML_NONET so attacker-controlled `SYSTEM` entity references can't
 * pull in `/etc/passwd` or trigger network requests.
 *
 * The hardening is applied in two places: VectorImageMedium itself and
 * (independently) the dom-sanitizer library. This test file exercises the
 * parsing primitives that both rely on; a separate test in dom-sanitizer's
 * own suite covers that side.
 *
 * Naming convention: test{Method}_{GHSA_ID}_{description}
 */
class SvgXxeSecurityTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Mirror VectorImageMedium's hardened SVG parse so the test exercises
     * the exact strip-and-parse sequence we ship.
     */
    private static function safeParseSvg(string $content): ?\SimpleXMLElement
    {
        $content = preg_replace('/<!DOCTYPE\b[^>]*(?:\[[^\]]*\])?[^>]*>/is', '', $content) ?? $content;
        $content = preg_replace('/<!ENTITY\b[^>]*>/i', '', $content) ?? $content;

        $previousEntityLoader = null;
        if (\PHP_VERSION_ID < 80000 && function_exists('libxml_disable_entity_loader')) {
            $previousEntityLoader = libxml_disable_entity_loader(true);
        }
        try {
            $xml = simplexml_load_string($content, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        } finally {
            if ($previousEntityLoader !== null) {
                libxml_disable_entity_loader($previousEntityLoader);
            }
        }
        return $xml === false ? null : $xml;
    }

    /**
     * After DOCTYPE/ENTITY stripping, any `&name;` reference in the body
     * becomes an undefined entity. simplexml may then refuse to parse the
     * doc entirely (returning null) — which is itself a safe outcome:
     * nothing was expanded, nothing leaked. We accept either result and
     * focus the assertions on what MUST NOT happen (file contents leaking
     * into the output).
     */
    public function testParse_GHSA3446_DoesNotExpandExternalSystemEntity(): void
    {
        $payload = <<<'SVG'
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE svg [
  <!ENTITY xxe SYSTEM "file:///etc/passwd">
]>
<svg xmlns="http://www.w3.org/2000/svg" width="&xxe;" height="100">
  <text>&xxe;</text>
</svg>
SVG;
        $xml = self::safeParseSvg($payload);

        if ($xml === null) {
            // Parser refused the doc (undefined &xxe; after strip) — safe.
            $this->addToAssertionCount(1);
            return;
        }

        // If a parser was lenient enough to accept it, the entity must NOT
        // have been substituted with /etc/passwd contents.
        $width = (string) $xml->attributes()->width;
        $textContent = (string) $xml->text;
        self::assertStringNotContainsString('root:x:', $width, 'must not have read /etc/passwd into the width attribute');
        self::assertStringNotContainsString('root:x:', $textContent, 'must not have read /etc/passwd into <text>');
        self::assertStringNotContainsString('/bin/', $width);
        self::assertStringNotContainsString('/bin/', $textContent);
    }

    public function testParse_GHSA3446_BillionLaughsDoesNotExpand(): void
    {
        $payload = <<<'SVG'
<?xml version="1.0"?>
<!DOCTYPE lolz [
  <!ENTITY lol "lol">
  <!ENTITY lol2 "&lol;&lol;&lol;&lol;&lol;&lol;&lol;&lol;&lol;&lol;">
  <!ENTITY lol3 "&lol2;&lol2;&lol2;&lol2;&lol2;&lol2;&lol2;&lol2;&lol2;&lol2;">
]>
<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100">
  <text>&lol3;</text>
</svg>
SVG;
        $startPeak = memory_get_peak_usage();
        $xml = self::safeParseSvg($payload);
        $delta = memory_get_peak_usage() - $startPeak;

        // The DoS angle: bounded memory regardless of whether the parser
        // returns a SimpleXMLElement (lenient) or null (strict).
        self::assertLessThan(1024 * 1024, $delta, 'parsing must not allocate megabytes from entity expansion');

        // If the parser DID accept it, the lol3 reference must not have been
        // expanded into hundreds of `lol`s.
        if ($xml !== null) {
            $textContent = (string) $xml->text;
            self::assertLessThan(50, strlen($textContent), 'entities must not have expanded');
        } else {
            $this->addToAssertionCount(1);
        }
    }

    public function testParse_GHSA3446_PlainSvgWidthHeightStillParsed(): void
    {
        $xml = self::safeParseSvg('<svg xmlns="http://www.w3.org/2000/svg" width="42" height="84"><rect/></svg>');
        self::assertNotNull($xml);
        self::assertSame('42', (string) $xml->attributes()->width);
        self::assertSame('84', (string) $xml->attributes()->height);
    }

    public function testParse_GHSA3446_PlainSvgViewBoxStillParsed(): void
    {
        $xml = self::safeParseSvg('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 150"><rect/></svg>');
        self::assertNotNull($xml);
        self::assertSame('0 0 200 150', (string) $xml->attributes()->viewBox);
    }
}
