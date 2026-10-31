<?php

use Codeception\Util\Fixtures;
use Grav\Common\File\CompiledYamlFile;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Flex\Types\Users\Storage\UserFileStorage;
use Grav\Common\Flex\Types\Users\UserIndex;
use Grav\Common\Flex\Types\Users\UserObject;
use Grav\Common\Grav;
use Grav\Common\Session;
use Grav\Common\User\DataUser\User;
use Grav\Framework\Flex\FlexDirectory;

/**
 * Class FlexUserSaveLostUpdateTest
 *
 * Saving a Flex account object writes the whole account the way the object knows it. An object which was loaded
 * before somebody else saved or removed the account (a self-service save which is still hashing its password while
 * a super admin disables, demotes or deletes the account, or the copy of the account kept in the session) must not
 * put the old account back. The Flex sibling of UserSaveLostUpdateTest.
 */
class FlexUserSaveLostUpdateTest extends \PHPUnit\Framework\TestCase
{
    private const ACCOUNT = [
        'state' => 'enabled',
        'email' => 'editor@example.com',
        'fullname' => 'Page Editor',
        'hashed_password' => 'old-hash',
        'access' => ['api' => ['pages' => ['write' => true]]],
    ];
    private const DEMOTED = ['site' => ['login' => true]];

    /** @var Grav */
    private $grav;
    /** @var array */
    private $schemes;
    /** @var array */
    private $flexTypes;
    /** @var string */
    private $root;
    /** @var string */
    private $path;

    protected function setUp(): void
    {
        $grav = Fixtures::get('grav');
        /** @var Grav $grav */
        $grav();

        $this->grav = Grav::instance();
        $folder = 'tmp/flex-user-lost-update-' . bin2hex(random_bytes(4));
        $this->root = GRAV_ROOT . '/' . $folder;
        $this->path = $this->root . '/accounts/editor.yaml';
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

        // The session restores its copy of the account through the registered accounts directory.
        $flex = $this->grav['flex'];
        $types = new ReflectionProperty($flex, 'types');
        $this->flexTypes = $types->getValue($flex);
        $flex->addDirectory($this->directory());
    }

    protected function tearDown(): void
    {
        $flex = $this->grav['flex'];
        (new ReflectionProperty($flex, 'types'))->setValue($flex, $this->flexTypes);

        $locator = $this->grav['locator'];
        $property = new ReflectionProperty($locator, 'schemes');
        $property->setValue($locator, $this->schemes);
        $locator->clearCache();

        CompiledYamlFile::instance($this->path)->free();
        Folder::delete($this->root);
    }

    /** The accounts directory as a new request sees it. */
    private function directory(): FlexDirectory
    {
        return new FlexDirectory('user-accounts', 'blueprints://flex/user-accounts.yaml', [
            'enabled' => true,
            'object' => ['cache' => ['index' => ['enabled' => false], 'object' => ['enabled' => false]]],
        ]);
    }

    /** What $grav['accounts']->load() does, in a request of its own. */
    private function load(string $username = 'editor'): UserObject
    {
        /** @var UserIndex $accounts */
        $accounts = $this->directory()->getIndex();

        return $accounts->load($username);
    }

    /** Store the account as a super admin would have set it up. */
    private function create(array $extra = []): void
    {
        $user = $this->load();
        foreach (self::ACCOUNT + $extra as $name => $value) {
            $user->set($name, $value);
        }
        $user->save();
    }

    private function stored(): array
    {
        $file = CompiledYamlFile::instance($this->path);
        $file->load();
        $content = (array)$file->content();
        $file->free();

        return $content;
    }

    private function assertRefused(UserObject $user): void
    {
        try {
            $user->save();
            self::fail('save() wrote an object which is older than the stored account');
        } catch (RuntimeException $e) {
            self::assertSame(409, $e->getCode());
            self::assertStringContainsString('has been changed', $e->getMessage());
        }
    }

