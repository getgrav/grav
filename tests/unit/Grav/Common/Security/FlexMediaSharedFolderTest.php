<?php

use Codeception\Util\Fixtures;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Flex\Types\Generic\GenericObject;
use Grav\Common\Grav;
use Grav\Framework\Flex\FlexDirectory;
use Grav\Framework\Flex\Interfaces\FlexObjectInterface;
use Grav\Framework\Form\FormFlash;
use Grav\Framework\Form\FormFlashFile;

/**
 * A file field keeps its files either in the folder of the object (`self@`) or in the folder given by
 * `destination`, which then holds the files of every object of the directory, if not more.
 *
 * The permission to save an object covers its own folder only. FlexObject::update($data, $files) takes the
 * file names from the request, so in a shared folder an upload must not replace a file or become a part of
 * an existing medium, and a removed file is only taken out of the list of the object.
 */
class FlexMediaSharedFolderTest extends \PHPUnit\Framework\TestCase
{
    private const BLUEPRINT = <<<'YAML'
        title: Cards
        type: flex-objects
        config:
          data:
            storage:
              class: Grav\Framework\Flex\Storage\FolderStorage
              options:
                folder: %1$s/objects
        form:
          validation: loose
          fields:
            name:
              type: text
            files:
              type: file
            photo:
              type: file
              destination: %1$s/shared
            extra:
              type: file
              destination: self@/extra
        YAML;

    /** @var string Folder relative to GRAV_ROOT, as the storage of a Flex directory has it. */
    private string $folder;
    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();

        $grav = Fixtures::get('grav');
        /** @var Grav $grav */
        $grav = $grav();

        $this->folder = 'tmp/flex-media-shared-' . bin2hex(random_bytes(6));
        $this->tmp = GRAV_ROOT . '/' . $this->folder;
        Folder::create("{$this->tmp}/shared");
        Folder::create("{$this->tmp}/flash/unique");
        file_put_contents("{$this->tmp}/cards.yaml", sprintf(self::BLUEPRINT, $this->folder));

