<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Framework\Form\FormFlash;

/**
 * Class FormFlashSecurityTest
 *
 * Covers: GHSA-hmcx-ch82-3fv2 (unauthenticated path traversal / arbitrary
 * directory creation via FormFlash session_id / unique_id).
 *
 * Naming convention: test{Method}_{GHSA_ID}_{description}
 */
class FormFlashSecurityTest extends \PHPUnit\Framework\TestCase
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
    // GHSA-hmcx-ch82-3fv2: Unauthenticated path traversal in FormFlash
    // =========================================================================

    /**
     * @dataProvider providerGHSAhmcx_TraversalIds
     */
    public function testConstruct_GHSAhmcx_RejectsTraversalSessionId(string $id, string $description): void
    {
        $flash = new FormFlash([
            'session_id' => $id,
            'unique_id' => 'abcdef1234567890',
            'form_name' => 'test',
        ]);

        $this->assertSame('', $flash->getSessionId(), $description);
        $this->assertSame('', $flash->getTmpDir(), "tmp dir must be empty when session id is rejected: {$description}");
    }

    /**
     * @dataProvider providerGHSAhmcx_TraversalIds
     */
    public function testConstruct_GHSAhmcx_RejectsTraversalUniqueId(string $id, string $description): void
    {
        $flash = new FormFlash([
            'session_id' => 'abcdef1234567890',
            'unique_id' => $id,
            'form_name' => 'test',
        ]);

        $this->assertSame('', $flash->getUniqueId(), $description);
        $this->assertSame('', $flash->getTmpDir(), "tmp dir must be empty when unique id is rejected: {$description}");
    }

    /**
     * @dataProvider providerGHSAhmcx_ValidIds
     */
    public function testConstruct_GHSAhmcx_AcceptsValidIds(string $id, string $description): void
    {
        $flash = new FormFlash([
            'session_id' => $id,
            'unique_id' => $id,
            'form_name' => 'test',
        ]);

        $this->assertSame($id, $flash->getSessionId(), $description);
        $this->assertSame($id, $flash->getUniqueId(), $description);
    }

    public static function providerGHSAhmcx_TraversalIds(): array
    {
        return [
            ['../../user/config/proof_dir', 'parent-dir traversal (advisory PoC)'],
            ['..', 'bare parent-dir'],
            ['foo/../bar', 'embedded traversal'],
            ['foo/bar', 'embedded forward slash'],
            ['foo\\bar', 'embedded backslash'],
            ['foo:bar', 'colon (stream prefix)'],
            ['foo.bar', 'embedded dot'],
            ['tmp://forms/abc', 'explicit stream url'],
            ['%2e%2e%2f', 'url-encoded traversal'],
            [str_repeat('a', 65), 'overlong (>64 chars)'],
            ["abc\x00def", 'null byte'],
            ["abc\ndef", 'newline'],
            [' abc', 'leading space'],
        ];
    }

    public static function providerGHSAhmcx_ValidIds(): array
    {
        return [
            ['abc123', 'alphanumeric'],
            ['ABCdef123', 'mixed case'],
            ['session-id-with-dashes', 'hyphens'],
            ['session_id_with_underscores', 'underscores'],
            ['session,id,with,commas', 'commas (PHP 5-bit session id)'],
            [str_repeat('a', 64), 'max length (64 chars)'],
        ];
    }
}
