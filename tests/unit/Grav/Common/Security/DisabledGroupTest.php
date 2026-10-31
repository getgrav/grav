<?php

use Grav\Common\Grav;
use Grav\Common\User\DataUser\User as DataUser;
use Grav\Framework\Flex\Pages\Traits\PageAuthorsTrait;

/**
 * A group with `enabled: false` has no say over its members. Flex accounts get that
 * from UserGroupObject::authorize(); the code which works from the account's plain
 * list of group names has to honour the same flag: UserTrait::authorize() for regular
 * accounts and PageAuthorsTrait::isAuthorizedByGroup() for per-page group permissions.
 */
class DisabledGroupTest extends \PHPUnit\Framework\TestCase
{
    /** @var mixed */
    private $groups;

    protected function setUp(): void
    {
        $this->groups = Grav::instance()['config']->get('groups');
    }

    protected function tearDown(): void
    {
        Grav::instance()['config']->set('groups', $this->groups);
    }

    private function memberOf(array $group): DataUser
    {
        Grav::instance()['config']->set('groups', ['vip' => $group]);

        $user = new DataUser(['state' => 'enabled', 'groups' => ['vip'], 'access' => ['site' => ['login' => true]]]);
        $user->set('authenticated', true);
        $user->set('authorized', true);

        return $user;
    }

    private function pageFor(string $rules): object
    {
        return new class($rules) {
            use PageAuthorsTrait;

            public function __construct(private string $rules)
            {
            }

            public function getNestedProperty($property, $default = null)
            {
                return $property === 'header.permissions.groups' ? ['vip' => $this->rules] : $default;
            }

            public function root(): bool
            {
                return false;
            }

            public function getKey(): string
            {
                return 'lounge';
            }

            public function parent()
            {
                return null;
            }
        };
    }

    public function testEnabledGroupGrantsItsAccessMap(): void
    {
        self::assertTrue($this->memberOf(['enabled' => true, 'access' => ['site' => ['vip' => true]]])->authorize('site.vip'));
    }

    public function testGroupWithoutTheFlagCountsAsEnabled(): void
    {
        self::assertTrue($this->memberOf(['access' => ['site' => ['vip' => true]]])->authorize('site.vip'));
    }

    public function testDisabledGroupGrantsNothing(): void
    {
        $user = $this->memberOf(['enabled' => false, 'access' => ['site' => ['vip' => true], 'admin' => ['super' => true]]]);

        self::assertFalse($user->authorize('site.vip'));
        self::assertFalse($user->authorize('admin.super'));
        self::assertTrue($user->authorize('site.login'));
    }

    public function testEnabledGroupGetsItsPagePermissions(): void
    {
        self::assertTrue($this->pageFor('ru')->isParentAuthorized('update', 'admin', $this->memberOf(['enabled' => true])));
        self::assertTrue($this->pageFor('ru')->isParentAuthorized('update', 'admin', $this->memberOf([])));
        self::assertFalse($this->pageFor('r-u')->isParentAuthorized('update', 'admin', $this->memberOf([])));
    }

    public function testDisabledGroupHasNoSayInPagePermissions(): void
    {
        self::assertNull($this->pageFor('ru')->isParentAuthorized('update', 'admin', $this->memberOf(['enabled' => false])));
        self::assertNull($this->pageFor('r-u')->isParentAuthorized('update', 'admin', $this->memberOf(['enabled' => false])));
    }
}
