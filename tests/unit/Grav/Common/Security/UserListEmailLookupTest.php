<?php

use Codeception\Util\Fixtures;
use Grav\Common\Data\Validation;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Flex\Types\Users\UserIndex;
use Grav\Common\Grav;
use Grav\Common\User\DataUser\User;
use Grav\Common\User\DataUser\UserCollection;
use Grav\Framework\Flex\FlexDirectory;
use Grav\Framework\Flex\Interfaces\FlexStorageInterface;

/**
 * The account blueprint's `email` field is a single address, so the validator must not let a
 * list through. And should an account file hold one anyway, looking up accounts by e-mail
 * reads them one after the other: a value which cannot be compared in one account must not
 * end the search (or the indexing) for all the others.
 */
class UserListEmailLookupTest extends \PHPUnit\Framework\TestCase
{
    /** @var Grav */
    protected $grav;
    /** @var array */
    protected $schemes;
    /** @var string */
    protected $accounts;

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $this->grav = $grav();

        $this->accounts = sys_get_temp_dir() . '/grav-accounts-' . bin2hex(random_bytes(4));
        mkdir($this->accounts, 0775, true);
        file_put_contents($this->accounts . '/editor.yaml', "email:\n  - editor@example.com\n");
        file_put_contents($this->accounts . '/member.yaml', "email: Member@example.com\n");

        $locator = $this->grav['locator'];
        $property = new ReflectionProperty($locator, 'schemes');
        $property->setAccessible(true);
        $this->schemes = $property->getValue($locator);
        $property->setValue($locator, ['account' => ['' => [$this->accounts]]] + $this->schemes);
        $locator->clearCache();
    }

    protected function tearDown(): void
    {
        $locator = $this->grav['locator'];
        $property = new ReflectionProperty($locator, 'schemes');
        $property->setAccessible(true);
        $property->setValue($locator, $this->schemes);
        $locator->clearCache();
        Folder::delete($this->accounts);
        parent::tearDown();
    }

    public function testEmailValidatorRejectsAListUnlessTheFieldIsMultiple(): void
    {
        $field = ['name' => 'email', 'type' => 'email', 'validate' => ['type' => 'email']];

        self::assertTrue(Validation::typeEmail('editor@example.com', [], $field));
        self::assertFalse(Validation::typeEmail(['editor@example.com'], [], $field));
        self::assertNotEmpty(Validation::validate(['editor@example.com'], $field));

        $field['multiple'] = true;
        self::assertTrue(Validation::typeEmail(['editor@example.com', 'other@example.com'], [], $field));
        self::assertFalse(Validation::typeEmail(['editor@example.com', 'nope'], [], $field));
        self::assertEmpty(Validation::validate(['editor@example.com'], $field));
    }

    public function testFindSkipsAnAccountWithAListInTheField(): void
    {
        $found = (new UserCollection(User::class))->find('member@example.com', ['email']);

        self::assertTrue($found->exists());
        self::assertSame('member', $found->username);
    }

    public function testFindWithoutAMatchReadsAllAccounts(): void
    {
        self::assertFalse((new UserCollection(User::class))->find('nobody@example.com', ['email'])->exists());
        self::assertFalse((new UserCollection(User::class))->find('array', ['email'])->exists());
    }

    public function testFlexIndexLeavesAListOutOfTheEmailKey(): void
    {
        $storage = $this->createMock(FlexStorageInterface::class);

        $meta = ['storage_key' => 'editor'];
        UserIndex::updateObjectMeta($meta, ['email' => ['editor@example.com']], $storage);
        self::assertNull($meta['email']);

        $meta = ['storage_key' => 'member'];
        UserIndex::updateObjectMeta($meta, ['email' => 'Member@example.com'], $storage);
        self::assertSame('member@example.com', $meta['email']);
    }

    public function testFlexCollectionFindSkipsAnAccountWithAListInTheField(): void
    {
        $directory = new FlexDirectory('user-accounts', 'blueprints://flex/user-accounts.yaml', ['enabled' => true]);
        $users = $directory->createCollection([
            'editor' => $directory->createObject(['username' => 'editor', 'email' => ['editor@example.com']], 'editor'),
            'member' => $directory->createObject(['username' => 'member', 'email' => 'Member@example.com'], 'member'),
        ]);

        self::assertSame('member', $users->find('member@example.com', 'email')->username);
        self::assertFalse($users->find('nobody@example.com', 'email')->exists());
    }
}
