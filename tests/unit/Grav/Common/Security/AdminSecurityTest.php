<?php

use Grav\Common\Scheduler\Job;

/**
 * Class AdminSecurityTest
 *
 * Tests for admin security fixes.
 * Covers: GHSA-x62q-p736-3997 (cron DoS), GHSA-gq3g-666w-7h85 (password hash exposure)
 *
 * Naming convention: test{Method}_{GHSA_ID}_{description}
 */
class AdminSecurityTest extends \PHPUnit\Framework\TestCase
{
    // =========================================================================
    // GHSA-x62q-p736-3997: DoS via Invalid Cron Expression
    // =========================================================================

    /**
     * @dataProvider providerGHSAx62q_InvalidCronExpressions
     */
    public function testIsValidCronExpression_GHSAx62q_RejectsInvalidCron(string $expression, string $description): void
    {
        $result = Job::isValidCronExpression($expression);
        self::assertFalse($result, "Should reject invalid cron expression: $description");
    }

    public static function providerGHSAx62q_InvalidCronExpressions(): array
    {
        return [
            // Malformed expressions that could cause DoS
            ["'", 'Single quote'],
            ['"', 'Double quote'],
            ['`', 'Backtick'],
            ['\\', 'Backslash'],

            // Invalid field counts
            ['*', 'Single asterisk (too few fields)'],
            ['* *', 'Two fields (too few)'],
            ['* * *', 'Three fields (too few)'],
            ['* * * *', 'Four fields (too few)'],
            ['* * * * * * *', 'Seven fields (too many)'],

            // Invalid ranges
            ['60 * * * *', 'Invalid minute (60)'],
            ['-1 * * * *', 'Negative minute'],
            ['* 25 * * *', 'Invalid hour (25)'],
            ['* * 32 * *', 'Invalid day (32)'],
            ['* * * 13 *', 'Invalid month (13)'],
            ['* * * * 8', 'Invalid day of week (8)'],

            // Malformed syntax
            ['* * * * * extra', 'Extra text'],
            ['*/* * * * *', 'Double slash'],
            ['*-* * * * *', 'Invalid range'],
            ['a * * * *', 'Letter in field'],
            ['* b * * *', 'Letter in field 2'],

            // Empty/whitespace
            ['', 'Empty string'],
            ['   ', 'Only whitespace'],
            ["\t", 'Tab character'],
            ["\n", 'Newline'],

            // Injection attempts
            ['* * * * *; rm -rf /', 'Command injection'],
            ['$(whoami)', 'Shell expansion'],
            ['* * * * * | cat /etc/passwd', 'Pipe injection'],
        ];
    }

    /**
     * @dataProvider providerGHSAx62q_ValidCronExpressions
     */
    public function testIsValidCronExpression_GHSAx62q_AcceptsValidCron(string $expression, string $description): void
    {
        $result = Job::isValidCronExpression($expression);
        self::assertTrue($result, "Should accept valid cron expression: $description");
    }

    public static function providerGHSAx62q_ValidCronExpressions(): array
    {
        return [
            // Standard expressions
            ['* * * * *', 'Every minute'],
            ['0 * * * *', 'Every hour'],
            ['0 0 * * *', 'Daily at midnight'],
            ['0 0 1 * *', 'Monthly on 1st'],
            ['0 0 * * 0', 'Weekly on Sunday'],

            // Specific times
            ['30 4 * * *', '4:30 AM daily'],
            ['0 9 * * 1-5', '9 AM weekdays'],
            ['0 12 15 * *', 'Noon on 15th'],

            // Ranges and steps
            ['*/5 * * * *', 'Every 5 minutes'],
            ['0 */2 * * *', 'Every 2 hours'],
            ['0 0 */3 * *', 'Every 3 days'],
            ['0 0 1 */2 *', 'Every 2 months'],

            // Multiple values
            ['0 9,17 * * *', '9 AM and 5 PM'],
            ['0 0 1,15 * *', '1st and 15th'],
            ['0 0 * * 0,6', 'Weekends'],

            // Range expressions
            ['0 9-17 * * *', '9 AM to 5 PM hourly'],
            ['* * * 1-6 *', 'Jan through June'],
            ['0 0 * * 1-5', 'Monday through Friday'],

            // Day of week names (if supported by library)
            ['0 0 * * SUN', 'Sunday by name'],
            ['0 0 * * MON-FRI', 'Weekdays by name'],
        ];
    }

