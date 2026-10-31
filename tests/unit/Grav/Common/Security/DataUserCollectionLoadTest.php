<?php

use Codeception\Util\Fixtures;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use Grav\Common\User\DataUser\User;
use Grav\Common\User\DataUser\UserCollection;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

/**
 * Class DataUserCollectionLoadTest
 *
 * An account has one name. The locator normalizes the path it is given, so
 * UserCollection::load() and delete() have to refuse the spellings ('./admin',
 * '\admin', 'x/../admin', ...) which would otherwise reach the same account file
 * under a username of their own, and with it a fresh set of per-user rate limits
 * (the Login plugin's 2FA attempt counter among them).
 */
class DataUserCollectionLoadTest extends \PHPUnit\Framework\TestCase
{
    /** @var Grav */
    protected $grav;
    /** @var array */
    protected $accountPaths;
    /** @var string */
    protected $accounts;

    protected function setUp(): void
    {
        parent::setUp();

        $grav = Fixtures::get('grav');
        $this->grav = $grav();

        $this->accounts = sys_get_temp_dir() . '/grav-accounts-' . bin2hex(random_bytes(6));
        mkdir($this->accounts . '/avatars', 0775, true);
        file_put_contents($this->accounts . '/admin.yaml', "email: admin@example.com\n");

        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];
        $this->accountPaths = $locator->getPaths('account');
        $locator->resetScheme('account');
        $locator->addPath('account', '', $this->accounts);
    }

    protected function tearDown(): void
    {
        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];
        $locator->resetScheme('account');
        foreach ($this->accountPaths as $prefix => $paths) {
            $locator->addPath('account', $prefix, $paths, false, true);
        }

        Folder::delete($this->accounts);

        parent::tearDown();
    }

    public function testLoadFindsTheAccountByItsName(): void
    {
        $accounts = new UserCollection(User::class);

        self::assertTrue($accounts->load('admin')->exists());
        self::assertSame('admin', $accounts->load('Admin')->username);
        self::assertSame('admin', $accounts->find('admin@example.com')->username);
        self::assertFalse($accounts->load('')->exists());
        self::assertFalse($accounts->load('nobody')->exists());
    }

    public function testDeleteRemovesTheAccountByItsName(): void
    {
        $accounts = new UserCollection(User::class);

        self::assertFalse($accounts->delete('nobody'));
        self::assertTrue($accounts->delete('admin'));
        self::assertFileDoesNotExist($this->accounts . '/admin.yaml');
    }

    /**
     * @dataProvider providerSpellings
     */
    public function testLoadAndDeleteKnowNoOtherSpelling(string $spelling): void
    {
        $accounts = new UserCollection(User::class);

        $user = $accounts->load($spelling);
        self::assertFalse($user->exists(), $spelling);
        self::assertNull($user->email, $spelling);
        self::assertFalse($accounts->find($spelling)->exists(), $spelling);
        self::assertFalse($accounts->delete($spelling), $spelling);
        self::assertFileExists($this->accounts . '/admin.yaml');
    }

    public static function providerSpellings(): array
    {
        return [
            ['./admin'],
            ['/admin'],
            ['//admin'],
            ['\\admin'],
            ['7/../admin'],
            ['7\\..\\admin'],
            ['avatars/../admin'],
            ['../admin'],
        ];
    }
}
