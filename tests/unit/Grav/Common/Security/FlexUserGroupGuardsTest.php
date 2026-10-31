<?php

use Grav\Common\Data\Data;
use Grav\Common\Flex\Types\UserGroups\UserGroupObject;
use Grav\Common\User\Access;
use Grav\Common\User\Interfaces\UserInterface;
use Grav\Framework\Flex\FlexDirectory;

/**
 * UserGroupObject with the Flex directory and access map stubbed out, so the
 * authorization override can be driven without booting Flex.
 */
class GuardStubUserGroupObject extends UserGroupObject
{
    public static ?FlexDirectory $stubDirectory = null;
    public array $stubAccess = [];
    public bool $stubEnabled = true;

    public function __construct(array $elements = [], $key = '', ?FlexDirectory $directory = null, bool $validate = false)
    {
    }

    public function getFlexDirectory(): FlexDirectory
    {
        return self::$stubDirectory;
    }

    public function getProperty($property, $default = null)
    {
        return $property === 'enabled' ? $this->stubEnabled : $default;
    }

    protected function getAccess(): Access
    {
        return new Access($this->stubAccess);
    }

    public function authorizeFor(UserInterface $user, string $action): ?bool
    {
        return $this->isAuthorizedOverride($user, $action, 'admin', false);
    }
}

/**
 * An account can be a super-admin through a group alone. Whoever may update or
 * delete that group decides whether its members stay super-admins, so the group
 * sits in the same tier as their accounts, which UserObject::isAuthorizedOverride()
 * keeps out of a user manager's reach.
 */
class FlexUserGroupGuardsTest extends \PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        $directory = $this->createMock(FlexDirectory::class);
        $directory->method('getConfig')->willReturn(new Data([]));
        $directory->method('getAuthorizeRule')->willReturnCallback(
            static fn(string $scope, string $action): string => "{$scope}.users.{$action}"
        );
        GuardStubUserGroupObject::$stubDirectory = $directory;
    }

    /** An actor that is not super but holds the user-management rule. */
    private function manager(): UserInterface
    {
        $user = $this->createMock(UserInterface::class);
        $user->method('authorize')->willReturnCallback(
            static fn(string $action, ?string $scope = null): ?bool => str_starts_with($action, 'admin.users')
        );

        return $user;
    }

    private function superAdmin(): UserInterface
    {
        $user = $this->createMock(UserInterface::class);
        $user->method('authorize')->willReturn(true);

        return $user;
    }

    private function group(array $access, bool $enabled = true): GuardStubUserGroupObject
    {
        $group = new GuardStubUserGroupObject();
        $group->stubAccess = $access;
        $group->stubEnabled = $enabled;

        return $group;
    }

    public function testManagerCanStillUpdateAndDeleteRegularGroup(): void
    {
        $group = $this->group(['site' => ['login' => true], 'admin' => ['login' => true]]);

        self::assertTrue($group->authorizeFor($this->manager(), 'update'));
        self::assertTrue($group->authorizeFor($this->manager(), 'delete'));
    }

    public function testManagerCannotUpdateOrDeleteSuperAdminGroup(): void
    {
        foreach (['admin', 'api'] as $scope) {
            $group = $this->group([$scope => ['super' => true]]);

            self::assertFalse($group->authorizeFor($this->manager(), 'update'));
            self::assertFalse($group->authorizeFor($this->manager(), 'delete'));
        }
    }

    /**
     * The guard reads the access map, not authorize(), so switching the group off
     * does not take it out of the super-admin tier.
     */
    public function testManagerCannotUpdateOrDeleteSuperAdminGroupThatIsSwitchedOff(): void
    {
        $group = $this->group(['admin' => ['super' => true]], false);

        self::assertNull($group->authorize('admin.super'));
        self::assertFalse($group->authorizeFor($this->manager(), 'update'));
        self::assertFalse($group->authorizeFor($this->manager(), 'delete'));
    }

    public function testManagerCanStillReadSuperAdminGroup(): void
    {
        $group = $this->group(['admin' => ['super' => true]]);

        self::assertTrue($group->authorizeFor($this->manager(), 'read'));
    }

    public function testSuperAdminCanUpdateAndDeleteSuperAdminGroup(): void
    {
        $group = $this->group(['admin' => ['super' => true]]);

        self::assertTrue($group->authorizeFor($this->superAdmin(), 'update'));
        self::assertTrue($group->authorizeFor($this->superAdmin(), 'delete'));
    }
}
