<?php

use Codeception\Util\Fixtures;
use Grav\Common\Data\Blueprint;
use Grav\Common\Data\Data;
use Grav\Common\Data\Validation;
use Grav\Common\Data\ValidationException;
use Grav\Common\Grav;

/**
 * A text value that is not valid UTF-8 is refused, and is never emptied by the filter.
 *
 * The same PCRE behaviour as GHSA-q2j8-x8hf-63ch: a /u pattern does not run on
 * invalid UTF-8. typeText() normalised line endings with one, measured the null
 * it got back as an empty string and so skipped `max` and the single-line rule,
 * while `required` passed on the raw bytes. filterText() then stored null, so a
 * form ran its actions with its required fields empty.
 */
class ValidationInvalidUtf8Test extends \PHPUnit\Framework\TestCase
{
    /** @var Grav */
    protected $grav;

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $this->grav = $grav();
    }

    /**
     * @dataProvider providerTextTypes
     */
    public function testValidate_InvalidUtf8IsRefused(string $type, string $value): void
    {
        $field = ['type' => $type, 'name' => 'field', 'validate' => ['required' => true, 'max' => 10]];

        self::assertArrayHasKey('field', Validation::validate($value, $field));
    }

    public static function providerTextTypes(): array
    {
        return [
            'text' => ['text', "\xff"],
            'text, longer than max and with a line break' => ['text', "\xff" . str_repeat("x\n", 50)],
            'text, truncated multibyte sequence' => ['text', "abc\xc3"],
            'textarea' => ['textarea', "\xff"],
            'email' => ['email', "\xff@"],
            'password' => ['password', "\xff"],
            'hidden' => ['hidden', "\xff"],
            'commalist' => ['commalist', "\xff"],
            'media' => ['media', "\xff"],
        ];
    }

    public function testValidate_DefaultLimitsHoldForInvalidUtf8(): void
    {
        // No explicit rule at all: the 2048 default and the single-line rule.
        self::assertFalse(Validation::typeText("\xff" . str_repeat('a', 4096), [], []));
        self::assertFalse(Validation::typeText("\xff\nsecond line", [], []));
        self::assertFalse(Validation::typeTextarea("\xff", [], []));
    }

    public function testValidate_RequiredFieldsCannotBeEmptiedByInvalidUtf8(): void
    {
        $blueprint = new Blueprint('contact', ['form' => ['fields' => [
            'name' => ['type' => 'text', 'validate' => ['required' => true]],
            'email' => ['type' => 'email', 'validate' => ['required' => true]],
            'message' => ['type' => 'textarea', 'validate' => ['required' => true]],
        ]]]);
        $blueprint->load()->init();

        $data = new Data(['name' => "\xff", 'email' => "\xff@", 'message' => "\xff"], $blueprint);

        $this->expectException(ValidationException::class);
        $data->validate();
    }

    public function testFilter_KeepsAValueItCannotDecode(): void
    {
        // `tel` has no type method, so nothing but `required` looks at the value
        // before it is filtered as text. What passed as filled in stays filled in.
        $field = ['type' => 'tel', 'name' => 'phone', 'validate' => ['required' => true]];

        self::assertSame([], Validation::validate("\xff555", $field));
        self::assertSame("\xff555", Validation::filter("\xff555", $field));
    }

    public function testFilter_StillNormalisesLineEndings(): void
    {
        $field = ['type' => 'textarea', 'name' => 'message'];

        self::assertSame("Zoë\nsaid\nhi\n", Validation::filter("Zoë\r\nsaid\rhi\n", $field));
        self::assertSame('Zoë', Validation::filter('  Zoë ', $field + ['validate' => ['trim' => true]]));
    }

    public function testValidate_ValidUtf8StillPasses(): void
    {
        $field = ['type' => 'text', 'name' => 'field', 'validate' => ['required' => true, 'max' => 10]];

        self::assertSame([], Validation::validate('Zoë Ünïcødé', ['validate' => ['required' => true, 'max' => 11]] + $field));
        self::assertSame([], Validation::validate("\u{1F600}", $field));
        self::assertTrue(Validation::typeTextarea("one\r\ntwo\rthree", ['max' => 13], []));
    }
}
