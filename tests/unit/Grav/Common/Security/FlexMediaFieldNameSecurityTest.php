<?php

use Codeception\Util\Fixtures;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Flex\Types\Generic\GenericObject;
use Grav\Common\Flex\Types\Pages\PageObject;
use Grav\Common\Flex\Types\Users\UserObject;
use Grav\Common\Grav;
use Grav\Framework\Flex\FlexDirectory;
use Grav\Framework\Flex\Interfaces\FlexObjectInterface;
use Grav\Framework\Form\FormFlash;
use Grav\Framework\Form\FormFlashFile;

/**
 * The files given to FlexObject::update() are applied after the blueprint has filtered the form data.
 * Only a media field which the user is allowed to edit lists its files in the object.
 */
class FlexMediaFieldNameSecurityTest extends \PHPUnit\Framework\TestCase
{
    private const BLUEPRINT = <<<'YAML'
        title: Notes
        type: flex-objects
        config:
          data:
            storage:
              class: Grav\Framework\Flex\Storage\FolderStorage
              options:
                folder: %s/objects
        form:
          validation: loose
          fields:
            title:
              type: text
            access:
              type: array
            files:
              type: file
            reviewed:
              type: file
              security@: admin.super
            locked:
              type: file
              disabled: true
        YAML;

    private const INFO = ['name' => 'a.png', 'type' => 'image/png', 'size' => 1, 'path' => 'a.png'];

    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();

        $grav = Fixtures::get('grav');
        /** @var Grav $grav */
        $grav = $grav();

        $this->tmp = sys_get_temp_dir() . '/grav-flex-media-' . bin2hex(random_bytes(6));
        Folder::create($this->tmp);
        file_put_contents("{$this->tmp}/notes.yaml", sprintf(self::BLUEPRINT, $this->tmp));
        file_put_contents("{$this->tmp}/upload", 'x');
    }

    protected function tearDown(): void
    {
        Folder::delete($this->tmp);

        parent::tearDown();
    }

    public function testFieldWhichIsNotMediaFieldDoesNotAddressPageHeader(): void
    {
        $directory = new FlexDirectory('pages', 'blueprints://flex/pages.yaml', ['enabled' => true]);
        $meta = ['key' => 'notes', 'storage_key' => 'notes', 'storage_timestamp' => 1];
        $header = ['title' => 'Notes', 'permissions' => ['authors' => ['editor'], 'groups' => ['staff' => 'crudl']]];

        /** @var PageObject $page */
        $page = $directory->createObject(['__META' => $meta, 'header' => $header], 'notes');
        $page->update([], [
            'header' => ['permissions' => null, 'title' => null],
            'header.permissions' => ['authors' => null],
            'header.permissions.groups' => ['b.png' => $this->upload('header.permissions.groups')],
            'header.permissions.groups.staff' => ['b.png' => $this->upload('header.permissions.groups.staff')],
        ]);

        self::assertSame($header, $page->header()->toArray());
    }

    public function testFieldWhichIsNotMediaFieldDoesNotAddressProperties(): void
    {
        $elements = ['title' => 'Note', 'access' => ['site' => ['login' => true]]];
        $note = $this->note($elements);
        $note->update([], [
            'access' => ['site' => null, 'b.png' => $this->upload('access')],
            'access.site' => ['login' => null],
            'unknown' => ['b.png' => $this->upload('unknown')],
        ]);

        self::assertSame($elements, $note->jsonSerialize());
        // The upload itself still goes to the media of the object, as it does when there is no field at all.
        self::assertArrayHasKey('b.png', $this->uploads($note));
    }

    public function testMediaFieldListsItsFiles(): void
    {
        $note = $this->note(['files' => ['a.png' => self::INFO, 'c.png' => ['name' => 'c.png'] + self::INFO]]);
        $note->update([], ['files' => ['a.png' => null, 'b.png' => $this->upload('files')]]);

        self::assertSame(['c.png', 'b.png'], array_keys($note->getProperty('files')));
        self::assertSame(['a.png', 'b.png'], array_keys($this->uploads($note)));
    }

    public function testMediaFieldWhichIsIgnoredInFormDataIsIgnoredInFiles(): void
    {
        $elements = ['reviewed' => ['a.png' => self::INFO], 'locked' => ['a.png' => self::INFO]];
        $note = $this->note($elements);
        $note->update([], [
            'reviewed' => ['a.png' => null, 'b.png' => $this->upload('reviewed')],
            'locked' => ['a.png' => null, 'b.png' => $this->upload('locked')],
        ]);

        self::assertSame($elements, $note->jsonSerialize());
        self::assertSame([], $this->uploads($note));
    }

    public function testUserAccountHasTheSameChecks(): void
    {
        // Accounts stored in folders have media of their own, which is where files without a media field go to.
        $options = ['class' => 'Grav\Common\Flex\Types\Users\Storage\UserFolderStorage', 'options' => ['folder' => "{$this->tmp}/accounts"]];
        $directory = new FlexDirectory('user-accounts', 'blueprints://flex/user-accounts.yaml', ['enabled' => true, 'data' => ['storage' => $options]]);
        $meta = ['key' => 'jdoe', 'storage_key' => 'jdoe', 'storage_timestamp' => 1];
        $access = ['site' => ['login' => false]];
        $limits = ['uploads' => 1];

        /** @var UserObject $user */
        $user = $directory->createObject(['__META' => $meta, 'access' => $access, 'limits' => $limits], 'jdoe');
        $user->update([], [
            'access' => ['site' => null],
            'access.site' => ['login' => null, 'b.png' => $this->upload('access.site')],
            'limits' => ['uploads' => null, 'b.png' => $this->upload('limits')],
        ]);

        self::assertSame($access, $user->get('access'));
        self::assertSame($limits, $user->get('limits'));
    }

    private function note(array $elements): GenericObject
    {
        $directory = new FlexDirectory('notes', "{$this->tmp}/notes.yaml", ['enabled' => true]);
        $meta = ['key' => 'note', 'storage_key' => 'note', 'storage_timestamp' => 1];

        /** @var GenericObject $note */
        $note = $directory->createObject(['__META' => $meta] + $elements, 'note');

        return $note;
    }

    private function upload(string $field): FormFlashFile
    {
        $flash = new FormFlash(['session_id' => 'session', 'unique_id' => 'unique', 'form_name' => 'note']);
        $upload = ['name' => 'b.png', 'type' => 'image/png', 'size' => 1, 'path' => "{$this->tmp}/upload"];

        return new FormFlashFile($field, $upload, $flash);
    }

    private function uploads(FlexObjectInterface $object): array
    {
        return $object->__debugInfo()['uploads:private'];
    }
}
