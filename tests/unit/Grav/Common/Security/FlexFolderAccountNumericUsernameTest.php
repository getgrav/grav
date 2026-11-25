<?php

use Codeception\Util\Fixtures;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Flex\Types\Users\Storage\UserFolderStorage;
use Grav\Common\Flex\Types\Users\UserIndex;
use Grav\Common\Flex\Types\Users\UserObject;
use Grav\Common\Grav;
use Grav\Framework\Flex\FlexDirectory;

/**
 * Class FlexFolderAccountNumericUsernameTest
 *
 * `system.accounts.storage: folder` keeps an account in `account://{KEY:2}/{KEY}/user.yaml`. A username made of
 * digits only (the default `system.username_regex` allows it) has to be found by that name like any other account:
 * an account indexed under its position among the numeric ones can't be loaded by username or email, so a session
 * of it is never refreshed from the stored account and stays signed in after the account has been disabled.
 */
class FlexFolderAccountNumericUsernameTest extends \PHPUnit\Framework\TestCase
{
    private const USERNAMES = ['20261', 'alice', '20262', '31007'];

    /** @var Grav */
    private $grav;
    /** @var array */
    private $schemes;
    /** @var string */
    private $root;

    protected function setUp(): void
    {
        $grav = Fixtures::get('grav');
        /** @var Grav $grav */
        $grav();

        $this->grav = Grav::instance();
        $folder = 'tmp/flex-folder-account-numeric-' . bin2hex(random_bytes(4));
        $this->root = GRAV_ROOT . '/' . $folder;
        mkdir($this->root . '/accounts', 0777, true);
        mkdir($this->root . '/data', 0777, true);

        $locator = $this->grav['locator'];
        $property = new ReflectionProperty($locator, 'schemes');
        $this->schemes = $property->getValue($locator);
        $property->setValue($locator, [
                'account' => ['' => [$folder . '/accounts']],
                'user-data' => ['' => [$folder . '/data']],
            ] + $this->schemes);
        $locator->clearCache();

        foreach (self::USERNAMES as $username) {
            $account = ['email' => "{$username}@example.com", 'fullname' => 'A member', 'state' => 'enabled'];
            $this->load($username)->update($account)->save();
        }
    }

    protected function tearDown(): void
    {
        $locator = $this->grav['locator'];
        $property = new ReflectionProperty($locator, 'schemes');
        $property->setValue($locator, $this->schemes);
        $locator->clearCache();

        Folder::delete($this->root);
    }

    /** The accounts directory as a new request sees it, set up as FlexServiceProvider does for `storage: folder`. */
    private function accounts(): UserIndex
    {
        $directory = new FlexDirectory('user-accounts', 'blueprints://flex/user-accounts.yaml', [
            'enabled' => true,
            'data' => [
                'storage' => [
                    'class' => UserFolderStorage::class,
                    'options' => [
                        'file' => 'user',
                        'pattern' => '{FOLDER}/{KEY:2}/{KEY}/{FILE}{EXT}',
                        'key' => 'storage_key',
                        'indexed' => true,
                        'case_sensitive' => false
                    ],
                ],
            ],
            'object' => ['cache' => ['index' => ['enabled' => false], 'object' => ['enabled' => false]]],
        ]);

        /** @var UserIndex $accounts */
        $accounts = $directory->getIndex();

        return $accounts;
    }

    private function load(string $username): UserObject
    {
        return $this->accounts()->load($username);
    }

    public function testAccountsAreIndexedByUsername(): void
    {
        $accounts = $this->accounts();

        $expected = self::USERNAMES;
        sort($expected, SORT_STRING);
        self::assertSame($expected, array_map('strval', $accounts->getKeys()));
        foreach ($accounts->getKeys() as $key) {
            $meta = $accounts->getMetaData($key);
            self::assertSame((string)$key, (string)$meta['storage_key']);
            self::assertSame((string)$key, (string)$meta['key']);
            self::assertSame("{$key}@example.com", $meta['email']);
        }
    }

    public function testNumericUsernameLoadsItsOwnAccount(): void
    {
        foreach (self::USERNAMES as $username) {
            $user = $this->load($username);

            self::assertTrue($user->exists(), "Account {$username} was not found by its username");
            self::assertSame($username, (string)$user->username);
            self::assertSame($username, $user->getStorageKey());
            self::assertSame($username, (string)$this->accounts()->find("{$username}@example.com", ['email'])->username);
        }

        // The position of an account among the numeric ones is not a username.
        self::assertFalse($this->load('0')->exists());
        self::assertFalse($this->load('1')->exists());
    }

    public function testDisabledNumericAccountIsSeenByTheNextLoad(): void
    {
        $admin = $this->load('20261');
        $admin->set('state', 'disabled');
        $admin->save();

        // What Session::refreshUser() reads on the next request of a session of this account.
        $stored = $this->load('20261');
        self::assertTrue($stored->exists());
        self::assertSame('disabled', $stored->get('state'));
        self::assertSame('enabled', $this->load('20262')->get('state'));
    }
}