    public function testObjectLoadedBeforeALockOutDoesNotUndoIt(): void
    {
        $this->create();

        $own = $this->load();
        $admin = $this->load();
        self::assertTrue($own->exists());

        $admin->set('state', 'disabled');
        $admin->set('access', self::DEMOTED);
        $admin->save();

        $own->set('password', 'Editor-pass-1');
        $this->assertRefused($own);

        $stored = $this->stored();
        self::assertSame('disabled', $stored['state']);
        self::assertSame('old-hash', $stored['hashed_password']);
        self::assertSame(self::DEMOTED, $stored['access']);
    }

    /** A refused save drops the cached account objects, so the next request reads the stored account. */
    public function testRefusedSaveClearsTheObjectCache(): void
    {
        $this->create();

        $directory = $this->directory();
        $own = $directory->getIndex()->load('editor');
        $directory->getCache('object')->set('editor', 'cached copy');

        $admin = $this->load();
        $admin->set('state', 'disabled');
        $admin->save();

        $this->assertRefused($own);
        self::assertNull($directory->getCache('object')->get('editor'));
    }

    public function testObjectOfARemovedAccountDoesNotBringItBack(): void
    {
        $this->create();

        $own = $this->load();
        $this->load()->delete();
        self::assertFileDoesNotExist($this->path);

        $own->set('fullname', 'Changed');
        $this->assertRefused($own);

        self::assertFileDoesNotExist($this->path);
    }

    public function testNewAccountCanBeCreatedAndSavedAgain(): void
    {
        $user = $this->load();
        self::assertFalse($user->exists());

        $user->update(self::ACCOUNT)->save();
        self::assertSame('Page Editor', $this->stored()['fullname']);

        $user->set('fullname', 'Second save');
        $user->save();
        self::assertSame('Second save', $this->stored()['fullname']);
    }

    public function testOneObjectCanBeSavedAgainAndAgain(): void
    {
        $this->create();

        // What an API update does: load, change, save, in one request.
        $user = $this->load();
        $user->update(['fullname' => 'Second save', 'title' => 'Editor']);
        $user->set('password', 'Editor-pass-1');
        $user->save();
        $user->set('fullname', 'Third save');
        $user->save();

        $stored = $this->stored();
        self::assertSame('Third save', $stored['fullname']);
        self::assertSame('Editor', $stored['title']);
        self::assertNotSame('old-hash', $stored['hashed_password']);
    }

    public function testRenamedObjectIsCheckedAgainstTheAccountItWasLoadedFrom(): void
    {
        $this->create();

        $stale = $this->load();
        $admin = $this->load();
        $admin->set('state', 'disabled');
        $admin->save();

        $stale->setStorageKey('renamed');
        $stale->set('username', 'renamed');
        $this->assertRefused($stale);
        self::assertSame('disabled', $this->stored()['state']);
        self::assertFileDoesNotExist($this->root . '/accounts/renamed.yaml');

        $fresh = $this->load();
        $fresh->setStorageKey('renamed');
        $fresh->set('username', 'renamed');
        $fresh->save();
        self::assertFileDoesNotExist($this->path);
        self::assertFileExists($this->root . '/accounts/renamed.yaml');
    }

    public function testSessionCopyKeepsWhatItWasLoadedFrom(): void
    {
        $this->create();

        /** @var UserObject $sessionUser */
        $sessionUser = unserialize(serialize($this->load()));
        self::assertNotEmpty($sessionUser->getMetaData()['account_checksum'] ?? null);

        // Saving the session's copy works while the account is as it was loaded.
        $sessionUser->set('fullname', 'Changed in profile');
        $sessionUser->save();
        self::assertSame('Changed in profile', $this->stored()['fullname']);

        /** @var UserObject $sessionUser */
        $sessionUser = unserialize(serialize($sessionUser));
        $sessionUser->set('fullname', 'Changed again');
        $sessionUser->save();
        self::assertSame('Changed again', $this->stored()['fullname']);

        // But not after the account was changed somewhere else.
        $sessionUser = unserialize(serialize($sessionUser));
        $admin = $this->load();
        $admin->set('state', 'disabled');
        $admin->save();

        $sessionUser->set('fullname', 'Profile save');
        $this->assertRefused($sessionUser);
        self::assertSame('disabled', $this->stored()['state']);
    }