        // The folder has a photo of another card, and a file which is not a photo at all.
        file_put_contents("{$this->tmp}/shared/bob.png", 'bob');
        file_put_contents("{$this->tmp}/shared/bob.png.meta.yaml", 'alt_text: Bob');
        file_put_contents("{$this->tmp}/shared/guide.pdf", 'guide');
    }

    protected function tearDown(): void
    {
        Folder::delete($this->tmp);

        parent::tearDown();
    }

    /**
     * @dataProvider providerSharedNames
     */
    public function testRemovedFileStaysInSharedFolder(string $name): void
    {
        $card = $this->card(['photo' => ["{$this->folder}/shared/bob.png" => $this->info('bob.png')]]);
        $card->update([], ['photo' => [$name => null]]);

        self::assertSame([], $this->uploads($card));

        $card->save();

        self::assertSame(['bob.png', 'bob.png.meta.yaml', 'guide.pdf'], $this->files('shared'));
    }

    public static function providerSharedNames(): array
    {
        return [
            'file in the list of the object' => ['bob.png'],
            'retina image of the same medium' => ['bob@2x.png'],
            'file of another type' => ['guide.pdf'],
        ];
    }

    public function testRemovedFileIsTakenOutOfTheListOfTheObject(): void
    {
        $card = $this->card(['photo' => ["{$this->folder}/shared/bob.png" => $this->info('bob.png')]]);
        $card->update([], ['photo' => ['bob.png' => null]]);

        self::assertSame([], (array)$card->getProperty('photo'));
    }

    /**
     * @dataProvider providerConflictingNames
     */
    public function testUploadDoesNotChangeMediumInSharedFolder(string $name): void
    {
        $card = $this->card([]);
        $card->update([], ['photo' => [$name => $this->upload('photo', $name)]]);

        $uploads = array_keys($this->uploads($card));
        self::assertCount(1, $uploads);
        self::assertMatchesRegularExpression('/^\d{14}-' . preg_quote($name, '/') . '$/', $uploads[0]);

        $card->save();

        self::assertSame([$uploads[0], 'bob.png', 'bob.png.meta.yaml', 'guide.pdf'], $this->files('shared'));
        self::assertSame('bob', file_get_contents("{$this->tmp}/shared/bob.png"));
        self::assertSame('alt_text: Bob', file_get_contents("{$this->tmp}/shared/bob.png.meta.yaml"));
        self::assertSame('upload', file_get_contents("{$this->tmp}/shared/{$uploads[0]}"));
    }

    public static function providerConflictingNames(): array
    {
        return [
            'same file' => ['bob.png'],
            'retina image' => ['bob@2x.png'],
            'base image by its scale' => ['bob@1x.png'],
            'thumbnail' => ['bob.png.thumb.png'],
        ];
    }

    public function testUploadDoesNotJoinMediumWhichHasNoBaseFile(): void
    {
        rename("{$this->tmp}/shared/bob.png", "{$this->tmp}/shared/bob@2x.png");

        $card = $this->card([]);
        $card->update([], ['photo' => ['bob.png' => $this->upload('photo', 'bob.png')]]);

        self::assertMatchesRegularExpression('/^\d{14}-bob\.png$/', (string)key($this->uploads($card)));
    }

    public function testRenamedUploadIsCheckedAgain(): void
    {
        // Somebody has prepared the names which the upload would get during this test.
        for ($time = time() - 1; $time < time() + 5; $time++) {
            file_put_contents("{$this->tmp}/shared/" . date('YmdHis', $time) . '-bob@2x.png', 'bob');
        }

        $card = $this->card([]);
        $card->update([], ['photo' => ['bob.png' => $this->upload('photo', 'bob.png')]]);

        self::assertMatchesRegularExpression('/^\d{14}-\d{14}-bob\.png$/', (string)key($this->uploads($card)));
    }

    public function testNewFileKeepsItsNameInSharedFolder(): void
    {
        $card = $this->card([]);
        $card->update([], ['photo' => [
            'alice.png' => $this->upload('photo', 'alice.png'),
            'alice@2x.png' => $this->upload('photo', 'alice@2x.png'),
        ]]);

        self::assertSame(['alice.png', 'alice@2x.png'], array_keys($this->uploads($card)));
        self::assertSame(["{$this->folder}/shared/alice.png"], array_keys($card->getProperty('photo')));

        $card->save();

        self::assertSame(['alice.png', 'alice@2x.png', 'bob.png', 'bob.png.meta.yaml', 'guide.pdf'], $this->files('shared'));
    }

    public function testOwnFolderOfTheObjectIsNotShared(): void
    {
        $card = $this->card(['files' => ['own.png' => $this->info('own.png'), 'old.png' => $this->info('old.png')]]);
        $card->save();
        $own = GRAV_ROOT . '/' . $card->getMediaFolder();
        file_put_contents("{$own}/own.png", 'own');
        file_put_contents("{$own}/old.png", 'old');

        $card->update([], ['files' => ['old.png' => null, 'own.png' => $this->upload('files', 'own.png')]]);

        self::assertSame(['old.png', 'own.png'], array_keys($this->uploads($card)));

        $card->save();

        self::assertFileDoesNotExist("{$own}/old.png");
        self::assertSame('upload', file_get_contents("{$own}/own.png"));
    }

    public function testSubFolderOfTheObjectIsNotShared(): void
    {
        $card = $this->card(['extra' => ['extra/old.png' => $this->info('old.png')]]);
        $card->save();
        $own = GRAV_ROOT . '/' . $card->getMediaFolder() . '/extra';
        Folder::create($own);
        file_put_contents("{$own}/own.png", 'own');
        file_put_contents("{$own}/old.png", 'old');

        $card->update([], ['extra' => ['old.png' => null, 'own.png' => $this->upload('extra', 'own.png')]]);

        self::assertSame(['old.png', 'own.png'], array_keys($this->uploads($card)));

        $card->save();

        self::assertFileDoesNotExist("{$own}/old.png");
        self::assertSame('upload', file_get_contents("{$own}/own.png"));
    }

    public function testFileOfUndeclaredFieldIsNotQueuedInAvatarFolder(): void
    {
        $directory = new FlexDirectory('user-accounts', 'blueprints://flex/user-accounts.yaml', ['enabled' => true]);
        $meta = ['key' => 'jdoe', 'storage_key' => 'jdoe', 'storage_timestamp' => 1];

        $user = $directory->createObject(['__META' => $meta], 'jdoe');
        $user->update([], ['undeclared' => ['bob.png' => $this->upload('undeclared', 'bob.png'), 'alice.png' => null]]);

        self::assertSame([], $this->uploads($user));
    }

    public function testAvatarFolderOfUserAccountsIsShared(): void
    {
        $directory = new FlexDirectory('user-accounts', 'blueprints://flex/user-accounts.yaml', ['enabled' => true]);
        $meta = ['key' => 'jdoe', 'storage_key' => 'jdoe', 'storage_timestamp' => 1];
        $avatar = ['user/accounts/avatars/bob.png' => $this->info('bob.png')];

        $user = $directory->createObject(['__META' => $meta, 'avatar' => $avatar], 'jdoe');
        $user->update([], ['avatar' => ['bob.png' => null]]);

        self::assertSame([], $this->uploads($user));
        self::assertSame([], (array)$user->getProperty('avatar'));
    }

    private function card(array $elements): GenericObject
    {
        $directory = new FlexDirectory('cards', "{$this->tmp}/cards.yaml", ['enabled' => true]);
        $meta = ['key' => 'card', 'storage_key' => 'card', 'storage_timestamp' => 1];

        /** @var GenericObject $card */
        $card = $directory->createObject(['__META' => $meta] + $elements, 'card');

        return $card;
    }

    private function info(string $name): array
    {
        return ['name' => $name, 'type' => 'image/png', 'size' => 1, 'path' => $name];
    }

    private function upload(string $field, string $name): FormFlashFile
    {
        $flash = new FormFlash(['session_id' => 'session', 'unique_id' => 'unique', 'form_name' => 'card', 'folder' => "{$this->tmp}/flash"]);
        $tmpName = bin2hex(random_bytes(6));
        file_put_contents("{$this->tmp}/flash/unique/{$tmpName}", 'upload');

        return new FormFlashFile($field, ['name' => $name, 'type' => 'image/png', 'size' => 6, 'tmp_name' => $tmpName], $flash);
    }

    private function uploads(FlexObjectInterface $object): array
    {
        return $object->__debugInfo()['uploads:private'];
    }

    private function files(string $folder): array
    {
        $files = array_values(array_diff(scandir("{$this->tmp}/{$folder}"), ['.', '..']));
        sort($files);

        return $files;
    }
}
