<?php

use Grav\Common\Flex\Types\Users\UserObject;
use Grav\Framework\Flex\FlexDirectory;
use Grav\Framework\Flex\Interfaces\FlexIndexInterface;
use Grav\Framework\Flex\Interfaces\FlexStorageInterface;

/**
 * Test double that lets us drive UserObject::save() without a full Flex
 * directory/blueprint/validator stack. We override the handful of accessors
 * the uniqueness-guard block reads.
 */
class StubUserObject extends UserObject
{
    public static ?FlexStorageInterface $stubStorage = null;
    public static ?FlexDirectory $stubDirectory = null;
    public string $stubKey = '';
    public string $stubStorageKey = '';
    public ?string $stubUsername = null;
    public array $stubMeta = [];

    // Match FlexObjectInterface constructor signature, but skip all parent work.
    public function __construct(array $elements = [], $key = '', ?FlexDirectory $directory = null, bool $validate = false)
    {
        // Deliberately empty — we don't want blueprint/validator/container boot.
    }

    public function getKey(): string
    {
        return $this->stubKey;
    }

    public function getStorageKey(): string
    {
        return $this->stubStorageKey;
    }

    public function setStorageKey($key = null)
    {
        $this->stubStorageKey = (string)($key ?? '');
        return $this;
    }

    public function getFlexDirectory(?string $type = null): FlexDirectory
    {
        return self::$stubDirectory;
    }

    public function getMetaData(): array
    {
        return $this->stubMeta;
    }

    protected function getElement($property, $default = null)
    {
        return $property === 'username' ? $this->stubUsername : $default;
    }
}

/**
 * Class UserOverwriteSecurityTest
 *
 * Covers: GHSA-rr73-568v-28f8 (privilege de-escalation / admin account disruption
 * by creating a user with an existing username and overwriting the target).
 *
 * Verifies the guard in UserObject::save() that refuses to create a new user
 * whose chosen username is already taken. The test also pins the two subtle
 * properties of the check:
 *  - `@@`-prefixed transient storage keys (Flex's in-memory marker for
 *    unsaved objects) are treated as "new user" and trigger the check —
 *    `strpos($key, '@@')` would return 0 here and be falsy, bypassing the check.
 *  - The uniqueness check runs for any FlexStorageInterface, not just FileStorage.
 *  - The check looks at the key the storage is going to write and at the
 *    username, not at the object key: a Flex form creates the object with an
 *    empty key and takes the username from the submitted data.
 *
 * Naming convention: test{Method}_{GHSA_ID}_{description}
 */
class UserOverwriteSecurityTest extends \PHPUnit\Framework\TestCase
{
    private function makeStubbedUser(string $storageKey, string $targetKey, bool $targetExists, string $keyField = 'username', array $index = []): StubUserObject
    {
        $storage = $this->createMock(FlexStorageInterface::class);
        $storage->method('getKeyField')->willReturn($keyField);
        $storage->method('hasKey')->willReturnCallback(
            static fn(string $k): bool => $k === $targetKey && $targetExists
        );

        // Index entries keyed by username, as UserIndex builds them: [username => storage_key].
        $flexIndex = $this->createMock(FlexIndexInterface::class);
        $flexIndex->method('getMetaData')->willReturnCallback(
            static fn($k): array => isset($index[$k]) ? ['storage_key' => $index[$k]] : []
        );

        $directory = $this->createMock(FlexDirectory::class);
        $directory->method('getStorage')->willReturn($storage);
        $directory->method('getIndex')->willReturn($flexIndex);

        StubUserObject::$stubStorage = $storage;
        StubUserObject::$stubDirectory = $directory;

        $user = new StubUserObject();
        $user->stubStorageKey = $storageKey;
        $user->stubKey = $targetKey;
        $user->stubUsername = $targetKey;

        return $user;
    }

    // =========================================================================
    // GHSA-rr73-568v-28f8: create-new path must refuse a taken username
    // =========================================================================

