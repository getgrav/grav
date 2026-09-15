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

    /**
     * The reported case from #3585 and its neighbours. These are exact multiples of
     * the step away from the minimum, and every one of them was rejected while the
     * check ran through binary floats.
     */
    public function testDecimalStepAcceptsExactMultiples(): void
    {
        self::assertTrue(Validation::typeNumber('81.96', ['min' => -90, 'step' => 0.0000001], []));
        self::assertTrue(Validation::typeNumber('81.96', ['min' => -90, 'step' => 0.00001], []));
        self::assertTrue(Validation::typeNumber('81.9', ['min' => -90, 'step' => 0.0000001], []));
        self::assertTrue(Validation::typeNumber('81.9692', ['min' => -90, 'step' => 0.0000001], []));
    }

    /**
     * Large magnitudes are where a tolerance-based comparison breaks down: scaling the
     * tolerance by the base lets it exceed the distance to the neighbouring step.
     */
    public function testDecimalStepHoldsAtLargeMagnitudes(): void
    {
        $rules = ['min' => 1000000000, 'step' => 0.000001];
        self::assertTrue(Validation::typeNumber('1000000000.000001', $rules, []));
        self::assertFalse(Validation::typeNumber('1000000000.0000005', $rules, []));
    }

    public function testOffGridValuesAreStillRejected(): void
    {
        self::assertFalse(Validation::typeNumber('2.7', ['min' => 0, 'step' => 0.5], []));
        self::assertFalse(Validation::typeNumber('0.30000000001', ['min' => 0, 'step' => 0.1], []));
    }

    /**
     * A step that does not parse as a positive number is not a grid. The HTML spec falls
     * back to the default step rather than erroring, so none of these may divide by zero.
     */
    public function testNonPositiveStepsDoNotFatal(): void
    {
        foreach (['0', 0, 0.0, '', false, 'abc', [], 'ANY ', ' any'] as $step) {
            self::assertTrue(
                Validation::typeNumber('1.5', ['step' => $step], []),
                'step ' . var_export($step, true) . ' should not restrict or fatal'
            );
        }
    }

    public function testTypeRangeSharesTheSameStepHandling(): void
    {
        self::assertTrue(Validation::typeRange('0.12345', ['step' => 'any', 'min' => 0, 'max' => 1], []));
        self::assertTrue(Validation::typeRange('1.5', ['step' => 0], []));
    }

    /**
     * typeArray()'s `multiple` branch took the remainder without casting, so `step: any`
     * was a TypeError there and `step: 0` a DivisionByZeroError.
     */
    public function testArrayStepDoesNotFatalOnNonIntegerSteps(): void
    {
        $field = ['multiple' => true];
        foreach (['any', 0, '0', '', 0.5] as $step) {
            self::assertTrue(
                Validation::typeArray([1, 2, 3], ['step' => $step], $field),
                'step ' . var_export($step, true) . ' should not restrict or fatal'
            );
        }

        self::assertFalse(Validation::typeArray([1, 2, 3], ['step' => 2], $field));
        self::assertTrue(Validation::typeArray([1, 2, 3, 4], ['step' => 2], $field));
    }

    public function testSelectSharesTheArrayStepHandling(): void
    {
        self::assertTrue(Validation::typeSelect(['a', 'b'], ['step' => 'any'], ['multiple' => true]));
    }
}