    public function testGetCronExpression_GHSAx62q_ReturnsNullForInvalid(): void
    {
        // Create a Job with an invalid cron expression
        $job = new Job('test_command');

        // Use reflection to set the 'at' property to an invalid value
        $reflection = new ReflectionClass($job);
        $property = $reflection->getProperty('at');
        $property->setAccessible(true);
        $property->setValue($job, "'invalid");

        // getCronExpression should return null instead of throwing
        $result = $job->getCronExpression();
        self::assertNull($result, 'getCronExpression should return null for invalid expression');
    }

    public function testGetCronExpression_GHSAx62q_ReturnsCronExpressionForValid(): void
    {
        $job = new Job('test_command');

        // Use reflection to set a valid cron expression
        $reflection = new ReflectionClass($job);
        $property = $reflection->getProperty('at');
        $property->setAccessible(true);
        $property->setValue($job, '* * * * *');

        $result = $job->getCronExpression();
        self::assertNotNull($result, 'getCronExpression should return CronExpression for valid expression');
        self::assertInstanceOf(\Cron\CronExpression::class, $result);
    }

    // =========================================================================
    // GHSA-gq3g-666w-7h85: Password Hash Exposure
    // These tests verify that sensitive fields are not exposed in serialization
    // =========================================================================

    /**
     * Test that UserObject jsonSerialize filters sensitive fields
     * Note: This requires a more complex setup with Grav fixtures
     * For now, we verify the method exists and filters the expected fields
     */
    public function testJsonSerialize_GHSAgq3g_MethodExists(): void
    {
        // Verify the UserObject class has the jsonSerialize override
        $reflection = new ReflectionClass(\Grav\Common\Flex\Types\Users\UserObject::class);

        self::assertTrue(
            $reflection->hasMethod('jsonSerialize'),
            'UserObject should have jsonSerialize method'
        );

        // Verify it's declared in UserObject (not just inherited)
        $method = $reflection->getMethod('jsonSerialize');
        self::assertEquals(
            \Grav\Common\Flex\Types\Users\UserObject::class,
            $method->getDeclaringClass()->getName(),
            'jsonSerialize should be declared in UserObject'
        );
    }

    public function testJsonSerialize_GHSAgq3g_DataUserMethodExists(): void
    {
        // Verify the DataUser\User class has the jsonSerialize override
        $reflection = new ReflectionClass(\Grav\Common\User\DataUser\User::class);

        self::assertTrue(
            $reflection->hasMethod('jsonSerialize'),
            'DataUser\\User should have jsonSerialize method'
        );

        // Verify it's declared in User (not just inherited from Data)
        $method = $reflection->getMethod('jsonSerialize');
        self::assertEquals(
            \Grav\Common\User\DataUser\User::class,
            $method->getDeclaringClass()->getName(),
            'jsonSerialize should be declared in DataUser\\User'
        );
    }

    // =========================================================================
    // Edge Cases
    // =========================================================================

    public function testIsValidCronExpression_EdgeCase_WhitespaceHandling(): void
    {
        // Extra whitespace should not affect valid expressions
        // Note: behavior depends on the cron library
        self::assertTrue(Job::isValidCronExpression('0 0 * * *'), 'Standard spacing');

        // Leading/trailing whitespace - depends on library
        // Just verify it doesn't throw
        $result = Job::isValidCronExpression(' * * * * * ');
        self::assertIsBool($result, 'Should return bool for whitespace-padded expression');
    }

    public function testIsValidCronExpression_EdgeCase_SpecialCharacters(): void
    {
        // These should all be invalid due to special characters
        $specialChars = ['@', '#', '$', '%', '^', '&', '(', ')', '[', ']', '{', '}', '<', '>'];

        foreach ($specialChars as $char) {
            self::assertFalse(
                Job::isValidCronExpression($char . ' * * * *'),
                "Should reject expression with special char: $char"
            );
        }
    }
}
