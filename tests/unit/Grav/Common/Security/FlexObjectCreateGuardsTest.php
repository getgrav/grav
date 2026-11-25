<?php

use Codeception\Util\Fixtures;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use Grav\Common\User\DataUser\User as DataUser;
use Grav\Framework\Flex\FlexDirectory;

/**
 * `FlexDirectory::createObject($data, $key, true)` validates and filters the data of a new object in the
 * constructor of the object. It has to use the blueprint as update() does, with the dynamic fields resolved:
 * otherwise a field guarded by `security@` (or `flex-ignore@`) is dropped from an update but kept on create,
 * and an account which may only create objects sets the fields reserved for super admins.
 */
class FlexObjectCreateGuardsTest extends \PHPUnit\Framework\TestCase
{
    private const BLUEPRINT = <<<'YAML'
        title: Notes
        type: flex-objects
        config:
          data:
            storage:
              class: Grav\Framework\Flex\Storage\SimpleStorage
              options:
                folder: %s/notes.json
        form:
          validation: loose
          fields:
            title:
              type: text
              validate:
                required: true
            published:
              type: toggle
              security@: admin.super
              validate:
                type: bool
            revision:
              type: text
              flex-ignore@: '!exists'
        YAML;

    private string $tmp;

    /** @var mixed */
    private $user;

    protected function setUp(): void
    {
        parent::setUp();

        $grav = Fixtures::get('grav');
        /** @var Grav $grav */
        $grav = $grav();

        $this->user = $grav['user'] ?? null;

        $this->tmp = sys_get_temp_dir() . '/grav-flex-create-' . bin2hex(random_bytes(6));
        Folder::create($this->tmp);
        file_put_contents("{$this->tmp}/notes.yaml", sprintf(self::BLUEPRINT, $this->tmp));
    }

    protected function tearDown(): void
    {
        $grav = Grav::instance();
        unset($grav['user']);
        $grav['user'] = $this->user;

        Folder::delete($this->tmp);

        parent::tearDown();
    }

    public function testCreateObject_ValidatedDataDropsSecurityGuardedField(): void
    {
        $this->login(['admin' => ['login' => true]]);

        $object = $this->directory()->createObject(['title' => 'Note', 'published' => true], '', true);

        self::assertSame('Note', $object->getProperty('title'));
        self::assertNull($object->getProperty('published'));
    }

    public function testCreateObject_ValidatedDataKeepsFieldForAuthorizedUser(): void
    {
        $this->login(['admin' => ['super' => true]]);

        $object = $this->directory()->createObject(['title' => 'Note', 'published' => true], '', true);

        self::assertTrue($object->getProperty('published'));
    }

    public function testCreateObject_ValidatedDataDropsFlexIgnoredField(): void
    {
        $this->login(['admin' => ['super' => true]]);

        $object = $this->directory()->createObject(['title' => 'Note', 'revision' => '7'], 'note', true);

        self::assertSame('note', $object->getKey());
        self::assertSame('Note', $object->getProperty('title'));
        self::assertNull($object->getProperty('revision'));
    }

    public function testCreateObject_ValidatedDataMatchesUpdate(): void
    {
        $this->login(['admin' => ['login' => true]]);

        $data = ['title' => 'Note', 'published' => true, 'revision' => '7'];
        $directory = $this->directory();

        $created = $directory->createObject($data, '', true);
        $updated = $directory->createObject([], '')->update($data);

        self::assertSame(
            array_filter($updated->jsonSerialize(), static fn($value) => $value !== null),
            array_filter($created->jsonSerialize(), static fn($value) => $value !== null)
        );
    }

    public function testCreateObject_ValidatedDataStillValidates(): void
    {
        $this->login(['admin' => ['super' => true]]);

        $this->expectException(\Grav\Common\Data\ValidationException::class);

        $this->directory()->createObject(['published' => true], '', true);
    }

    public function testCreateObject_UnvalidatedDataIsKept(): void
    {
        $this->login(['admin' => ['login' => true]]);

        $object = $this->directory()->createObject(['title' => 'Note', 'published' => true], '');

        self::assertTrue($object->getProperty('published'));
    }

    private function directory(): FlexDirectory
    {
        return new FlexDirectory('notes', "{$this->tmp}/notes.yaml", ['enabled' => true]);
    }

    private function login(array $access): void
    {
        $user = new DataUser(['username' => 'editor', 'state' => 'enabled', 'access' => $access]);
        $user->set('authenticated', true);
        $user->set('authorized', true);

        $grav = Grav::instance();
        unset($grav['user']);
        $grav['user'] = $user;
    }
}
