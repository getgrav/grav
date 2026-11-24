<?php

use Codeception\Util\Fixtures;
use Grav\Common\Flex\Types\UserGroups\UserGroupObject;
use Grav\Common\Flex\Types\Users\UserObject;
use Grav\Common\Grav;
use Grav\Common\User\Access;
use Grav\Framework\Flex\Flex;

/**
 * Flex accounts and groups keep their access map as the array Access::toArray() returns, and that
 * array is what gets saved. A deny below an allowed parent (`api.system: true`,
 * `api.system.backup: false`) has to survive it, or loading the object grants the denied action
 * and saving any other field of a group removes the deny from groups.yaml.
 */
class FlexAccessOverrideRuleTest extends \PHPUnit\Framework\TestCase
{
    private const ACCESS = ['api.access' => true, 'api.system' => true, 'api.system.backup' => false];

    private function flex(): Flex
    {
        $grav = Fixtures::get('grav');
        /** @var Grav $grav */
        $grav = $grav();

        $flex = new Flex([], ['object' => $grav['config']->get('system.flex', [])]);
        $flex->addDirectoryType('user-accounts', 'blueprints://flex/user-accounts.yaml', ['enabled' => true]);
        $flex->addDirectoryType('user-groups', 'blueprints://flex/user-groups.yaml', ['enabled' => true]);

        return $flex;
    }

    public function testGroupStoresDenyBelowAllowedParent(): void
    {
        /** @var UserGroupObject $group */
        $group = $this->flex()->getDirectory('user-groups')->createObject(
            ['groupname' => 'operators', 'access' => self::ACCESS],
            'operators'
        );

        self::assertTrue($group->authorize('api.system.info'));
        self::assertFalse($group->authorize('api.system.backup'));

        // The loaded property replaces the stored map on save.
        foreach ([$group->getProperty('access'), $group->prepareStorage()['access']] as $stored) {
            $access = new Access($stored);

            self::assertTrue($access->authorize('api.system.info'));
            self::assertFalse($access->authorize('api.system.backup'));
        }
    }

    public function testAccountKeepsDenyBelowAllowedParent(): void
    {
        $user = new UserObject(['access' => self::ACCESS], 'operator', $this->flex()->getDirectory('user-accounts'));

        self::assertTrue($user->authorize('api.system.info', 'test'));
        self::assertFalse($user->authorize('api.system.backup', 'test'));
        self::assertFalse((new Access($user->prepareStorage()['access']))->authorize('api.system.backup'));
    }
}
