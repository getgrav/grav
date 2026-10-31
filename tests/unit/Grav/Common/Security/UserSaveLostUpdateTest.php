<?php

use Codeception\Util\Fixtures;
use Grav\Common\Data\Blueprint;
use Grav\Common\File\CompiledYamlFile;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use Grav\Common\Session;
use Grav\Common\User\DataUser\User;
use Grav\Common\User\DataUser\UserCollection;

/**
 * Class UserSaveLostUpdateTest
 *
 * DataUser\User::save() writes the whole account the way the object knows it. An object which was
 * loaded before somebody else saved or removed the account (a self-service save which is still
 * hashing its password while a super admin disables, demotes or deletes the account) must not put
 * the old account back.
 */
class UserSaveLostUpdateTest extends \PHPUnit\Framework\TestCase
{
    private const ACCOUNT = [
        'state' => 'enabled',
        'fullname' => 'Page Editor',
        'hashed_password' => 'old-hash',
        'access' => ['api' => ['pages' => ['write' => true]]],
    ];

    /** @var Grav */
    private $grav;
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
        $this->root = sys_get_temp_dir() . '/grav-user-lost-update-' . bin2hex(random_bytes(4));
        $this->path = $this->root . '/editor.yaml';
        mkdir($this->root, 0777, true);
    }

    protected function tearDown(): void
    {
        CompiledYamlFile::instance($this->path)->free();
        Folder::delete($this->root);
    }

    /** What UserCollection::load() does, in a request of its own. */
    private function load(): User
    {
        $file = CompiledYamlFile::instance($this->path);
        $user = new User((array)$file->content() + ['username' => 'editor'], new Blueprint('user/account'));
        $user->file($file);
        $file->free();

        return $user;
    }

    private function stored(): array
    {
        $file = CompiledYamlFile::instance($this->path);
        $content = (array)$file->content();
        $file->free();

        return $content;
    }

    public function testObjectLoadedBeforeALockOutDoesNotUndoIt(): void
    {
        $this->load()->update(self::ACCOUNT)->save();

        $own = $this->load();
        $admin = $this->load();

        $admin->set('state', 'disabled');
        $admin->undef('access');
        $admin->save();

        $own->set('password', 'Editor-pass-1');
        try {
            $own->save();
            self::fail('save() wrote an object which is older than the stored account');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('has been changed', $e->getMessage());
        }

        $stored = $this->stored();
        self::assertSame('disabled', $stored['state']);
        self::assertSame('old-hash', $stored['hashed_password']);
        self::assertArrayNotHasKey('access', $stored);
    }

    public function testObjectOfARemovedAccountDoesNotBringItBack(): void
    {
        $this->load()->update(self::ACCOUNT)->save();

        $own = $this->load();
        unlink($this->path);

        try {
            $own->save();
            self::fail('save() created an account which has been removed');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('has been changed', $e->getMessage());
        }

        self::assertFileDoesNotExist($this->path);
    }

    public function testNewAccountCanBeCreated(): void
    {
        $user = $this->load();
        self::assertFalse($user->exists());

        $user->update(self::ACCOUNT)->save();

        self::assertSame('Page Editor', $this->stored()['fullname']);
    }

    public function testOneObjectCanBeSavedAgainAndAgain(): void
    {
        $user = $this->load();
        $user->update(self::ACCOUNT + ['title' => null, 'modified' => 1900000000, 'zip' => '00100'])->save();
        $user->set('fullname', 'Second save');
        $user->save();
        $user->set('fullname', 'Third save');
        $user->save();

        self::assertSame('Third save', $this->stored()['fullname']);
    }

    /**
     * The session keeps its own copy of the account. When the session starts, the fields which
     * decide what the account may do are read from the stored account again.
     */
    public function testSessionStartRefreshesAccessFromTheStoredAccount(): void
    {
        $this->load()->update(self::ACCOUNT + ['groups' => ['editors']])->save();

        /** @var User $sessionUser */
        $sessionUser = unserialize(serialize($this->load()));
        $sessionUser->file()->free();

        $admin = $this->load();
        $admin->set('state', 'disabled');
        $admin->set('hashed_password', 'new-hash');
        $admin->set('fullname', 'Renamed by admin');
        $admin->undef('access');
        $admin->undef('groups');
        $admin->save();
        CompiledYamlFile::instance($this->path)->free();

        $this->startSessionWith($sessionUser);

        self::assertSame('disabled', $sessionUser->get('state'));
        self::assertSame('new-hash', $sessionUser->get('hashed_password'));
        self::assertNull($sessionUser->get('access'));
        self::assertNull($sessionUser->get('groups'));
        self::assertFalse($sessionUser->authorize('api.pages.write'));
        // Other fields are left as the session has them.
        self::assertSame('Page Editor', $sessionUser->get('fullname'));

        // A save of the session's copy keeps what the session was refreshed with.
        $sessionUser->set('title', 'Profile save');
        $sessionUser->save();
        $stored = $this->stored();
        self::assertSame('disabled', $stored['state']);
        self::assertSame('new-hash', $stored['hashed_password']);
        self::assertArrayNotHasKey('access', $stored);
    }

    private function startSessionWith(User $user): void
    {
        $path = $this->path;
        $accounts = new class($path) extends UserCollection {
            public function __construct(private string $path)
            {
                parent::__construct(User::class);
            }

            public function load($username): \Grav\Common\User\Interfaces\UserInterface
            {
                $file = CompiledYamlFile::instance($this->path);
                $user = new User((array)$file->content() + ['username' => 'editor'], new Blueprint('user/account'));
                $user->file($file);

                return $user;
            }
        };

        $session = new class($user) extends Session {
            public function __construct(private User $sessionUser)
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
        $this->grav['accounts'] = $accounts;
        try {
            $session->start();
        } finally {
            unset($this->grav['accounts']);
            $this->grav['accounts'] = $previous;
        }
    }

    public function testSessionCopyCanStillBeSaved(): void
    {
        $this->load()->update(self::ACCOUNT)->save();

        /** @var User $restored */
        $restored = unserialize(serialize($this->load()));
        $restored->set('fullname', 'Changed in profile');
        $restored->save();
        $restored->file()->free();

        self::assertSame('Changed in profile', $this->stored()['fullname']);
    }
}
