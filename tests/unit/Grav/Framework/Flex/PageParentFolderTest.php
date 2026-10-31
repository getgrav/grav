<?php

use Codeception\Util\Fixtures;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use Grav\Common\Page\Pages;
use Grav\Common\User\DataUser\User as DataUser;
use Grav\Framework\Flex\FlexDirectory;

/**
 * The key of a page is its route: order prefixes are removed and it is lower cased,
 * so `03.team` and `Team` share the key `team`. The parent of a page is the folder
 * the page is stored in, not whichever folder answers to the first part of its key.
 */
class PageParentFolderTest extends \PHPUnit\Framework\TestCase
{
    private const OPEN = "permissions:\n  groups:\n    defaults: crudl\n";
    private const DENY = "permissions:\n  groups:\n    defaults: { update: false }\n";

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

        $this->folder = 'tmp/page-parent-folder-' . uniqid('', false);
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
     * @param array<string,string> $pages Frontmatter of every page, by its folder.
     */
    private function directory(array $pages, bool $admin): FlexDirectory
    {
        if ($admin) {
            Grav::instance()['admin'] = new stdClass();
        }

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

    public static function scopes(): array
    {
        return ['site' => [false], 'admin' => [true]];
    }

    /**
     * @dataProvider scopes
     */
    public function testParentIsTheFolderThePageIsIn(bool $admin): void
    {
        $directory = $this->directory(['03.team' => '', '03.team/contract' => '', 'Team' => '', 'Team/notes' => ''], $admin);

        // Both folders have the same key.
        self::assertSame('team', $directory->getObject('03.team', 'storage_key')->getKey());
        self::assertSame('team', $directory->getObject('Team', 'storage_key')->getKey());

        self::assertSame('03.team', $directory->getObject('team/contract')->parent()->getStorageKey());
        self::assertSame('Team', $directory->getObject('team/notes')->parent()->getStorageKey());
        self::assertTrue($directory->getObject('03.team', 'storage_key')->parent()->root());
    }

    /**
     * @dataProvider scopes
     */
    public function testFolderWithTheSameKeyDoesNotLiftInheritedPermissions(bool $admin): void
    {
        $directory = $this->directory(['03.team' => self::DENY, '03.team/contract' => ''], $admin);
        self::assertFalse($directory->getObject('team/contract')->isAuthorized('update'));

        $directory = $this->directory(['Team' => self::OPEN], $admin);
        self::assertFalse($directory->getObject('team/contract')->isAuthorized('update'));
        self::assertTrue($directory->getObject('Team', 'storage_key')->isAuthorized('update'));
    }

    public function testRoutesOfOtherPagesDoNotMakeThemParentsOnTheSite(): void
    {
        $directory = $this->directory([
            '03.team' => '', '03.team/contract' => '', 'Team' => '',
            '04.legal' => '', '04.legal/terms' => '', '05.other' => "routes:\n  aliases: ['/legal']\n",
        ], false);

        $grav = Grav::instance();
        $grav['flex']->addDirectory($directory);
        $grav['config']->set('system.pages.type', 'flex');
        $grav['locator']->addPath('page', '', $this->folder, false);

        /** @var Pages $pages */
        $pages = $grav['pages'];
        $pages->register();
        $pages->init();

        $root = GRAV_ROOT . "/{$this->folder}";
        self::assertSame("{$root}/Team", $pages->find('/team')->path());
        self::assertSame("{$root}/05.other", $pages->find('/legal')->path());

        self::assertSame("{$root}/03.team", $pages->find('/team/contract')->parent()->path());
        self::assertSame("{$root}/04.legal", $pages->find('/legal/terms')->parent()->path());
        self::assertSame($pages->get("{$root}/04.legal"), $pages->find('/legal/terms')->parent());
        self::assertSame($pages->root(), $pages->find('/team')->parent());

        // Cached pages do not have the folder which lost its route to another one.
        $pages->reset();
        self::assertNull($pages->get("{$root}/03.team"));
        self::assertSame("{$root}/03.team", $pages->find('/team/contract')->parent()->path());
    }

    /**
     * @dataProvider scopes
     */
    public function testCopyStaysInTheFolderOfThePage(bool $admin): void
    {
        $directory = $this->directory(['03.team' => '', '03.team/contract' => '', 'Team' => ''], $admin);

        self::assertSame('03.team', $directory->getObject('team/contract')->copy()->parent()->getStorageKey());
    }

    /**
     * @dataProvider scopes
     */
    public function testNewPageFindsItsParentByKey(bool $admin): void
    {
        $directory = $this->directory(['03.team' => ''], $admin);

        self::assertSame('03.team', $directory->createObject([], 'team/new')->parent()->getStorageKey());
        self::assertTrue($directory->createObject([], 'new')->parent()->root());
    }
}
