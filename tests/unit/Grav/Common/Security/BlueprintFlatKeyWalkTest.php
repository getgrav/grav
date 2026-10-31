<?php

use Grav\Common\Data\Blueprint;

/**
 * Class BlueprintFlatKeyWalkTest
 *
 * BlueprintSchema::filterArray() drops a flat dot-notation key when one of its
 * ancestor paths is disabled or ignored. The walk over those ancestors only
 * tests prefixes that can name a field, so it stays linear however many dots
 * the key holds.
 */
class BlueprintFlatKeyWalkTest extends \PHPUnit\Framework\TestCase
{
    public function testFilter_FlatKeysBelowGuardedFieldsAreDropped(): void
    {
        $filtered = $this->blueprint()->filter([
            'name' => 'ok',
            'access.admin.super' => true,
            'header.permissions.groups.editors' => 'crud',
            'header.permissions.groups.editors.' . str_repeat('a.', 50) . 'x' => 'crud',
            'header' => ['title' => 'ok', 'permissions.groups.editors' => 'crud'],
            'other.key' => 'kept',
        ]);

        self::assertSame(['name' => 'ok', 'header' => ['title' => 'ok'], 'other.key' => 'kept'], $filtered);
    }

    public function testFilter_KeyWithManyDotsFiltersInLinearTime(): void
    {
        $blueprint = $this->blueprint();
        $key = str_repeat('.', 200000);

        $start = microtime(true);
        $filtered = $blueprint->filter([$key => 'x', 'header' => [$key => 'x']]);
        $elapsed = microtime(true) - $start;

        self::assertSame([$key => 'x', 'header' => [$key => 'x']], $filtered);
        // About 15 s with a walk over every ancestor; milliseconds with the bounded walk.
        self::assertLessThan(1.0, $elapsed, 'The ancestor walk must stay linear in the length of the key');
    }

    private function blueprint(): Blueprint
    {
        return new Blueprint(null, ['form' => ['fields' => [
            'name' => ['type' => 'text'],
            'access' => ['type' => 'array', 'validate' => ['ignore' => true]],
            'header.permissions.groups' => ['type' => 'array', 'disabled' => true],
            'header.title' => ['type' => 'text'],
        ]]], true);
    }
}
