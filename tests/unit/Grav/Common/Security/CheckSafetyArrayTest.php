<?php

use Codeception\Util\Fixtures;
use Grav\Common\Data\Validation;

/**
 * Class CheckSafetyArrayTest
 *
 * Validation::checkSafety() only reports whether an array holds a flagged value.
 * It must not build the key path of every hit to find that out: the keys can
 * come from the request, and the paths cost key length times item count.
 */
class CheckSafetyArrayTest extends \PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $grav();
    }

    public function testFlaggedValueAnywhereInArrayIsReported(): void
    {
        $field = ['name' => 'note'];

        self::assertSame([], Validation::checkSafety(['a' => 'fine', 'b' => ['c' => [1, null, 'fine']]], $field));
        self::assertSame(['Note'], array_keys(Validation::checkSafety(['javascript:alert(1)'], $field)));
        self::assertSame(['Note'], array_keys(Validation::checkSafety(['a' => 'fine', 'b' => ['c' => ['fine', '<script>x</script>']]], $field)));
        self::assertCount(1, Validation::checkSafety(['javascript:alert(1)', ['javascript:alert(1)']], $field)['Note']);
    }

    public function testDisabledCheckStillSkipsArrays(): void
    {
        self::assertSame([], Validation::checkSafety(['javascript:alert(1)'], ['name' => 'note', 'xss_check' => false]));
    }

    public function testLongKeyAboveManyFlaggedValuesStaysCheap(): void
    {
        // 20,000 bytes of key above 2,000 flagged values used to hold 40 MB of paths.
        $key = str_repeat('k', 20000);
        $flagged = array_fill(0, 2000, 'javascript:alert(1)');

        memory_reset_peak_usage();
        $before = memory_get_peak_usage();

        self::assertNotEmpty(Validation::checkSafety([$key => $flagged], ['name' => 'note']));
        self::assertNotEmpty(Validation::checkSafety($flagged, ['name' => $key]));

        self::assertLessThan(8 * 1024 * 1024, memory_get_peak_usage() - $before);
    }
}
