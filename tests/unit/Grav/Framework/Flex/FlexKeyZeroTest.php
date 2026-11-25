<?php

use Codeception\Util\Fixtures;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use Grav\Common\Page\Pages;
use Grav\Common\User\DataUser\User as DataUser;
use Grav\Framework\Flex\FlexDirectory;
use Grav\Framework\Object\ArrayObject;

/**
 * `0` is a key like any other, but PHP reads the string as false. A key is missing
 * only when it is the empty string: a page stored in the folder `0` exists, has a
 * path and children, and does not stop the other pages of the site from being built.
 */
class FlexKeyZeroTest extends \PHPUnit\Framework\TestCase
{
    /** @var string */
    protected $folder;
    /** @var mixed */
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();

        $grav = Fixtures::get('grav');
        /** @var Grav $grav */
        $grav = $grav();

        $this->folder = 'tmp/flex-key-zero-' . uniqid('', false);
        $this->user = $grav['user'] ?? null;

        $user = new DataUser(['username' => 'editor', 'state' => 'enabled', 'access' => ['admin' => ['pages' => true]]]);
        $user->set('authenticated', true);
        $user->set('authorized', true);

        unset($grav['user']);
        $grav['user'] = $user;
    }

    protected function tearDown(): void
    {
        $grav = Grav::instance();
        unset($grav['user'], $grav['admin']);
        $grav['user'] = $this->user;

        Folder::delete(GRAV_ROOT . '/' . $this->folder);
        parent::tearDown();
    }

    /**
     * @param string[] $pages Folders of the pages.
     */
    private function directory(array $pages): FlexDirectory
    {
        foreach ($pages as $folder) {
            $path = GRAV_ROOT . "/{$this->folder}/{$folder}";
            mkdir($path, 0777, true);
            file_put_contents("{$path}/default.md", "---\ntitle: Page\n---\n");
        }

        return new FlexDirectory('pages', 'blueprints://flex/pages.yaml', [
            'enabled' => true,
            'data' => ['storage' => ['options' => ['folder' => $this->folder, 'indexed' => false]]],
            'object' => ['cache' => ['index' => ['enabled' => false], 'object' => ['enabled' => false], 'render' => ['enabled' => false]]],
        ]);
    }

    public function testPageInFolderZeroExists(): void
    {
        $directory = $this->directory(['0', 'one', 'one/0', '01.0']);

        // Not keys of an array: PHP turns the key '0' into an integer.
        foreach ([['0', '0'], ['one/0', 'one/0'], ['01.0', '0']] as [$storageKey, $key]) {
            $page = $directory->getObject($storageKey, 'storage_key');

            self::assertNotNull($page, $storageKey);
            self::assertTrue($page->exists(), $storageKey);
            self::assertTrue($page->hasKey(), $storageKey);
            self::assertSame($key, $page->getKey());
            self::assertSame("pages.obj:{$storageKey}", $page->getFlexKey());
            self::assertSame(basename($storageKey), $page->folder());
            self::assertSame('0', $page->slug());
            self::assertSame("/{$key}", $page->rawRoute());
            self::assertSame("/{$key}", $page->getFormValue('full_route'));
            self::assertSame(GRAV_ROOT . "/{$this->folder}/{$storageKey}", $page->path());
            self::assertSame(GRAV_ROOT . "/{$this->folder}/{$storageKey}/default.md", $page->filePath());
            self::assertNotSame('', $page->getCacheKey());
        }

        self::assertSame('/one', $directory->getObject('one/0', 'storage_key')->getFormValue('route'));
    }

    public function testFolderZeroIsTheParentOfItsChildren(): void
    {
        $directory = $this->directory(['0', '0/kid', 'kid', 'other']);

        // Admin lists the children from the Flex index.
        Grav::instance()['admin'] = new stdClass();

        $zero = $directory->getObject('0', 'storage_key');
        $kid = $directory->getObject('0/kid', 'storage_key');

        self::assertSame(['0/kid'], array_map(static fn($child) => $child->getStorageKey(), array_values($zero->children()->toArray())));
        self::assertSame('0', $kid->parentStorageKey());
        self::assertSame('0', $kid->parent()->getStorageKey());
        self::assertTrue($zero->parent()->root());

        // The copy stays in the folder.
        $copy = $kid->copy();
        self::assertSame('0/kid2', $copy->getKey());
        self::assertSame('0/kid2', $copy->getStorageKey());
        self::assertSame('0', $copy->parent()->getStorageKey());
    }

    public function testFolderZeroDoesNotBreakTheSite(): void
    {
        $directory = $this->directory(['0', '0/kid', 'one']);

        $grav = Grav::instance();
        $grav['flex']->addDirectory($directory);
        $grav['config']->set('system.pages.type', 'flex');
        $grav['locator']->addPath('page', '', $this->folder, false);

        /** @var Pages $pages */
        $pages = $grav['pages'];
        $pages->register();
        $pages->init();

        $root = GRAV_ROOT . "/{$this->folder}";
        self::assertSame("{$root}/one", $pages->find('/one')->path());
        self::assertSame("{$root}/0", $pages->find('/0')->path());
        self::assertSame("{$root}/0/kid", $pages->find('/0/kid')->path());
        self::assertSame($pages->find('/0'), $pages->get("{$root}/0"));
        self::assertSame($pages->find('/0'), $pages->find('/0/kid')->parent());
        self::assertSame(["{$root}/0/kid"], array_keys($pages->find('/0')->children()->toArray()));
    }

    public function testPageIsSavedToAndDeletedFromFolderZero(): void
    {
        $directory = $this->directory(['one']);
        $root = GRAV_ROOT . "/{$this->folder}";

        $page = $directory->createObject([], 'new-page');
        $page->update(['route' => '/', 'folder' => '0', 'name' => 'default', 'content' => 'Text']);
        $page->save();

        self::assertDirectoryExists("{$root}/0");
        self::assertSame('0', $page->getMasterKey());
        self::assertSame('0', $page->getKey());
        self::assertTrue($page->exists());

        $page = $directory->createObject([], 'new-page');
        $page->update(['route' => '/0', 'folder' => 'kid', 'name' => 'default', 'content' => 'Text']);
        $page->save();

        self::assertDirectoryExists("{$root}/0/kid");

        // The folder is taken.
        try {
            $directory->createObject([], '0')->create('0');
            self::fail('Page was created over the existing one');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('Already exists', $e->getMessage());
        }

        $page = $directory->getObject('0');
        self::assertNotNull($page);
        $page->delete();

        self::assertDirectoryDoesNotExist("{$root}/0");
        self::assertDirectoryExists("{$root}/one");
    }

    public function testPageInFolderZeroIsUpdated(): void
    {
        $directory = $this->directory(['0', 'one']);
        $root = GRAV_ROOT . "/{$this->folder}";

        $page = $directory->getObject('0');
        $page->update(['content' => 'Changed']);
        $page->save();

        self::assertStringContainsString('Changed', (string)file_get_contents("{$root}/0/default.md"));
        self::assertSame(['0', 'one'], array_map('basename', glob("{$root}/*") ?: []));
        self::assertSame('0', $page->getMasterKey());
    }

    public function testPlainObjectHasKeyZero(): void
    {
        $object = new ArrayObject([], '0');

        self::assertTrue($object->hasKey());
        self::assertSame('0', $object->getKey());
        self::assertFalse((new ArrayObject([], ''))->hasKey());
    }
}