    /**
     * A session opened before the account was disabled: when the session starts, the fields which decide what the
     * account may do are read from the stored account, and a later profile save does not enable the account again.
     */
    public function testSessionFromBeforeALockOutDoesNotEnableTheAccountAgain(): void
    {
        $this->create(['groups' => ['editors']]);

        /** @var UserObject $sessionUser */
        $sessionUser = unserialize(serialize($this->load()));

        $admin = $this->load();
        $admin->set('state', 'disabled');
        $admin->set('hashed_password', 'new-hash');
        $admin->set('fullname', 'Renamed by admin');
        $admin->set('access', self::DEMOTED);
        $admin->undef('groups');
        $admin->save();

        $this->startSessionWith($sessionUser);

        self::assertSame('disabled', $sessionUser->get('state'));
        self::assertSame('new-hash', $sessionUser->get('hashed_password'));
        self::assertSame(self::DEMOTED, $sessionUser->get('access'));
        self::assertNull($sessionUser->get('groups'));
        self::assertFalse($sessionUser->authorize('api.pages.write'));
        self::assertSame('Page Editor', $sessionUser->get('fullname'));

        // A save of the session's copy keeps what the session was refreshed with.
        $sessionUser->set('title', 'Profile save');
        $sessionUser->save();
        $stored = $this->stored();
        self::assertSame('disabled', $stored['state']);
        self::assertSame('new-hash', $stored['hashed_password']);
        self::assertSame(self::DEMOTED, $stored['access']);
        self::assertArrayNotHasKey('groups', $stored);
        self::assertSame('Profile save', $stored['title']);
    }

    private function startSessionWith(UserObject $user): void
    {
        $session = new class($user) extends Session {
            public function __construct(private UserObject $sessionUser)
            {
                parent::__construct([]);
            }

            public function __get($name)
            {
                return $name === 'user' ? $this->sessionUser : null;
            }

            public function start($readonly = false)
            {
                $this->onSessionStart();

                return $this;
            }
        };

        $previous = $this->grav['accounts'];
        unset($this->grav['accounts']);
        $this->grav['accounts'] = $this->directory()->getIndex();
        try {
            $session->start();
        } finally {
            unset($this->grav['accounts']);
            $this->grav['accounts'] = $previous;
        }
    }

    /** Flex and regular account writers take the same lock for an account file. */
    public function testFlexAndRegularAccountsShareTheLock(): void
    {
        $storage = $this->directory()->getStorage();
        self::assertInstanceOf(UserFileStorage::class, $storage);

        $method = new ReflectionMethod($storage, 'getAccountFilename');
        $flexFilename = $method->invoke($storage, 'Editor');

        $regularFilename = $this->grav['locator']->findResource('account://editor.yaml', true, true);

        self::assertSame($regularFilename, $flexFilename);
    }

    /** Removing an account waits while another process holds the account's lock. */
    public function testDeleteWaitsForTheAccountLock(): void
    {
        $this->create();

        $lockFile = CACHE_DIR . 'compiled/files/' . md5($this->path) . '.lock';
        $holder = sprintf(
            '$h = fopen(%s, "cb+"); flock($h, LOCK_EX); echo "locked\n"; fflush(STDOUT); usleep(800000); flock($h, LOCK_UN);',
            var_export($lockFile, true)
        );
        $process = proc_open([PHP_BINARY, '-r', $holder], [1 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        self::assertSame("locked\n", fgets($pipes[1]));

        $start = microtime(true);
        $this->load()->delete();
        $waited = microtime(true) - $start;

        fclose($pipes[1]);
        proc_close($process);

        self::assertFileDoesNotExist($this->path);
        self::assertGreaterThan(0.5, $waited);
    }
}
