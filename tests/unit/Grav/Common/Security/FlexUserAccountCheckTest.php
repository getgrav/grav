<?php

use Codeception\Util\Fixtures;
use Grav\Common\File\CompiledYamlFile;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Flex\Types\Users\UserIndex;
use Grav\Common\Flex\Types\Users\UserObject;
use Grav\Common\Grav;
use Grav\Common\User\DataUser\User;
use Grav\Framework\Flex\FlexDirectory;

/**
 * A Flex account form checks the active user before it saves the account, and a form which holds an existing account
 * is an update, whatever the form calls itself: an account which may only create accounts must not get a stored one
 * saved.
 */
class FlexUserAccountCheckTest extends \PHPUnit\Framework\TestCase
{
    /** @var Grav */
    private $grav;
    /** @var array */
    private $schemes;
    /** @var array */
    private $flexTypes;
    /** @var mixed */
    private $activeUser;
    /** @var string */
    private $root;

    protected function setUp(): void
    {
        $grav = Fixtures::get('grav');
        /** @var Grav $grav */
        $grav();

        $this->grav = Grav::instance();
        $this->activeUser = $this->grav['user'] ?? null;

        $folder = 'tmp/flex-user-check-' . bin2hex(random_bytes(4));
        $this->root = GRAV_ROOT . '/' . $folder;
        mkdir($this->root . '/accounts', 0777, true);
        mkdir($this->root . '/data', 0777, true);
        file_put_contents($this->root . '/accounts/webmaster.yaml', "state: enabled\nemail: webmaster@example.com\naccess:\n  site:\n    login: true\n");

        $locator = $this->grav['locator'];
        $property = new ReflectionProperty($locator, 'schemes');
        $this->schemes = $property->getValue($locator);
        $property->setValue($locator, [
                'account' => ['' => [$folder . '/accounts']],
                'user-data' => ['' => [$folder . '/data']],
            ] + $this->schemes);
        $locator->clearCache();

        // The accounts look up their groups through the registered directory.
        $flex = $this->grav['flex'];
        $this->flexTypes = (new ReflectionProperty($flex, 'types'))->getValue($flex);
        $flex->addDirectory(new FlexDirectory('user-groups', 'blueprints://flex/user-groups.yaml', ['enabled' => true]));
    }

    protected function tearDown(): void
    {
        unset($this->grav['user']);
        $this->grav['user'] = $this->activeUser;

        $flex = $this->grav['flex'];
        (new ReflectionProperty($flex, 'types'))->setValue($flex, $this->flexTypes);

        $locator = $this->grav['locator'];
        $property = new ReflectionProperty($locator, 'schemes');
        $property->setValue($locator, $this->schemes);
        $locator->clearCache();

        CompiledYamlFile::instance($this->root . '/accounts/webmaster.yaml')->free();
        Folder::delete($this->root);
    }

    private function load(string $username): UserObject
    {
        $directory = new FlexDirectory('user-accounts', 'blueprints://flex/user-accounts.yaml', [
            'enabled' => true,
            'object' => ['cache' => ['index' => ['enabled' => false], 'object' => ['enabled' => false]]],
        ]);
        /** @var UserIndex $accounts */
        $accounts = $directory->getIndex();

        return $accounts->load($username);
    }

    private function signIn(array $users): void
    {
        $user = new User(['username' => 'recruiter', 'state' => 'enabled', 'access' => ['site' => ['login' => true], 'admin' => ['users' => $users]]]);
        $user->set('authenticated', true);
        $user->set('authorized', true);
        unset($this->grav['user']);
        $this->grav['user'] = $user;
    }

    private function check(UserObject $object): ?int
    {
        try {
            $object->check();
        } catch (RuntimeException $e) {
            return $e->getCode();
        }

        return null;
    }

    public function testCreateOnlyUserCannotSaveStoredAccount(): void
    {
        $this->signIn(['create' => true]);
        $account = $this->load('webmaster');
        self::assertTrue($account->exists());
        $account->update(['email' => 'attacker@example.com', 'fullname' => 'Web Master']);

        self::assertSame(403, $this->check($account));
    }

    public function testUpdatingUserCanSaveStoredAccount(): void
    {
        $this->signIn(['create' => true, 'update' => true]);
        $account = $this->load('webmaster');
        $account->update(['email' => 'new@example.com', 'fullname' => 'Web Master']);

        self::assertNull($this->check($account));
    }

    public function testCreateOnlyUserCanSaveNewAccount(): void
    {
        $this->signIn(['create' => true]);
        $account = $this->load('newhire');
        self::assertFalse($account->exists());
        $account->update(['email' => 'newhire@example.com', 'fullname' => 'New Hire']);

        self::assertNull($this->check($account));
    }
}
