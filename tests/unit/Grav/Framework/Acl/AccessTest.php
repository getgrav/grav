<?php

use Grav\Framework\Acl\Access;

/**
 * The compact permission string a page keeps in `header.permissions.groups` (for example `cru-d`).
 * A `+` or `-` sets the state for every letter after it, until the next sign. With no sign a letter is allowed.
 */
class AccessTest extends \PHPUnit\Framework\TestCase
{
    private const RULES = [
        'c' => 'create',
        'r' => 'read',
        'u' => 'update',
        'd' => 'delete',
        'p' => 'publish',
        'l' => 'list',
    ];

    /**
     * @dataProvider providerPermissionStrings
     * @param string $string
     * @param array $expected
     */
    public function testPermissionString(string $string, array $expected): void
    {
        $access = new Access($string, self::RULES);

        self::assertSame($expected, $access->getAllActions());
    }

    /**
     * @dataProvider providerPermissionStrings
     * @param string $string
     * @param array $expected
     */
    public function testAuthorize(string $string, array $expected): void
    {
        $access = new Access($string, self::RULES);

        foreach (self::RULES as $action) {
            self::assertSame($expected[$action] ?? null, $access->authorize($action), "{$string}: {$action}");
        }
    }

    /**
     * What the admin panel writes when C, U and D are set to deny and R to allow.
     */
    public function testDeniedRunDoesNotAllowLaterLetters(): void
    {
        $access = new Access('-c+r-ud', self::RULES);

        self::assertFalse($access->authorize('create'));
        self::assertTrue($access->authorize('read'));
        self::assertFalse($access->authorize('update'));
        self::assertFalse($access->authorize('delete'));
    }

    public static function providerPermissionStrings(): array
    {
        return [
            'all allowed' => ['crud', ['create' => true, 'read' => true, 'update' => true, 'delete' => true]],
            'explicit plus' => ['+crud', ['create' => true, 'read' => true, 'update' => true, 'delete' => true]],
            'single deny' => ['-d', ['delete' => false]],
            'allow then deny' => ['cru-d', ['create' => true, 'read' => true, 'update' => true, 'delete' => false]],
            'deny run' => ['-ud', ['update' => false, 'delete' => false]],
            'deny run then allow' => ['-cu+rd', ['create' => false, 'update' => false, 'read' => true, 'delete' => true]],
            'admin panel output' => ['-c+r-ud', ['create' => false, 'read' => true, 'update' => false, 'delete' => false]],
            'every letter denied' => ['-c-r-u-d', ['create' => false, 'read' => false, 'update' => false, 'delete' => false]],
            'every letter denied as one run' => ['-crud', ['create' => false, 'read' => false, 'update' => false, 'delete' => false]],
            'deny, allow, deny' => ['-c+ru-d', ['create' => false, 'read' => true, 'update' => true, 'delete' => false]],
            'publish and list' => ['-pl+r', ['publish' => false, 'list' => false, 'read' => true]],
        ];
    }
}
