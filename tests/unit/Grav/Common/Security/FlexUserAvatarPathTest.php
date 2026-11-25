<?php

use Codeception\Util\Fixtures;
use Grav\Common\Flex\Types\Users\UserObject;
use Grav\Framework\Flex\FlexDirectory;

/**
 * The avatar of an account is a file list keyed by file path, and the `path` of an entry is used as a
 * filesystem path (UserObject::getAvatarFile(), and whoever removes the avatar). The entries are written
 * by setUpdatedMedia() for the uploads in `$files`; the data given to UserObject::update() comes from a
 * form, where it can keep or remove what is stored but must not name a file of its own.
 */
class FlexUserAvatarPathTest extends \PHPUnit\Framework\TestCase
{
    private const STORED = [
        'user/accounts/avatars/me.png' => [
            'name' => 'me.png',
            'type' => 'image/png',
            'size' => 10,
            'path' => 'user/accounts/avatars/me.png',
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $grav();
    }

    /**
     * @dataProvider providerForgedAvatars
     */
    public function testSubmittedDataCannotNameAnAvatarFile(array $data, array $expected): void
    {
        $user = $this->createUser();
        $user->update($data);

        self::assertSame($expected, $this->storedAvatar($user));
    }

    public static function providerForgedAvatars(): array
    {
        $forged = ['name' => 'me.png'];

        return [
            'other file' => [['avatar' => ['user/accounts/admin.yaml' => $forged]], []],
            'other file next to the stored one' => [
                ['avatar' => self::STORED + ['user/accounts/admin.yaml' => $forged]],
                self::STORED,
            ],
            'list entry with a path' => [
                ['avatar' => [['name' => 'me.png', 'path' => 'user/accounts/admin.yaml']]],
                [],
            ],
            'stored entry with another path and name' => [
                ['avatar' => ['user/accounts/avatars/me.png' => ['name' => '../admin.yaml', 'path' => 'user/accounts/admin.yaml']]],
                self::STORED,
            ],
            'flat key' => [['avatar.user/accounts/admin' => $forged], self::STORED],
            'flat key of a property' => [['avatar.user/accounts/admin.name' => 'me.png'], self::STORED],
        ];
    }

    public function testFormCanKeepAndRemoveTheStoredAvatar(): void
    {
        // What a form sends back: the list it was given, with the URLs that were added for it.
        $user = $this->createUser();
        $user->update(['fullname' => 'Editor', 'avatar' => $user->getFormValue('avatar')]);
        self::assertSame(self::STORED, $this->storedAvatar($user));

        $user = $this->createUser();
        $user->update(['fullname' => 'Editor']);
        self::assertSame(self::STORED, $this->storedAvatar($user));

        $user = $this->createUser();
        $user->update(['avatar' => []]);
        self::assertSame([], $this->storedAvatar($user));
    }

    private function storedAvatar(UserObject $user): array
    {
        return $user->prepareStorage()['avatar'] ?? [];
    }

    private function createUser(): UserObject
    {
        $directory = new FlexDirectory('user-accounts', 'blueprints://flex/user-accounts.yaml', ['enabled' => true]);

        /** @var UserObject $user */
        $user = $directory->createObject(
            ['email' => 'editor@example.com', 'fullname' => 'Page Editor', 'state' => 'enabled', 'avatar' => self::STORED],
            'editor'
        );

        return $user;
    }
}
