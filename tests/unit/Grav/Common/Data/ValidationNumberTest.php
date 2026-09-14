<?php

use Grav\Common\Data\Validation;

class ValidationNumberTest extends \PHPUnit\Framework\TestCase
{
    public function testAnyStepAllowsArbitraryDecimals(): void
    {
        self::assertTrue(Validation::typeNumber('0.12345', ['step' => 'any', 'min' => 0, 'max' => 1], []));
        self::assertTrue(Validation::typeNumber(81.96, ['step' => 'any', 'min' => -90, 'max' => 90], []));
    }

    public function testAnyStepStillEnforcesNumberAndBounds(): void
    {
        $rules = ['step' => 'any', 'min' => 0, 'max' => 1];
        self::assertFalse(Validation::typeNumber('not a number', $rules, []));
        self::assertFalse(Validation::typeNumber(-0.01, $rules, []));
        self::assertFalse(Validation::typeNumber(1.01, $rules, []));
        self::assertTrue(Validation::typeNumber(0, $rules, []));
        self::assertTrue(Validation::typeNumber(1, $rules, []));
    }

    public function testAnyStepIsCaseInsensitive(): void
    {
        self::assertTrue(Validation::typeNumber(0.12345, ['step' => 'ANY'], []));
        self::assertTrue(Validation::typeNumber(0.12345, ['step' => 'Any'], []));
    }

    public function testNumericStepStillEnforcesItsGrid(): void
    {
        $rules = ['step' => 0.01, 'min' => 0, 'max' => 1];
        self::assertTrue(Validation::typeNumber(0.7, $rules, []));
        self::assertFalse(Validation::typeNumber(0.705, $rules, []));
        self::assertTrue(Validation::typeNumber(0.12345, [], []));
    }
}
