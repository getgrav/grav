<?php

use Grav\Common\Data\Blueprint;
use Grav\Common\Data\Data;
use Grav\Common\Flex\Types\UserGroups\UserGroupCollection;
use Grav\Common\Flex\Types\UserGroups\UserGroupObject;
use Grav\Common\Flex\Types\Users\UserObject;
use Grav\Common\User\Access;
use Grav\Common\User\Interfaces\UserInterface;
use Grav\Framework\Flex\FlexDirectory;

/**
 * UserObject with the Flex directory, blueprint and storage stubbed out, so the
 * authorization override and update() can be driven without booting Flex.
 */
class GuardStubUserObject extends UserObject
{
    public static ?FlexDirectory $stubDirectory = null;
    public static ?Blueprint $stubBlueprint = null;
    public string $stubStorageKey = '';
    public array $stubAccess = [];
    public ?UserGroupCollection $stubGroups = null;
    public array $written = [];
    public array $stubProperties = [];

    public function __construct(array $elements = [], $key = '', ?FlexDirectory $directory = null, bool $validate = false)
    {
    }

    public function getStorageKey(): string
    {
        return $this->stubStorageKey;
    }

    public function getFlexDirectory(): FlexDirectory
    {
        return self::$stubDirectory;
    }

    public function getBlueprint(string $name = '')
    {
        return self::$stubBlueprint;
    }

    public function getProperty($property, $default = null)
    {
        return $this->stubProperties[$property] ?? $default;
    }

    public function getTimestamp(): int
    {
        return 0;
    }

    protected function getElements()
    {
        return [];
    }

    protected function getAccess(): Access
    {
        return new Access($this->stubAccess);
    }

    protected function getGroups()
    {
        return $this->stubGroups;
    }

    public function setNestedProperty($property, mixed $value, $separator = null)
    {
        $this->written[$property] = $value;

        return $this;
    }

    public function unsetNestedProperty($property, $separator = null)
    {
        unset($this->written[$property]);

        return $this;
    }

    public function authorizeFor(UserInterface $user, string $action): ?bool
    {
        return $this->isAuthorizedOverride($user, $action, 'admin', false);
    }

    public function authorizeForSessionUser(UserInterface $user, string $action): ?bool
    {
        return $this->isAuthorizedOverride($user, $action, 'site', true);
    }
}

/**
 * UserGroupObject answering from a plain row, so authorize() runs without booting Flex.
 */
class GuardStubMemberGroup extends UserGroupObject
{
    public array $stubRow = [];

    public function __construct(array $elements = [], $key = '', ?FlexDirectory $directory = null, bool $validate = false)
    {
        $this->stubRow = $elements;
    }

    public function getProperty($property, mixed $default = null)
    {
        return $this->stubRow[$property] ?? $default;
    }

    protected function getAccess(): Access
    {
        return new Access($this->stubRow['access'] ?? []);
    }
}

