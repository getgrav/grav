<?php

use Grav\Common\Flex\Types\Users\UserObject;
use Grav\Framework\Flex\FlexDirectory;
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
 *
 * Naming convention: test{Method}_{GHSA_ID}_{description}
 */
class UserOverwriteSecurityTest extends \PHPUnit\Framework\TestCase
{
    private function makeStubbedUser(string $storageKey, string $targetKey, bool $targetExists): StubUserObject
    {
        $storage = $this->createMock(FlexStorageInterface::class);
        $storage->method('hasKey')->willReturnCallback(
            static fn(string $k): bool => $k === $targetKey && $targetExists
        );

        $directory = $this->createMock(FlexDirectory::class);
        $directory->method('getStorage')->willReturn($storage);

        StubUserObject::$stubStorage = $storage;
        StubUserObject::$stubDirectory = $directory;

        $user = new StubUserObject();
        $user->stubStorageKey = $storageKey;
        $user->stubKey = $targetKey;

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
}
