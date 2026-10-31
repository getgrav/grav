<?php

use Codeception\Util\Fixtures;
use Grav\Common\Data\Blueprint;
use Grav\Framework\Form\Traits\FormTrait;

/**
 * Class FormFlashFolderProvenanceTest
 *
 * `form.flash_folder` decides where FormFlash keeps its files. Only a trusted
 * blueprint (a file, or code) may set it; a form defined in page frontmatter
 * always uses the default folder. A folder containing a `..` path part, after
 * its tokens are filled in, is refused.
 *
 * Naming convention: test{Method}_{description}
 */
class FormFlashFolderProvenanceTest extends \PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $grav();
    }

    /**
     * @param mixed $flashFolder
     * @param bool|null $trusted
     * @param string $name
     * @return object Form exposing the protected folder getters.
     */
    private function form($flashFolder, ?bool $trusted, string $name = 'survey'): object
    {
        $form = ['fields' => ['name' => ['type' => 'text']]];
        if (null !== $flashFolder) {
            $form['flash_folder'] = $flashFolder;
        }

        // The blueprint the form plugin builds from page frontmatter.
        $blueprint = new Blueprint($name, ['form' => $form], $trusted);
        $blueprint->load()->init();

        return new class ($name, $blueprint) {
            use FormTrait;

            public function __construct(string $name, Blueprint $blueprint)
            {
                $this->name = $name;
                $this->blueprint = $blueprint;
            }

            public function getBlueprint(): Blueprint
            {
                return $this->blueprint;
            }

            public function lookupFolder(): string
            {
                return $this->getFlashLookupFolder();
            }

            public function folder(): ?string
            {
                return $this->getFlashFolder();
            }
        };
    }

    /**
     * @dataProvider providerUntrustedFolders
     * @param mixed $folder
     */
    public function testGetFlashLookupFolder_IgnoresUntrustedFolder($folder, string $description): void
    {
        self::assertSame('tmp://forms/[SESSIONID]', $this->form($folder, null)->lookupFolder(), $description);
    }

    public static function providerUntrustedFolders(): array
    {
        return [
            ['user://blueprints/pages', 'blueprint folder'],
            ['user://', 'user folder stream'],
            ['user://data', 'stored form data'],
            ['account://', 'accounts'],
            ['[ACCOUNT]', 'user media folder token'],
            ['/var/www', 'absolute path'],
            ['user/accounts', 'path relative to the Grav root'],
            ['user', 'any direct child of user/'],
            ['tmp://', 'parent of the forms folder'],
            ['tmp://sessions', 'sibling of the forms folder'],
            ['tmp://forms/[FORM_NAME]', 'inside the forms folder'],
            ['tmp://forms/../user', 'traversal out of the forms folder'],
            ['', 'empty string'],
            [['user://data'], 'not a string'],
        ];
    }

    public function testGetFlashLookupFolder_DefaultsWithoutFlashFolder(): void
    {
        self::assertSame('tmp://forms/[SESSIONID]', $this->form(null, null)->lookupFolder());
        self::assertSame('tmp://forms/[SESSIONID]', $this->form(null, true)->lookupFolder());
    }

    public function testGetFlashFolder_RefusesTraversal(): void
    {
        self::assertNull($this->form('user-data://drafts/..', true)->folder());
        self::assertNull($this->form('tmp://forms/../../user/blueprints/pages', true)->folder());
        self::assertNull($this->form('user-data://drafts/[FORM_NAME]', true, '..')->folder());
        self::assertNull($this->form('user-data://drafts/[FORM_NAME]', true, '../sessions')->folder());
        self::assertNull($this->form('user-data://drafts\\..\\sessions', true)->folder());
        self::assertNull($this->form('user-data://drafts/[FORM_NAME]', true, '..\\sessions')->folder());
    }

    /**
     * Only whole `..` path parts are refused; a name that merely contains two
     * dots is a valid folder.
     */
    public function testGetFlashFolder_KeepsNamesWithDots(): void
    {
        self::assertSame('user-data://drafts/draft..old', $this->form('user-data://drafts/draft..old', true)->folder());
        self::assertSame('user-data://drafts/v2..final', $this->form('user-data://drafts/[FORM_NAME]', true, 'v2..final')->folder());
    }

    public function testGetFlashLookupFolder_KeepsTrustedFolder(): void
    {
        self::assertSame('user-data://drafts/[FORM_NAME]', $this->form('user-data://drafts/[FORM_NAME]', true)->lookupFolder());
        self::assertSame('user-data://drafts/survey', $this->form('user-data://drafts/[FORM_NAME]', true)->folder());
    }
}
