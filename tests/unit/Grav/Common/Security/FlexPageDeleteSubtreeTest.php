<?php

use Codeception\Util\Fixtures;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use Grav\Common\User\DataUser\User as DataUser;
use Grav\Framework\Flex\FlexDirectory;

/**
 * Deleting a Flex page can remove its folder, with the other translations of the
 * page and the pages below it. Each of those has to allow the delete as well. A
 * translation which leaves other page files in the folder only removes its own file.
 */
class FlexPageDeleteSubtreeTest extends \PHPUnit\Framework\TestCase
{
    private const OPEN = "permissions:\n  groups:\n    defaults: crudl\n";
    private const DENY = "permissions:\n  groups:\n    defaults: { delete: false }\n";
    private const NO_INHERIT = "permissions:\n  inherit: false\n";

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

        // Page storage resolves a plain folder against GRAV_ROOT.
        $this->folder = 'tmp/flex-page-delete-subtree-' . uniqid('', false);
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
        foreach ($pages as $file => $frontmatter) {
            // A key without a file name is the folder of a page saved in default.md.
            if (!str_ends_with($file, '.md')) {
                $file .= '/default.md';
            }
            $path = GRAV_ROOT . "/{$this->folder}/{$file}";
            if (!is_dir(dirname($path))) {
                mkdir(dirname($path), 0777, true);
            }
            file_put_contents($path, "---\ntitle: Page\n{$frontmatter}---\n");
        }

        return new FlexDirectory('pages', 'blueprints://flex/pages.yaml', [
            'enabled' => true,
            'data' => ['storage' => ['options' => ['folder' => $this->folder, 'indexed' => false]]],
            'object' => ['cache' => ['index' => ['enabled' => false], 'object' => ['enabled' => false], 'render' => ['enabled' => false]]],
        ]);
    }

    public function testChildThatDeniesDeleteStopsTheParent(): void
    {
        $directory = $this->directory(['team' => self::OPEN, 'team/contract' => self::DENY]);

        self::assertFalse($directory->getObject('team/contract')->isAuthorized('delete'));
        self::assertFalse($directory->getObject('team')->isAuthorized('delete'));

        // Only delete takes the other pages with it.
        self::assertTrue($directory->getObject('team')->isAuthorized('update'));
    }

    public function testDenyIsFoundOnEveryLevel(): void
    {
        $directory = $this->directory(['team' => self::OPEN, 'team/legal' => '', 'team/legal/contract' => self::DENY]);

        self::assertFalse($directory->getObject('team')->isAuthorized('delete'));
    }

    public function testChildThatDoesNotInheritIsLeftToUserPermissions(): void
    {
        $directory = $this->directory(['team' => self::OPEN, 'team/contract' => self::NO_INHERIT]);

        self::assertFalse($directory->getObject('team')->isAuthorized('delete'));

        $this->login(['site' => ['login' => true], 'admin' => ['pages' => ['delete' => true]]]);

        self::assertTrue($directory->getObject('team')->isAuthorized('delete'));
    }

    public function testChildrenWithoutRulesGoWithTheParent(): void
    {
        $directory = $this->directory(['team' => self::OPEN, 'team/about' => '', 'team/news' => self::OPEN]);
        mkdir(GRAV_ROOT . "/{$this->folder}/team/images");

        $team = $directory->getObject('team');
        self::assertTrue($team->isAuthorized('delete'));

        $team->delete();
        self::assertDirectoryDoesNotExist(GRAV_ROOT . "/{$this->folder}/team");
    }

    public function testTranslationThatDeniesDeleteStopsThePage(): void
    {
        $directory = $this->directory(['team/default.md' => self::OPEN, 'team/default.fr.md' => self::DENY]);

        // Deleting the page without a language removes the folder with the French translation in it.
        self::assertFalse($directory->getObject('team')->isAuthorized('delete'));
        self::assertFalse($directory->getObject('team|.fr')->isAuthorized('delete'));
    }

    public function testTranslationWhichLeavesOtherFilesOnlyDeletesItself(): void
    {
        $directory = $this->directory(['team/default.en.md' => self::OPEN, 'team/default.fr.md' => self::OPEN, 'team/contract' => self::DENY]);

        self::assertTrue($directory->getObject('team|.fr')->isAuthorized('delete'));

        $directory->getObject('team|.fr')->delete();
        self::assertFileDoesNotExist(GRAV_ROOT . "/{$this->folder}/team/default.fr.md");
        self::assertFileExists(GRAV_ROOT . "/{$this->folder}/team/contract/default.md");
    }

    public function testLastTranslationChecksThePagesBelow(): void
    {
        $directory = $this->directory(['team/default.fr.md' => self::OPEN, 'team/contract' => self::DENY]);

        self::assertFalse($directory->getObject('team|.fr')->isAuthorized('delete'));
    }

    public function testTranslationOfAPageBelowIsChecked(): void
    {
        $directory = $this->directory(['team' => self::OPEN, 'team/contract/default.md' => self::OPEN, 'team/contract/default.fr.md' => self::DENY]);

        self::assertFalse($directory->getObject('team')->isAuthorized('delete'));
    }
}
