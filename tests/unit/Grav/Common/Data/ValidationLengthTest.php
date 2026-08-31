<?php

use Grav\Common\Data\Validation;

/**
 * Length rules on text/textarea fields.
 *
 * The multiline default is a runaway guard, not a storage limit. It regressed
 * into one in 1.7.32, when a 65536 default started rejecting ordinary long
 * pages, so these cases pin down what the defaults are allowed to reject.
 *
 * @see https://github.com/getgrav/grav/issues/3643
 */
class ValidationLengthTest extends \Codeception\TestCase\Test
{
    public function testLongContentPassesTheMultilineDefault(): void
    {
        // Comfortably past the old 65536 ceiling: a long-form article, a big
        // markdown table, a historian's 22-page page.
        $value = str_repeat("Lorem ipsum dolor sit amet.\n", 20000);

        self::assertGreaterThan(65536, mb_strlen($value));
        self::assertTrue(Validation::typeTextarea($value, [], []));
    }

    public function testMaxZeroOptsOutOfTheLengthCheck(): void
    {
        $value = str_repeat('a', 3000000);

        self::assertFalse(Validation::typeTextarea($value, [], []));
        self::assertTrue(Validation::typeTextarea($value, ['max' => 0], []));
    }

    public function testExplicitMaxIsStillEnforced(): void
    {
        self::assertTrue(Validation::typeTextarea(str_repeat('a', 100), ['max' => 100], []));
        self::assertFalse(Validation::typeTextarea(str_repeat('a', 101), ['max' => 100], []));
    }

    public function testSingleLineDefaultIsUnchanged(): void
    {
        self::assertTrue(Validation::typeText(str_repeat('a', 2048), [], []));
        self::assertFalse(Validation::typeText(str_repeat('a', 2049), [], []));
    }

    public function testTooLongMessageNamesTheLengthAndTheLimit(): void
    {
        $field = [
            'name' => 'content',
            'label' => 'Content',
            'type' => 'textarea',
            'validate' => ['type' => 'textarea', 'max' => 100],
        ];

        $messages = Validation::validate(str_repeat('a', 150), $field);

        self::assertArrayHasKey('content', $messages);
        self::assertStringContainsString('150', $messages['content'][0]);
        self::assertStringContainsString('100', $messages['content'][0]);
    }

    public function testTooShortMessageNamesTheLengthAndTheLimit(): void
    {
        $field = [
            'name' => 'content',
            'label' => 'Content',
            'type' => 'textarea',
            'validate' => ['type' => 'textarea', 'min' => 100],
        ];

        $messages = Validation::validate(str_repeat('a', 10), $field);

        self::assertArrayHasKey('content', $messages);
        self::assertStringContainsString('10', $messages['content'][0]);
        self::assertStringContainsString('100', $messages['content'][0]);
    }

    public function testLengthDetailIsNotLeakedIntoAnUnrelatedFailure(): void
    {
        $field = [
            'name' => 'website',
            'label' => 'Website',
            'type' => 'url',
            'validate' => ['type' => 'url'],
        ];

        // Short enough to clear every length rule, but not a URL.
        $messages = Validation::validate('not a url', $field);

        self::assertArrayHasKey('website', $messages);
        self::assertStringNotContainsString('maximum', $messages['website'][0]);
        self::assertStringNotContainsString('minimum', $messages['website'][0]);
    }
}
