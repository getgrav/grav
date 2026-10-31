<?php

use Codeception\Util\Fixtures;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use Grav\Common\User\DataUser\User as DataUser;
use Grav\Framework\Flex\FlexDirectory;

/**
 * The key of a new page only tells where the page was meant to go. The submitted
 * data names the parent and the folder the page is saved to, so it is that parent
 * which has to allow the page to be created, and the folder cannot belong to
 * another page.
 */
class PageCreateParentAclTest extends \PHPUnit\Framework\TestCase
{
    private const OPEN = "permissions:\n  groups:\n    defaults: crudl\n";
    private const DENY = "permissions:\n  groups:\n    defaults: rl-cud\n";

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

        $this->folder = 'tmp/page-create-parent-' . uniqid('', false);
        $this->user = $grav['user'] ?? null;

        $this->login(['site' => ['login' => true]]);
    }

    protected function tearDown(): void
    {
        $grav = Grav::instance();
        unset($grav['user']);
        $grav['user'] = $this->user;

        Folder::delete(GRAV_ROOT . '/' . $this->folder);
        parent::tearDown();
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

    /**
     * @param array<string,string> $pages Frontmatter of every page, by its folder.
     */
    private function directory(array $pages): FlexDirectory
    {
        foreach ($pages as $folder => $frontmatter) {
            $path = GRAV_ROOT . "/{$this->folder}/{$folder}";
            mkdir($path, 0777, true);
            file_put_contents("{$path}/default.md", "---\ntitle: Page\n{$frontmatter}---\n");
        }

        return new FlexDirectory('pages', 'blueprints://flex/pages.yaml', [
            'enabled' => true,
            'data' => ['storage' => ['options' => ['folder' => $this->folder, 'indexed' => false]]],
            'object' => ['cache' => ['index' => ['enabled' => false], 'object' => ['enabled' => false], 'render' => ['enabled' => false]]],
        ]);
    }

    private function assertForbidden(callable $callable): void
    {
        try {
            $callable();
            self::fail('The page passed the check');
        } catch (\RuntimeException $e) {
            self::assertSame(403, $e->getCode());
        }
    }

    public function testNewPageIsAllowedByTheParentInTheData(): void
    {
        $directory = $this->directory(['handbook' => self::OPEN, '01.guide' => self::OPEN, 'board' => self::DENY]);

        foreach (['/handbook' => 'handbook', '/guide' => '01.guide'] as $route => $folder) {
            $page = $directory->createObject([], 'handbook/new-page');
            $page->update(['route' => $route, 'folder' => 'holidays', 'name' => 'default', 'content' => 'Text']);
            $page->check();
            $page->save();

            self::assertDirectoryExists(GRAV_ROOT . "/{$this->folder}/{$folder}/holidays");
        }
    }

    public function testNewPageIsRefusedByTheParentInTheData(): void
    {
        $directory = $this->directory(['handbook' => self::OPEN, 'board' => self::DENY]);

        // The route in the data names the parent.
        $page = $directory->createObject([], 'handbook/new-page');
        $page->update(['route' => '/board', 'folder' => 'planted', 'name' => 'default']);
        $this->assertForbidden(static function () use ($page) { $page->check(); });

        // So does a parent key given directly.
        $page = $directory->createObject([], 'handbook/new-page');
        $page->update(['parent_key' => 'board', 'folder' => 'planted']);
        $this->assertForbidden(static function () use ($page) { $page->check(); });

        // A parent which does not exist cannot allow anything.
        $page = $directory->createObject([], 'handbook/new-page');
        $page->update(['route' => '/missing', 'folder' => 'planted', 'name' => 'default']);
        $this->assertForbidden(static function () use ($page) { $page->check(); });
    }

    public function testNewTopLevelPageIsLeftToUserPermissions(): void
    {
        $directory = $this->directory(['handbook' => self::OPEN]);

        $page = $directory->createObject([], 'handbook/new-page');
        $page->update(['route' => '/', 'folder' => 'planted', 'name' => 'default', 'content' => 'Text']);
        $this->assertForbidden(static function () use ($page) { $page->check(); });

        $this->login(['site' => ['login' => true], 'admin' => ['pages' => ['create' => true]]]);

        $page->check();
        $page->save();

        self::assertDirectoryExists(GRAV_ROOT . "/{$this->folder}/planted");
    }

    public function testMovedPageIsRefusedByTheNewParent(): void
    {
        $directory = $this->directory(['handbook' => self::OPEN, 'handbook/mine' => '', 'team' => self::OPEN, 'board' => self::DENY]);

        $page = $directory->getObject('handbook/mine');
        $page->update(['route' => '/board', 'folder' => 'mine', 'name' => 'default']);
        $this->assertForbidden(static function () use ($page) { $page->check(); });

        $page = $directory->getObject('handbook/mine');
        $page->update(['route' => '/team', 'folder' => 'mine', 'name' => 'default']);
        $page->check();
        $page->save();

        self::assertFileExists(GRAV_ROOT . "/{$this->folder}/team/mine/default.md");
    }

    public function testNewPageCannotReplaceAnotherPage(): void
    {
        $directory = $this->directory(['handbook' => self::OPEN, 'handbook/policy' => self::DENY]);
        $file = GRAV_ROOT . "/{$this->folder}/handbook/policy/default.md";
        $content = file_get_contents($file);

        $page = $directory->createObject([], 'handbook/new-page');
        $page->update(['route' => '/handbook', 'folder' => 'policy', 'name' => 'default', 'content' => 'Replaced']);

        try {
            $page->save();
            self::fail('The page was saved');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('already exists', $e->getMessage());
        }

        // Another template would be another page file in the same folder.
        try {
            $directory->getStorage()->createRows([['parent_key' => 'handbook', 'folder' => 'policy', 'template' => 'other', 'markdown' => 'Replaced']]);
            self::fail('The page was saved');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('already exists', $e->getMessage());
        }

        self::assertSame($content, file_get_contents($file));
        self::assertSame(['default.md'], array_values(array_diff(scandir(dirname($file)), ['.', '..'])));
    }

    public function testNewPageCanBeCreatedInAFolderWithoutAPage(): void
    {
        $directory = $this->directory(['handbook' => self::OPEN, 'handbook/archive/2024' => self::OPEN]);
        $media = GRAV_ROOT . "/{$this->folder}/handbook/photos";
        mkdir($media);
        touch("{$media}/photo.jpg");

        foreach (['archive', 'photos'] as $folder) {
            $page = $directory->createObject([], 'handbook/new-page');
            $page->update(['route' => '/handbook', 'folder' => $folder, 'name' => 'default', 'content' => 'Text']);
            $page->check();
            $page->save();

            self::assertNotEmpty(glob(GRAV_ROOT . "/{$this->folder}/handbook/{$folder}/default*.md"));
        }

        self::assertFileExists("{$media}/photo.jpg");
        self::assertFileExists(GRAV_ROOT . "/{$this->folder}/handbook/archive/2024/default.md");
    }
}
