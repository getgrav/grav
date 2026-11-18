<?php

use Grav\Common\Data\Blueprint;
use Grav\Common\Data\Data;
use Grav\Common\Flex\Types\Users\UserObject;
use Grav\Common\User\Access;
use Grav\Common\User\DataUser\User;
use Grav\Common\User\Interfaces\UserInterface;
use Grav\Framework\Flex\FlexDirectory;

/**
 * A user group that answers only for the super-admin rules.
 */
class GuardStubUserGroup
{
    /** @var bool|null */
    private $super;

    public function __construct(?bool $super)
    {
        $this->super = $super;
    }

    public function authorize(string $action, string $scope = null): ?bool
    {
        return substr($action, -6) === '.super' ? $this->super : null;
    }
}

/**
 * UserObject with the Flex directory, blueprint and storage stubbed out, so the
 * authorization override and update() can be driven without booting Flex.
 */
class GuardStubUserObject extends UserObject
{
    /** @var FlexDirectory|null */
    public static $stubDirectory;
    /** @var Blueprint|null */
    public static $stubBlueprint;
    /** @var string */
    public $stubStorageKey = '';
    /** @var array */
    public $stubAccess = [];
    /** @var bool|null */
    public $stubGroupSuper;
    /** @var array */
    public $written = [];
    /** @var array */
    public $stubElements = [];

    public function __construct(array $elements = [], $key = '', FlexDirectory $directory = null, bool $validate = false)
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

    public function getTimestamp(): int
    {
        return 0;
    }

    protected function getElements()
    {
        return [];
    }

    public function toArray()
    {
        return $this->stubElements;
    }

    protected function getAccess(): Access
    {
        return new Access($this->stubAccess);
    }

    protected function getGroups()
    {
        return [new GuardStubUserGroup($this->stubGroupSuper)];
    }

    public function setNestedProperty($property, $value, $separator = null)
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
}

class FlexUserAccountGuardsTest extends \PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        $directory = $this->createMock(FlexDirectory::class);
        $directory->method('getConfig')->willReturn(new Data([]));
        $directory->method('getAuthorizeRule')->willReturnCallback(
            static function (string $scope, string $action): string {
                return "{$scope}.users.{$action}";
            }
        );
        GuardStubUserObject::$stubDirectory = $directory;

        $blueprint = $this->createMock(Blueprint::class);
        $blueprint->method('mergeData')->willReturnCallback(static function (array $a, array $b): array {
            return array_replace($a, $b);
        });
        $blueprint->method('filter')->willReturnArgument(0);
        $blueprint->method('flattenData')->willReturnArgument(0);
        $blueprint->method('extra')->willReturnArgument(0);
        GuardStubUserObject::$stubBlueprint = $blueprint;
    }

    /** An actor that is not super but holds the user-management rule. */
    private function manager(): UserInterface
    {
        $user = $this->createMock(UserInterface::class);
        $user->method('authorize')->willReturnCallback(
            static function (string $action, string $scope = null): ?bool {
                return strpos($action, 'admin.users') === 0;
            }
        );

        return $user;
    }

    private function superAdmin(): UserInterface
    {
        $user = $this->createMock(UserInterface::class);
        $user->method('authorize')->willReturn(true);

        return $user;
    }

    private function target(array $access, ?bool $groupSuper = null): GuardStubUserObject
    {
        $target = new GuardStubUserObject();
        $target->stubStorageKey = 'target';
        $target->stubAccess = $access;
        $target->stubGroupSuper = $groupSuper;

        return $target;
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
        $target = $this->target(['site' => ['login' => true]], true);

        self::assertFalse($target->authorizeFor($this->manager(), 'update'));
    }

    public function testSuperAdminCanUpdateSuperAdminAccount(): void
    {
        $target = $this->target(['admin' => ['super' => true]]);

        self::assertTrue($target->authorizeFor($this->superAdmin(), 'update'));
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

    public function testExtraDataLeavesOutCredentialsAndTokens(): void
    {
        $target = $this->target([]);
        $target->stubElements = [
            'state' => 'enabled',
            'hashed_password' => '$2y$10$abcdefghijklmnopqrstuuJ9nYQqf0pZ0bBkn5a5l3cGQ2rQ3xV9.',
            'reset' => 'token::1999999999',
            'twofa_secret' => 'ABCDEFGHIJKLMNOP',
        ];

        self::assertSame(['state' => 'enabled'], $target->extra());
    }

    public function testRegularUserExtraDataLeavesOutCredentialsAndTokens(): void
    {
        $user = new User([
            'state' => 'enabled',
            'hashed_password' => '$2y$10$abcdefghijklmnopqrstuuJ9nYQqf0pZ0bBkn5a5l3cGQ2rQ3xV9.',
            'reset' => 'token::1999999999',
            'activation_token' => 'token::1999999999',
        ], new Blueprint());

        self::assertSame(['state' => 'enabled'], $user->extra());
    }
}