    public function testSave_GHSArr73_BlocksNewUserWithExistingUsername(): void
    {
        $user = $this->makeStubbedUser(storageKey: '', targetKey: 'root0', targetExists: true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('User account with this username already exists');
        $user->save();
    }

    public function testSave_GHSArr73_BlocksWhenTransientKeyStartsWithAtMarker(): void
    {
        // Flex's in-memory marker for unsaved objects is `@@<hash>`. `strpos($key, '@@')`
        // returns 0 here, which is falsy — the old check would have skipped the guard
        // and let the overwrite through. Pin the str_contains() fix.
        $user = $this->makeStubbedUser(storageKey: '@@abc123', targetKey: 'root0', targetExists: true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('User account with this username already exists');
        $user->save();
    }

    public function testSave_GHSArr73_AllowsNewUserWhenUsernameIsFree(): void
    {
        // When the username is NOT taken, the uniqueness check should pass and
        // save() must be allowed to proceed past the guard. We can't run the
        // rest of save() without a full Flex setup, so we assert that the
        // specific RuntimeException is NOT thrown before control leaves our
        // instrumented code — any other exception downstream is acceptable.
        $user = $this->makeStubbedUser(storageKey: '', targetKey: 'brand-new-user', targetExists: false);

        try {
            $user->save();
            $this->addToAssertionCount(1);
        } catch (RuntimeException $e) {
            $this->assertStringNotContainsString(
                'User account with this username already exists',
                $e->getMessage(),
                'Uniqueness guard must not fire when the target username is free'
            );
        } catch (\Throwable) {
            // Downstream failures (missing blueprint, storage, events) are expected
            // and do not indicate a guard regression.
            $this->addToAssertionCount(1);
        }
    }

    // =========================================================================
    // The username comes from the data; the object key does not have to match it
    // =========================================================================

    private function assertSaveIsRefused(StubUserObject $user): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('User account with this username already exists');
        $user->save();
    }

    private function assertSavePassesTheGuard(StubUserObject $user): void
    {
        try {
            $user->save();
            $this->addToAssertionCount(1);
        } catch (RuntimeException $e) {
            $this->assertStringNotContainsString('User account with this username already exists', $e->getMessage());
        } catch (\Throwable) {
            // Downstream failures are expected, see above.
            $this->addToAssertionCount(1);
        }
    }

    public function testSave_BlocksNewUserWhoseTakenUsernameIsOnlyInTheData(): void
    {
        // Flex form: createObject([], '') followed by update(['username' => 'root0']).
        $user = $this->makeStubbedUser(storageKey: '', targetKey: 'root0', targetExists: true);
        $user->stubKey = '';

        $this->assertSaveIsRefused($user);
    }

    public function testSave_BlocksNewUserWhoseObjectKeyIsAnotherFreeName(): void
    {
        $user = $this->makeStubbedUser(storageKey: '', targetKey: 'root0', targetExists: true);
        $user->stubKey = 'free';

        $this->assertSaveIsRefused($user);
    }

    public function testSave_BlocksTakenUsernameWhenObjectAlreadyHasStorageKey(): void
    {
        // A storage key alone (e.g. submitted as form data) does not make the object a saved account.
        $user = $this->makeStubbedUser(storageKey: 'anything', targetKey: 'root0', targetExists: true);
        $user->stubKey = '';

        $this->assertSaveIsRefused($user);
    }

    public function testSave_BlocksRenamingAccountToTakenUsername(): void
    {
        $user = $this->makeStubbedUser(storageKey: 'editor', targetKey: 'root0', targetExists: true);
        $user->stubKey = 'editor';
        $user->stubMeta = ['storage_key' => 'editor'];

        $this->assertSaveIsRefused($user);
    }

    public function testSave_BlocksStorageKeyOfAnotherAccountInFolderStorage(): void
    {
        // Folder storage keys records by `storage_key`, which the row may carry.
        $user = $this->makeStubbedUser(storageKey: 'root0', targetKey: 'root0', targetExists: true, keyField: 'storage_key');
        $user->stubKey = '';
        $user->stubUsername = 'free';

        $this->assertSaveIsRefused($user);
    }

    public function testSave_BlocksSecondRecordWithTakenUsernameInFolderStorage(): void
    {
        // The record itself is new, but the index would file it over root0's.
        $user = $this->makeStubbedUser(storageKey: 'zzz', targetKey: 'abc', targetExists: true, keyField: 'storage_key', index: ['root0' => 'abc']);
        $user->stubKey = '';
        $user->stubUsername = 'root0';

        $this->assertSaveIsRefused($user);
    }

    public function testSave_AllowsUsernameWhichIsOnlyLeftInStaleIndex(): void
    {
        $user = $this->makeStubbedUser(storageKey: 'zzz', targetKey: 'abc', targetExists: false, keyField: 'storage_key', index: ['root0' => 'abc']);
        $user->stubKey = '';
        $user->stubUsername = 'root0';

        $this->assertSavePassesTheGuard($user);
    }

    public function testSave_AllowsSavingExistingAccount(): void
    {
        $user = $this->makeStubbedUser(storageKey: 'root0', targetKey: 'root0', targetExists: true, index: ['root0' => 'root0']);
        $user->stubMeta = ['storage_key' => 'root0'];

        $this->assertSavePassesTheGuard($user);
    }

    public function testSave_AllowsRenamingAccountToFreeUsername(): void
    {
        $user = $this->makeStubbedUser(storageKey: 'editor', targetKey: 'free', targetExists: false, index: ['editor' => 'editor']);
        $user->stubKey = 'editor';
        $user->stubMeta = ['storage_key' => 'editor'];

        $this->assertSavePassesTheGuard($user);
    }
}
