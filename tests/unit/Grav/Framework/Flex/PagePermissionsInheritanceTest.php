<?php

use Codeception\Util\Fixtures;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use Grav\Common\User\DataUser\User as DataUser;
use Grav\Framework\Flex\FlexDirectory;

/**
 * A page without a permission rule for the user follows the page above it.
 * Permissions only exist on Flex pages, so the parent is looked up from the
 * Flex directory: outside of admin PageObject::parent() returns the parent
 * from the page tree of the site, which is a regular page by default.
 */
class PagePermissionsInheritanceTest extends \PHPUnit\Framework\TestCase
{
    private const DENY = "permissions:\n  groups:\n    staff: { update: false }\n";
    private const GRANT = "permissions:\n  groups:\n    staff: crudl\n";
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

        $this->folder = 'tmp/page-permissions-inheritance-' . uniqid('', false);
        $this->user = $grav['user'] ?? null;
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
        $user = new DataUser(['username' => 'writer', 'state' => 'enabled', 'groups' => ['staff'], 'access' => $access]);
        $user->set('authenticated', true);
        $user->set('authorized', true);

        $grav = Grav::instance();
        unset($grav['user']);
        $grav['user'] = $user;
    }

    /**
     * @param array<string,string> $pages Frontmatter of every page, by its folder. The file is `default.md`, unless
     *                                    the folder is followed by another one (`board/default.en.md`).
     */
    private function directory(array $pages): FlexDirectory
    {
        foreach ($pages as $folder => $frontmatter) {
            $file = 'default.md';
            if (str_ends_with($folder, '.md')) {
                $file = basename($folder);
                $folder = dirname($folder);
            }
            $path = GRAV_ROOT . "/{$this->folder}/{$folder}";
            if (!is_dir($path)) {
                mkdir($path, 0777, true);
            }
            file_put_contents("{$path}/{$file}", "---\ntitle: Page\n{$frontmatter}---\n");
        }

        return new FlexDirectory('pages', 'blueprints://flex/pages.yaml', [
            'enabled' => true,
            'data' => ['storage' => ['options' => ['folder' => $this->folder, 'indexed' => false]]],
            'object' => ['cache' => ['index' => ['enabled' => false], 'object' => ['enabled' => false], 'render' => ['enabled' => false]]],
        ]);
    }

    public function testDenyCoversThePagesBelow(): void
    {
        $this->login(['site' => ['login' => true], 'admin' => ['pages' => ['update' => true]]]);
        $directory = $this->directory(['news' => '', 'board' => self::DENY, 'board/minutes' => '', 'board/minutes/january' => '']);

        self::assertTrue($directory->getObject('news')->isAuthorized('update'));
        self::assertFalse($directory->getObject('board')->isAuthorized('update'));
        self::assertFalse($directory->getObject('board/minutes')->isAuthorized('update'));
        self::assertFalse($directory->getObject('board/minutes/january')->isAuthorized('update'));
    }

    public function testGrantCoversThePagesBelow(): void
    {
        $this->login(['site' => ['login' => true]]);
        $directory = $this->directory(['news' => '', 'handbook' => self::GRANT, 'handbook/rules' => '']);

        self::assertFalse($directory->getObject('news')->isAuthorized('update'));
        self::assertTrue($directory->getObject('handbook')->isAuthorized('update'));
        self::assertTrue($directory->getObject('handbook/rules')->isAuthorized('update'));
    }

    public function testPageThatDoesNotInheritIsLeftToUserPermissions(): void
    {
        $this->login(['site' => ['login' => true], 'admin' => ['pages' => ['update' => true]]]);
        $directory = $this->directory(['board' => self::DENY, 'board/public' => self::NO_INHERIT]);

        self::assertTrue($directory->getObject('board/public')->isAuthorized('update'));
    }

    public function testInheritedPermissionsAreListed(): void
    {
        $directory = $this->directory(['board' => self::DENY, 'board/minutes' => '', 'board/public' => self::NO_INHERIT]);

        self::assertArrayHasKey('staff', $directory->getObject('board/minutes')->getPermissions(true));
        self::assertSame([], $directory->getObject('board/public')->getPermissions(true));
    }

    /**
     * Languages of the test site are en (default), fr and vi. The default language of a page is either in `default.md`
     * or in `default.en.md`, and the page below does not have to use the same one.
     *
     * @dataProvider providerParentFiles
     */
    public function testDenyCoversThePagesBelowWhicheverFileHasIt(string $parent, string $child): void
    {
        $this->login(['site' => ['login' => true], 'admin' => ['pages' => ['update' => true]]]);
        $directory = $this->directory(['news' => '', "board/{$parent}" => self::DENY, "board/minutes/{$child}" => '']);

        $minutes = $directory->getObject('board/minutes');
        if ($child !== 'default.md') {
            $minutes = $minutes->getTranslation('en');
        }

        self::assertTrue($directory->getObject('news')->isAuthorized('update'));
        self::assertArrayHasKey('staff', $minutes->getPermissions(true));
        self::assertFalse($minutes->isAuthorized('update'));
    }

    public static function providerParentFiles(): array
    {
        return [
            'both without language' => ['default.md', 'default.md'],
            'parent with language' => ['default.en.md', 'default.md'],
            'child with language' => ['default.md', 'default.en.md'],
            'both with language' => ['default.en.md', 'default.en.md'],
        ];
    }

    public function testRulesOfTheParentAreTheOnesInTheLanguageOfThePage(): void
    {
        $this->login(['site' => ['login' => true], 'admin' => ['pages' => ['update' => true]]]);
        $directory = $this->directory([
            'board/default.en.md' => '', 'board/default.fr.md' => self::DENY,
            'board/minutes' => '', 'board/proces-verbal/default.fr.md' => ''
        ]);

        self::assertTrue($directory->getObject('board/minutes')->isAuthorized('update'));
        self::assertFalse($directory->getObject('board/proces-verbal')->getTranslation('fr')->isAuthorized('update'));
    }

    /**
     * A parent with both files is served from `default.en.md` in the default language, so that is the file the
     * pages below it follow, whichever file they use themselves.
     */
    public function testParentInTheDefaultLanguageIsTheFileWithTheLanguage(): void
    {
        $this->login(['site' => ['login' => true], 'admin' => ['pages' => ['update' => true]]]);
        $directory = $this->directory([
            'board/default.md' => '', 'board/default.en.md' => self::DENY,
            'board/minutes' => '', 'board/agenda/default.en.md' => ''
        ]);

        self::assertFalse($directory->getObject('board/minutes')->isAuthorized('update'));
        self::assertFalse($directory->getObject('board/agenda')->getTranslation('en')->isAuthorized('update'));
    }

    /**
     * With regular pages on the site, PageObject::parent() returns a regular page there.
     */
    public function testInheritanceFollowsFlexParentsWhenTheSiteUsesRegularPages(): void
    {
        $this->login(['site' => ['login' => true], 'admin' => ['pages' => ['update' => true]]]);
        $directory = $this->directory(['board' => self::DENY, 'board/minutes' => '']);

        $grav = Grav::instance();
        $grav['flex']->addDirectory($directory);
        $grav['locator']->addPath('page', '', $this->folder, false);

        /** @var \Grav\Common\Page\Pages $pages */
        $pages = $grav['pages'];
        $pages->register();
        $pages->init();

        $minutes = $directory->getObject('board/minutes');
        self::assertNotInstanceOf(\Grav\Framework\Flex\Interfaces\FlexObjectInterface::class, $minutes->parent());
        self::assertArrayHasKey('staff', $minutes->getPermissions(true));
        self::assertFalse($minutes->isAuthorized('update'));
    }
}
