<?php

use Codeception\Util\Fixtures;
use Grav\Common\Data\Data;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Flex\Types\UserGroups\UserGroupCollection;
use Grav\Common\Flex\Types\Users\UserObject;
use Grav\Common\User\Access;
use Grav\Common\User\Interfaces\UserInterface;
use Grav\Framework\Flex\FlexDirectory;

/**
 * UserObject in the groups named by `groups`, none of which is in user/config/groups.yaml.
 */
class EnvironmentGroupStubUserObject extends UserObject
{
    public static ?FlexDirectory $stubDirectory = null;
    public array $stubProperties = [];

    public function __construct(array $elements = [], $key = '', ?FlexDirectory $directory = null, bool $validate = false)
    {
    }

    public function getStorageKey(): string
    {
        return 'target';
    }

    public function getFlexDirectory(): FlexDirectory
    {
        return self::$stubDirectory;
    }

    public function getProperty($property, $default = null)
    {
        return $this->stubProperties[$property] ?? $default;
    }

    protected function getAccess(): Access
    {
        return new Access([]);
    }

    protected function getGroups()
    {
        return new UserGroupCollection([]);
    }

    public function authorizeFor(UserInterface $user, string $action): ?bool
    {
        return $this->isAuthorizedOverride($user, $action, 'admin', false);
    }
}

/**
 * An account can be super-admin through a group which an environment defines in its own config/groups.yaml.
 * Which environment a request runs in depends on the host name, so the guard which keeps user managers away from
 * super-admin accounts has to look at the groups of every environment, not only at user/config/groups.yaml.
 */
class FlexUserEnvironmentGroupGuardTest extends \PHPUnit\Framework\TestCase
{
    /** @var string */
    private $root;
    /** @var bool */
    private $createdEnvFolder = false;

    protected function setUp(): void
    {
        $grav = Fixtures::get('grav');
        $grav();

        $directory = $this->createMock(FlexDirectory::class);
        $directory->method('getConfig')->willReturn(new Data([]));
        $directory->method('getAuthorizeRule')->willReturnCallback(
            static fn(string $scope, string $action): string => "{$scope}.users.{$action}"
        );
        EnvironmentGroupStubUserObject::$stubDirectory = $directory;
    }

    protected function tearDown(): void
    {
        putenv('GRAV_ENVIRONMENTS_PATH');
        if ($this->root) {
            Folder::delete($this->root);
        }
        if ($this->createdEnvFolder) {
            Folder::delete(GRAV_WEBROOT . '/' . GRAV_USER_PATH . '/env');
        }
    }

    private function manager(): UserInterface
    {
        $user = $this->createMock(UserInterface::class);
        $user->method('authorize')->willReturnCallback(
            static fn(string $action, ?string $scope = null): ?bool => str_starts_with($action, 'admin.users')
        );

        return $user;
    }

    private function target(array $groups): EnvironmentGroupStubUserObject
    {
        $target = new EnvironmentGroupStubUserObject();
        $target->stubProperties = ['groups' => $groups];

        return $target;
    }

    private function writeGroups(string $envFolder, string $yaml): void
    {
        mkdir($envFolder . '/config', 0777, true);
        file_put_contents($envFolder . '/config/groups.yaml', $yaml);
    }

    public function testManagerCannotUpdateAccountThatIsSuperThroughAnEnvironmentGroup(): void
    {
        $this->root = sys_get_temp_dir() . '/grav-env-groups-' . bin2hex(random_bytes(4));
        $this->writeGroups($this->root . '/staging.example.com', "staff:\n  access:\n    api:\n      super: true\n");
        putenv('GRAV_ENVIRONMENTS_PATH=' . $this->root);

        self::assertFalse($this->target(['staff'])->authorizeFor($this->manager(), 'update'));
        self::assertFalse($this->target(['staff'])->authorizeFor($this->manager(), 'delete'));
        self::assertTrue($this->target(['readers'])->authorizeFor($this->manager(), 'update'));
    }

    public function testEnvironmentFolderInTheDefaultLocationIsChecked(): void
    {
        $envRoot = GRAV_WEBROOT . '/' . GRAV_USER_PATH . '/env';
        if (!is_dir($envRoot)) {
            $this->createdEnvFolder = true;
        }
        $this->root = $envRoot . '/oss-test-' . bin2hex(random_bytes(4));
        $this->writeGroups($this->root, "staff:\n  access:\n    admin.super: true\n");

        self::assertFalse($this->target(['staff'])->authorizeFor($this->manager(), 'update'));
        self::assertTrue($this->target(['readers'])->authorizeFor($this->manager(), 'update'));
    }

    public function testManagerCanStillUpdateAccountOfARegularEnvironmentGroup(): void
    {
        $this->root = sys_get_temp_dir() . '/grav-env-groups-' . bin2hex(random_bytes(4));
        $this->writeGroups($this->root . '/staging.example.com', "staff:\n  access:\n    site:\n      login: true\n");
        putenv('GRAV_ENVIRONMENTS_PATH=' . $this->root);

        self::assertTrue($this->target(['staff'])->authorizeFor($this->manager(), 'update'));
    }
}
