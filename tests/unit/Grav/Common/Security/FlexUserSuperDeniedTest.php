<?php

use Codeception\Util\Fixtures;
use Grav\Common\Flex\Types\UserGroups\UserGroupCollection;
use Grav\Common\Flex\Types\Users\UserObject;
use Grav\Common\Grav;
use Grav\Framework\Flex\Flex;

/**
 * UserObject whose groups are handed in, so authorize() runs against real group
 * objects without a groups.yaml in the test site.
 */
class SuperDeniedStubUserObject extends UserObject
{
    public ?UserGroupCollection $stubGroups = null;

    protected function getGroups()
    {
        return $this->stubGroups;
    }

    public function isSuperTarget(): bool
    {
        return $this->hasSuperAccess();
    }
}

/**
 * A rule in the account wins over the rules of its groups. `admin.super: false` is such
 * a rule, so a group that allows super must not answer "yes" to every action it has no
 * rule for -- `api.super` included, which the super-admin account guard accepts.
 */
class FlexUserSuperDeniedTest extends \PHPUnit\Framework\TestCase
{
    private function member(array $access): SuperDeniedStubUserObject
    {
        $grav = Fixtures::get('grav');
        /** @var Grav $grav */
        $grav = $grav();

        $flex = new Flex([], ['object' => $grav['config']->get('system.flex', [])]);
        $flex->addDirectoryType('user-accounts', 'blueprints://flex/user-accounts.yaml', ['enabled' => true]);
        $flex->addDirectoryType('user-groups', 'blueprints://flex/user-groups.yaml', ['enabled' => true]);

        $groups = $flex->getDirectory('user-groups');
        $list = [
            'administrators' => $groups->createObject([
                'groupname' => 'administrators',
                'access' => ['admin' => ['login' => true, 'super' => true, 'pages' => true]],
            ], 'administrators'),
        ];

        $user = new SuperDeniedStubUserObject(['access' => $access], 'junior', $flex->getDirectory('user-accounts'));
        /** @var UserGroupCollection $collection */
        $collection = $groups->createCollection($list);
        $user->stubGroups = $collection;

        return $user;
    }

    public function testMemberOfSuperGroupIsSuper(): void
    {
        $user = $this->member(['site' => ['login' => true]]);

        self::assertTrue($user->authorize('admin.super', 'test'));
        self::assertTrue($user->authorize('api.super', 'test'));
        self::assertTrue($user->authorize('admin.users.update', 'test'));
    }

    public function testSuperDeniedInAccountIsNotInheritedFromGroup(): void
    {
        $user = $this->member(['admin' => ['super' => false]]);

        self::assertFalse($user->authorize('admin.super', 'test'));
        self::assertFalse($user->authorize('api.super', 'test'));
        self::assertFalse($user->authorize('admin.users.update', 'test'));
    }

    public function testSuperDeniedInAccountKeepsWhatTheGroupSpellsOut(): void
    {
        $user = $this->member(['admin' => ['super' => false]]);

        self::assertTrue($user->authorize('admin.login', 'test'));
        self::assertTrue($user->authorize('admin.pages', 'test'));
    }

    public function testMemberOfSuperGroupStaysBehindTheSuperAdminGuard(): void
    {
        self::assertTrue($this->member([])->isSuperTarget());
        self::assertTrue($this->member(['admin' => ['super' => false]])->isSuperTarget());
    }
}
