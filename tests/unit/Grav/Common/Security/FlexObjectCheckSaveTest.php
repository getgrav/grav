<?php

use Codeception\Util\Fixtures;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use Grav\Common\User\DataUser\User;
use Grav\Common\User\Interfaces\UserInterface;
use Grav\Framework\Flex\FlexDirectory;
use Grav\Framework\Flex\Interfaces\FlexObjectInterface;

/**
 * FlexObject::check() is the last question asked before a Flex form saves its
 * object: may this user create the object or, if it is already stored, update it.
 * A user who is only allowed to create objects must not get a stored one saved,
 * neither because no user was passed in (which means the active user, like in
 * isAuthorized()) nor by giving the stored object another storage key.
 */
class FlexObjectCheckSaveTest extends \PHPUnit\Framework\TestCase
{
    private const BLUEPRINT = <<<'YAML'
        title: Notes
        type: flex-objects
        config:
          admin:
            permissions:
              admin.notes:
                type: crudl
                label: Notes
          data:
            storage:
              class: Grav\Framework\Flex\Storage\SimpleStorage
              options:
                formatter:
                  class: Grav\Framework\File\Formatter\JsonFormatter
                folder: %s/notes.json
        form:
          validation: loose
          fields:
            title:
              type: text
        YAML;

    /** @var string Storage resolves a plain folder against GRAV_ROOT. */
    protected $folder;

    /** @var mixed */
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();

        $grav = Fixtures::get('grav');
        /** @var Grav $grav */
        $grav = $grav();

        $this->folder = 'tmp/flex-check-save-' . uniqid('', false);
        Folder::create(GRAV_ROOT . "/{$this->folder}");
        file_put_contents(GRAV_ROOT . "/{$this->folder}/notes.yaml", sprintf(self::BLUEPRINT, $this->folder));
        file_put_contents(GRAV_ROOT . "/{$this->folder}/notes.json", json_encode(['stored' => ['title' => 'Stored']]));

        $this->user = $grav['user'] ?? null;
    }

    protected function tearDown(): void
    {
        $grav = Grav::instance();
        unset($grav['user']);
        $grav['user'] = $this->user;

        Folder::delete(GRAV_ROOT . "/{$this->folder}");

        parent::tearDown();
    }

    /**
     * @dataProvider providerStoredObject
     */
    public function testStoredObjectNeedsUpdate(array $data): void
    {
        $this->signIn(['create' => true]);
        $note = $this->directory()->getObject('stored');
        $note->update($data);

        self::assertFalse($note->isAuthorized('save'));
        self::assertSame(403, $this->check($note));

        $this->signIn(['update' => true]);
        $note = $this->directory()->getObject('stored');
        $note->update($data);

        self::assertTrue($note->isAuthorized('save'));
        self::assertNull($this->check($note));
    }

    public static function providerStoredObject(): array
    {
        return [
            'same key' => [['title' => 'Changed']],
            'another key' => [['title' => 'Changed', 'storage_key' => 'moved']],
        ];
    }

    /**
     * @dataProvider providerNewObject
     */
    public function testNewObjectNeedsCreate(array $data): void
    {
        $this->signIn(['create' => true]);
        $note = $this->directory()->createObject([], '');
        $note->update($data);

        self::assertTrue($note->isAuthorized('save'));
        self::assertNull($this->check($note));

        $this->signIn(['update' => true]);
        $note = $this->directory()->createObject([], '');
        $note->update($data);

        self::assertFalse($note->isAuthorized('save'));
        self::assertSame(403, $this->check($note));
    }

    public static function providerNewObject(): array
    {
        return [
            'no key' => [['title' => 'New']],
            'chosen key' => [['title' => 'New', 'storage_key' => 'chosen']],
        ];
    }

    public function testGivenUserIsCheckedInsteadOfActiveUser(): void
    {
        $this->signIn(['create' => true, 'update' => true]);
        $note = $this->directory()->getObject('stored');
        $note->update(['title' => 'Changed']);

        self::assertNull($this->check($note));
        self::assertSame(403, $this->check($note, $this->given('admin.notes.create')));
        self::assertNull($this->check($note, $this->given('admin.notes.update')));
    }

    public function testNobodySignedInIsRefused(): void
    {
        $grav = Grav::instance();
        unset($grav['user']);
        $grav['user'] = new User(['username' => '']);

        $note = $this->directory()->getObject('stored');
        $note->update(['title' => 'Changed']);
        self::assertSame(403, $this->check($note));

        $note = $this->directory()->createObject([], '');
        $note->update(['title' => 'New']);
        self::assertSame(403, $this->check($note));
    }

    private function directory(): FlexDirectory
    {
        return new FlexDirectory('notes', GRAV_ROOT . "/{$this->folder}/notes.yaml", [
            'enabled' => true,
            'object' => ['cache' => ['index' => ['enabled' => false], 'object' => ['enabled' => false], 'render' => ['enabled' => false]]],
        ]);
    }

    private function user(array $actions): User
    {
        $user = new User(['username' => 'clerk', 'state' => 'enabled', 'access' => ['admin' => ['notes' => $actions]]]);
        $user->set('authenticated', true);
        $user->set('authorized', true);

        return $user;
    }

    /** A user to pass in, who holds the one rule. */
    private function given(string $rule): UserInterface
    {
        $user = $this->createMock(UserInterface::class);
        $user->method('authorize')->willReturnCallback(
            static fn(string $action, ?string $scope = null): ?bool => $action === $rule
        );

        return $user;
    }

    private function signIn(array $actions): void
    {
        $grav = Grav::instance();
        unset($grav['user']);
        $grav['user'] = $this->user($actions);
    }

    /**
     * @return int|null Error code of the refusal, null if the save is allowed.
     */
    private function check(FlexObjectInterface $object, ?UserInterface $user = null): ?int
    {
        try {
            $object->check($user);
        } catch (\RuntimeException $e) {
            return $e->getCode();
        }

        return null;
    }
}
