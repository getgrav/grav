<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Common\Twig\Extension\GravExtension;

/**
 * `safe_email` is registered as HTML-safe and is allowed in sandboxed page
 * content, so whatever it returns is printed without autoescaping. It has to
 * escape every `<`, `>`, `&` and quote itself, also when the text is not valid
 * UTF-8: a stray lead byte used to carry the bytes after it past the escaping.
 *
 * The filter picks raw text or a numeric entity at random for each character,
 * so every check runs many times.
 */
class SafeEmailInvalidUtf8Test extends \PHPUnit\Framework\TestCase
{
    /** @var Grav */
    protected $grav;

    /** @var GravExtension */
    protected $twig_ext;

    protected function setUp(): void
    {
        parent::setUp();
        $this->grav = Fixtures::get('grav');
        $this->twig_ext = new GravExtension();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function providerInvalidUtf8(): array
    {
        return [
            '4-byte lead before <' => ["\xF0<sv\xC3>"],
            'event handler' => ["\xF0<svg/\xF0 onload=alert(document.domain)//\xC3>"],
            '3-byte lead before quotes' => ["\xE2\"'x"],
            '2-byte lead before &' => ["\xC3&lt;"],
            'truncated at the end' => ["<a@b.c>\xF0"],
            'lone continuation bytes' => ["\x80<\xBF>"],
            'overlong <' => ["\xC0\xBC<\xE0\x80\xBC>"],
            'invalid lead byte' => ["\xFF<\xFE>\xF8<<<<"],
        ];
    }

    /**
     * @dataProvider providerInvalidUtf8
     */
    public function testInvalidUtf8NeverLeavesMarkupUnescaped(string $input): void
    {
        for ($i = 0; $i < 200; $i++) {
            $output = $this->twig_ext->safeEmailFilter($input);

            self::assertDoesNotMatchRegularExpression('/[<>"\']/', $output, bin2hex($input));
            // Every & starts an entity the filter wrote itself; `&#;` was what a broken chunk turned into.
            self::assertMatchesRegularExpression('/^(?:[^&]|&(?:amp|lt|gt|#\d+);)*$/', $output, bin2hex($input));
            self::assertTrue(mb_check_encoding($output, 'UTF-8'), bin2hex($input));
        }
    }

    public function testRandomBytesNeverLeaveMarkupUnescaped(): void
    {
        $alphabet = ["\xC3", "\xE2", "\xF0", "\x80", "\xFF", '<', '>', '"', "'", '&', '@', ' ', 'a', 'é', '€', '😀'];

        mt_srand(20261123);
        for ($i = 0; $i < 2000; $i++) {
            $input = '';
            for ($n = mt_rand(1, 12); $n > 0; $n--) {
                $input .= $alphabet[mt_rand(0, count($alphabet) - 1)];
            }

            $output = $this->twig_ext->safeEmailFilter($input);

            self::assertDoesNotMatchRegularExpression('/[<>"\']/', $output, bin2hex($input));
            self::assertMatchesRegularExpression('/^(?:[^&]|&(?:amp|lt|gt|#\d+);)*$/', $output, bin2hex($input));
        }
        mt_srand();
    }

    public function testValidUtf8StillDecodesToTheSameText(): void
    {
        $inputs = [
            'devs@getgrav.org',
            'Jörg Müller <jörg@exämple.org>',
            '"quoted" & \'single\' 日本語 😀',
            '',
        ];

        foreach ($inputs as $input) {
            for ($i = 0; $i < 50; $i++) {
                $output = $this->twig_ext->safeEmailFilter($input);

                self::assertDoesNotMatchRegularExpression('/[<>"\'@]/', $output);
                self::assertSame($input, html_entity_decode($output, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            }
        }
    }
}
