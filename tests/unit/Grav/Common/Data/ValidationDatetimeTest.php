<?php

use Grav\Common\Data\Validation;

/**
 * Datetime fields have to accept the values Grav itself stores.
 *
 * YAML reads an unquoted `date: 2026-03-17T16:30:00` as an integer, so a page
 * header date written the way the docs show it comes back out of storage as a
 * Unix timestamp rather than a string. typeDatetime() rejected every non-string
 * outright, which meant a form that posted an untouched date straight back was
 * refused with `Invalid input in "Date"` even though nothing about the value had
 * changed. Utils::date2timestamp(), which is what the page objects actually
 * call, has always taken int and float through unchanged.
 */
class ValidationDatetimeTest extends \PHPUnit\Framework\TestCase
{
    public function testUnixTimestampIntegerIsAccepted(): void
    {
        // 2026-03-17T16:30:00, exactly what Symfony's YAML parser hands back
        // for an unquoted header date.
        self::assertTrue(Validation::typeDatetime(1773765000, [], []));
    }

    public function testUnixTimestampIsAcceptedEvenWhenAFormatIsDeclared(): void
    {
        // The format constrains how a *string* has to be written. A timestamp
        // has no string form to check, and it is already normalized.
        $params = ['format' => 'd.m.Y H:i:s'];

        self::assertTrue(Validation::typeDatetime(1773765000, $params, []));
        self::assertTrue(Validation::typeDate(1773765000, [], []));
        self::assertTrue(Validation::typeDatetimeLocal(1773765000, [], []));
    }

    public function testFloatTimestampIsAcceptedButNanAndInfAreNot(): void
    {
        self::assertTrue(Validation::typeDatetime(1773765000.0, [], []));
        self::assertFalse(Validation::typeDatetime(NAN, [], []));
        self::assertFalse(Validation::typeDatetime(INF, [], []));
    }

    public function testDateTimeImmutableIsAccepted(): void
    {
        // Only the mutable DateTime was allowed through before, so anything
        // handing over the immutable one failed for no reason.
        self::assertTrue(Validation::typeDatetime(new DateTime('2026-03-17 16:30:00'), [], []));
        self::assertTrue(Validation::typeDatetime(new DateTimeImmutable('2026-03-17 16:30:00'), [], []));
    }

    public function testGenuinelyInvalidValuesAreStillRejected(): void
    {
        self::assertFalse(Validation::typeDatetime('not a date', [], []));
        self::assertFalse(Validation::typeDatetime(true, [], []));
        self::assertFalse(Validation::typeDatetime(['2026-03-17'], [], []));
        self::assertFalse(Validation::typeDatetime(new stdClass(), [], []));
    }

    public function testStringValidationIsUnchanged(): void
    {
        self::assertTrue(Validation::typeDatetime('2026-03-17 16:30', [], []));
        self::assertTrue(Validation::typeDatetime('2026-03-17T16:30:00', [], []));

        // The strict round-trip against a declared format still bites.
        $params = ['format' => 'd.m.Y H:i:s'];
        self::assertTrue(Validation::typeDatetime('17.03.2026 16:30:00', $params, []));
        self::assertFalse(Validation::typeDatetime('2026-03-17 16:30:00', $params, []));
    }

    public function testTimestampRoundTripPassesFullFieldValidation(): void
    {
        // The end-to-end shape of the bug: the page blueprint's `header.date`
        // field, handed back the integer the API just served for it.
        $field = [
            'name' => 'header.date',
            'label' => 'Date',
            'type' => 'datetime',
        ];

        self::assertSame([], Validation::validate(1773765000, $field));
    }
}