class FlexUserAccountGuardsTest extends \PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        $directory = $this->createMock(FlexDirectory::class);
        $directory->method('getConfig')->willReturn(new Data([]));
        $directory->method('getAuthorizeRule')->willReturnCallback(
            static fn(string $scope, string $action): string => "{$scope}.users.{$action}"
        );
        GuardStubUserObject::$stubDirectory = $directory;

        $blueprint = $this->createMock(Blueprint::class);
        $blueprint->method('mergeData')->willReturnCallback(static fn(array $a, array $b): array => array_replace($a, $b));
        $blueprint->method('filter')->willReturnArgument(0);
        $blueprint->method('flattenData')->willReturnArgument(0);
        GuardStubUserObject::$stubBlueprint = $blueprint;
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

    private function target(array $access, array $groups = []): GuardStubUserObject
    {
        $target = new GuardStubUserObject();
        $target->stubStorageKey = 'target';
        $target->stubAccess = $access;
        $target->stubGroups = new UserGroupCollection(
            array_map(static fn(array $row): GuardStubMemberGroup => new GuardStubMemberGroup($row), $groups)
        );

        return $target;
    }

    /** The copy of the target account that a session carries. */
    private function owner(array $properties): GuardStubUserObject
    {
        $user = $this->target([]);
        $user->stubProperties = $properties;

        return $user;
    }

    public function testManagerCanStillUpdateRegularAccount(): void
    {
        $target = $this->target(['site' => ['login' => true]]);

        self::assertTrue($target->authorizeFor($this->manager(), 'update'));
    }

    public function testManagerCannotUpdateOrDeleteSuperAdminAccount(): void
    {
        $target = $this->target(['admin' => ['login' => true, 'super' => true]]);

        self::assertFalse($target->authorizeFor($this->manager(), 'update'));
        self::assertFalse($target->authorizeFor($this->manager(), 'delete'));
    }

    public function testManagerCannotUpdateApiSuperAccount(): void
    {
        $target = $this->target(['api' => ['access' => true, 'super' => true]]);

        self::assertFalse($target->authorizeFor($this->manager(), 'update'));
    }

    public function testManagerCannotUpdateAccountThatIsSuperThroughGroup(): void
    {
        foreach (['admin', 'api'] as $scope) {
            $target = $this->target(['site' => ['login' => true]], [
                'administrators' => ['access' => [$scope => ['super' => true]]],
            ]);

            self::assertFalse($target->authorizeFor($this->manager(), 'update'));
        }
    }

    public function testManagerCanStillUpdateAccountWhoseGroupsDoNotGrantSuper(): void
    {
        $target = $this->target(['site' => ['login' => true]], [
            'editors' => ['access' => ['admin' => ['login' => true, 'super' => false, 'pages' => true]]],
            'members' => ['access' => ['site' => ['login' => true]]],
        ]);

        self::assertTrue($target->authorizeFor($this->manager(), 'update'));
    }

    /**
     * Regular accounts and the API are super as soon as one group grants it, so
     * another group which denies it does not take the account out of the tier.
     */
    public function testGroupThatDeniesSuperDoesNotHideGroupThatGrantsIt(): void
    {
        $administrators = ['access' => ['admin' => ['super' => true], 'api' => ['super' => true]]];
        $editors = ['access' => ['admin' => ['super' => false], 'api' => ['super' => false]]];

        foreach ([
            ['administrators' => $administrators, 'editors' => $editors],
            ['editors' => $editors, 'administrators' => $administrators],
        ] as $groups) {
            $target = $this->target(['site' => ['login' => true]], $groups);

            self::assertFalse($target->stubGroups->authorize('admin.super'), 'deny wins in the combined answer');
            self::assertFalse($target->authorizeFor($this->manager(), 'update'));
            self::assertFalse($target->authorizeFor($this->manager(), 'delete'));
            self::assertTrue($target->authorizeFor($this->superAdmin(), 'update'));
        }
    }

    /** Whoever took the account over would be super once the group is enabled again. */
    public function testDisabledGroupThatGrantsSuperStillCounts(): void
    {
        $target = $this->target(['site' => ['login' => true]], [
            'administrators' => ['enabled' => false, 'access' => ['api' => ['super' => true]]],
        ]);

        self::assertNull($target->stubGroups->authorize('api.super'), 'a disabled group grants nothing');
        self::assertFalse($target->authorizeFor($this->manager(), 'update'));
    }

    public function testSuperAdminCanUpdateSuperAdminAccount(): void
    {
        $target = $this->target(['admin' => ['super' => true]]);

        self::assertTrue($target->authorizeFor($this->superAdmin(), 'update'));
    }

    public function testLoggedInOwnerCanUpdateButNotDeleteOwnAccount(): void
    {
        $owner = $this->owner(['state' => 'enabled', 'authenticated' => true, 'authorized' => true]);

        self::assertTrue($this->target([])->authorizeForSessionUser($owner, 'update'));
        self::assertFalse($this->target([])->authorizeForSessionUser($owner, 'delete'));
    }

    public function testOwnerWaitingForSecondFactorCannotReachOwnAccount(): void
    {
        $owner = $this->owner(['state' => 'enabled', 'authenticated' => true, 'authorized' => false]);

        self::assertFalse($this->target([])->authorizeForSessionUser($owner, 'read'));
        self::assertFalse($this->target([])->authorizeForSessionUser($owner, 'update'));
    }

    public function testOwnerWhoIsNotLoggedInCannotReachOwnAccount(): void
    {
        $owner = $this->owner(['state' => 'enabled']);

        self::assertFalse($this->target([])->authorizeForSessionUser($owner, 'update'));
    }

    public function testDisabledOwnerCannotUpdateOwnAccount(): void
    {
        $session = ['authenticated' => true, 'authorized' => true];

        self::assertFalse($this->target([])->authorizeForSessionUser($this->owner(['state' => 'disabled'] + $session), 'update'));

        // Disabled after the session started: only the stored account knows.
        $target = $this->target([]);
        $target->stubProperties = ['state' => 'disabled'];

        self::assertFalse($target->authorizeForSessionUser($this->owner(['state' => 'enabled'] + $session), 'update'));
    }

    public function testOwnerPassedInExplicitlyNeedsNoSession(): void
    {
        self::assertTrue($this->target([])->authorizeFor($this->owner(['state' => 'enabled']), 'update'));
        self::assertFalse($this->target([])->authorizeFor($this->owner(['state' => 'disabled']), 'update'));
    }

    public function testUpdateIgnoresSubmittedPasswordHashAndSessionFlags(): void
    {
        $target = $this->target([]);
        $target->update([
            'fullname' => 'Changed',
            'hashed_password' => '$2y$10$abcdefghijklmnopqrstuuJ9nYQqf0pZ0bBkn5a5l3cGQ2rQ3xV9.',
            'authenticated' => true,
            'authorized' => true,
        ]);

        self::assertSame(['fullname' => 'Changed'], $target->written);
    }

    public function testUpdateIgnoresSubmittedStorageKey(): void
    {
        $target = $this->target([]);
        $target->update(['fullname' => 'Changed', 'storage_key' => 'admin']);

        self::assertSame(['fullname' => 'Changed'], $target->written);
    }
}
