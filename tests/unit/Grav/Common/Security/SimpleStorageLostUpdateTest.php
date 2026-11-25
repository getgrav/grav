<?php

use Codeception\Util\Fixtures;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use Grav\Common\Yaml;
use Grav\Framework\File\Formatter\YamlFormatter;
use Grav\Framework\Flex\Storage\SimpleStorage;

/**
 * Class SimpleStorageLostUpdateTest
 *
 * Simple storage keeps all the rows in one file (user/config/groups.yaml for the user groups) and reads it once.
 * Saving a row must not write the other rows back as they were read: somebody else may have changed, added or
 * removed them in the meantime, and a save of one group would undo what a super admin just took from another.
 * The shared-file sibling of UserSaveLostUpdateTest.
 */
class SimpleStorageLostUpdateTest extends \PHPUnit\Framework\TestCase
{
    /** @var string */
    protected $folder;
    /** @var string */
    protected $root;

    protected function setUp(): void
    {
        parent::setUp();

        $grav = Fixtures::get('grav');
        /** @var Grav $grav */
        $grav();

        // Storage resolves a plain folder against GRAV_ROOT.
        $this->folder = 'tmp/simple-storage-lost-update-' . uniqid('', false);
        $this->root = GRAV_ROOT . '/' . $this->folder;
        mkdir($this->root, 0777, true);
    }

    protected function tearDown(): void
    {
        Folder::delete($this->root);
        parent::tearDown();
    }

    /**
     * Each storage object stands for a request: it reads the file when it is first used.
     */
    private function storage(?string $prefix = null): SimpleStorage
    {
        $storage = new SimpleStorage([
            'folder' => $this->folder . '/groups.yaml',
            'formatter' => ['class' => YamlFormatter::class],
            'key' => 'groupname',
            'prefix' => $prefix,
        ]);
        $storage->getExistingKeys();

        return $storage;
    }

    private function put(array $content): void
    {
        file_put_contents($this->root . '/groups.yaml', Yaml::dump($content));
    }

    private function stored(): array
    {
        return (array)Yaml::parse((string)file_get_contents($this->root . '/groups.yaml'));
    }

    private function rows(): array
    {
        return [
            'contractors' => ['access' => ['admin' => ['super' => true]]],
            'readers' => ['access' => ['site' => ['login' => true]]],
            'old' => ['access' => ['site' => ['login' => true]]],
        ];
    }

    public function testSaveKeepsRowsChangedByOthers(): void
    {
        $this->put($this->rows());

        $slow = $this->storage();
        $other = $this->storage();

        $other->updateRows(['contractors' => ['access' => ['admin' => ['super' => false]]]]);
        $slow->updateRows(['readers' => ['readableName' => 'Readers']]);

        $stored = $this->stored();
        self::assertFalse($stored['contractors']['access']['admin']['super']);
        self::assertSame(['readableName' => 'Readers'], $stored['readers']);
        self::assertSame(['contractors', 'readers', 'old'], array_keys($stored));

        // The storage knows the rows as they were written.
        self::assertFalse($slow->readRows(['contractors' => null])['contractors']['access']['admin']['super']);
    }

    public function testSaveKeepsRowsAddedAndRemovedByOthers(): void
    {
        $this->put($this->rows());

        $slow = $this->storage();
        $other = $this->storage();

        $other->replaceRows(['new' => ['readableName' => 'New']]);
        $other->deleteRows(['old' => []]);
        $slow->updateRows(['readers' => ['readableName' => 'Readers']]);

        $stored = $this->stored();
        self::assertSame(['readableName' => 'New'], $stored['new']);
        self::assertArrayNotHasKey('old', $stored);
        self::assertSame(['readableName' => 'Readers'], $stored['readers']);
        self::assertFalse($slow->hasKey('old'));
        self::assertTrue($slow->hasKey('new'));
    }

    public function testOwnRemovalAndRenameAreSaved(): void
    {
        $this->put($this->rows());

        $slow = $this->storage();
        $other = $this->storage();

        $other->updateRows(['contractors' => ['access' => ['admin' => ['super' => false]]]]);
        $slow->deleteRows(['old' => []]);
        $slow->replaceRows(['members' => [
            'groupname' => 'members',
            'readableName' => 'Members',
            '__META' => ['storage_key' => 'readers']
        ]]);

        $stored = $this->stored();
        self::assertFalse($stored['contractors']['access']['admin']['super']);
        self::assertArrayNotHasKey('old', $stored);
        self::assertArrayNotHasKey('readers', $stored);
        self::assertSame(['readableName' => 'Members'], $stored['members']);
    }

    public function testLaterSaveOfTheSameStorage(): void
    {
        $this->put($this->rows());

        $slow = $this->storage();
        $slow->updateRows(['readers' => ['readableName' => 'Readers']]);

        $other = $this->storage();
        $other->updateRows(['contractors' => ['access' => ['admin' => ['super' => false]]]]);
        $slow->updateRows(['old' => ['readableName' => 'Old']]);

        $stored = $this->stored();
        self::assertFalse($stored['contractors']['access']['admin']['super']);
        self::assertSame(['readableName' => 'Readers'], $stored['readers']);
        self::assertSame(['readableName' => 'Old'], $stored['old']);
    }

    public function testSaveWithPrefix(): void
    {
        $this->put(['title' => 'Groups', 'list' => $this->rows()]);

        $slow = $this->storage('list');
        $other = $this->storage('list');

        $other->updateRows(['contractors' => ['access' => ['admin' => ['super' => false]]]]);
        $slow->updateRows(['readers' => ['readableName' => 'Readers']]);

        $stored = $this->stored();
        self::assertSame('Groups', $stored['title']);
        self::assertFalse($stored['list']['contractors']['access']['admin']['super']);
        self::assertSame(['readableName' => 'Readers'], $stored['list']['readers']);
    }

    public function testRenameKeepsTheOrderOfRows(): void
    {
        $this->put($this->rows());

        $storage = $this->storage();
        $storage->replaceRows(['members' => [
            'groupname' => 'members',
            'readableName' => 'Members',
            '__META' => ['storage_key' => 'readers']
        ]]);

        $stored = $this->stored();
        self::assertSame(['contractors', 'members', 'old'], array_keys($stored));
        self::assertSame($this->rows()['contractors'], $stored['contractors']);
    }
}
