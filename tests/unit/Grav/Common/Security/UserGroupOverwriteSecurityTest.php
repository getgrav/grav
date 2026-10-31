<?php

use Grav\Common\Flex\Types\UserGroups\UserGroupObject;
use Grav\Framework\Flex\FlexDirectory;
use Grav\Framework\Flex\Interfaces\FlexStorageInterface;

/**
 * UserGroupObject with the Flex directory and storage stubbed out, so the guard in
 * save() can be driven without booting Flex.
 */
class StubUserGroupObject extends UserGroupObject
{
    public static ?FlexDirectory $stubDirectory = null;
    public string $stubStorageKey = '';
    public ?string $stubGroupname = null;
    public array $stubMeta = [];

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

    public function getMetaData(): array
    {
        return $this->stubMeta;
    }

    protected function getElement($property, $default = null)
    {
        return $property === 'groupname' ? $this->stubGroupname : $default;
    }
}

/**
 * A group is stored under its `groupname`, which a Flex form takes from the
 * submission. Creating a group must not replace the one which has the name already:
 * the new row has no access map, so every member would lose what the group grants.
 */
class UserGroupOverwriteSecurityTest extends \PHPUnit\Framework\TestCase
{
    private function group(string $groupname, array $meta = [], string $storageKey = ''): StubUserGroupObject
    {
        $storage = $this->createMock(FlexStorageInterface::class);
        $storage->method('getKeyField')->willReturn('groupname');
        $storage->method('hasKey')->willReturnCallback(static fn(string $k): bool => $k === 'admins');

        $directory = $this->createMock(FlexDirectory::class);
        $directory->method('getStorage')->willReturn($storage);
        StubUserGroupObject::$stubDirectory = $directory;

        $group = new StubUserGroupObject();
        $group->stubGroupname = $groupname;
        $group->stubMeta = $meta;
        $group->stubStorageKey = $storageKey;

        return $group;
    }

    private function guardRefuses(StubUserGroupObject $group): bool
    {
        try {
            $group->save();
        } catch (\Throwable $e) {
            // Anything else comes from the rest of save(), which needs a full Flex setup.
            return $e instanceof RuntimeException && $e->getMessage() === 'User group with this name already exists';
        }

        return false;
    }

    public function testNewGroupWithTakenNameIsRefused(): void
    {
        self::assertTrue($this->guardRefuses($this->group('admins')));
    }

    public function testNewGroupWithTakenNameAndStorageKeyIsRefused(): void
    {
        self::assertTrue($this->guardRefuses($this->group('admins', [], 'anything')));
    }

    public function testNewGroupWithFreeNameIsAllowed(): void
    {
        self::assertFalse($this->guardRefuses($this->group('editors')));
    }

    public function testExistingGroupCanBeSaved(): void
    {
        self::assertFalse($this->guardRefuses($this->group('admins', ['storage_key' => 'admins'], 'admins')));
    }
}
