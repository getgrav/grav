<?php

use Codeception\Util\Fixtures;
use Grav\Common\Data\Blueprint;
use Grav\Common\Grav;
use Grav\Framework\Form\FormFlash;
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

    /**
     * The session id is the visitor's credential and the flash folder is not a
     * secret, so the session tokens are filled in with a hash of the id, the
     * same one FormFlash uses for its default folder.
     */
    public function testGetFlashFolder_DoesNotContainSessionId(): void
    {
        $sessionId = 'a808544e2e88640e1597e51e01d14689';
        $hashed = FormFlash::getSessionFolderName($sessionId);

        $grav = Grav::instance();
        $previous = $grav['session'];
        unset($grav['session']);
        $grav['session'] = new class ($sessionId) {
            public function __construct(private string $id)
            {
            }

            public function getId(): string
            {
                return $this->id;
            }
        };

        try {
            self::assertSame('tmp://forms/' . $hashed, $this->form(null, null)->folder());
            self::assertSame('user-data://drafts/' . $hashed, $this->form('user-data://drafts/[SESSIONID]', true)->folder());
            self::assertSame('user-data://drafts/' . $hashed, $this->form('user-data://drafts/[USERNAME_OR_SESSIONID]', true)->folder());

            // A flash created from the session id alone (as the Flex controllers do) is the form's flash.
            $flash = new FormFlash(['session_id' => $sessionId, 'unique_id' => 'abcdef1234567890', 'form_name' => 'survey']);
            $folder = $grav['locator']->findResource($this->form(null, null)->folder(), true, true);

            self::assertSame($folder . '/abcdef1234567890', $flash->getTmpDir());
            self::assertStringNotContainsString($sessionId, $flash->getTmpDir());
        } finally {
            unset($grav['session']);
            $grav['session'] = $previous;
        }
    }

    public function testGetFlashLookupFolder_KeepsTrustedFolder(): void
    {
        self::assertSame('user-data://drafts/[FORM_NAME]', $this->form('user-data://drafts/[FORM_NAME]', true)->lookupFolder());
        self::assertSame('user-data://drafts/survey', $this->form('user-data://drafts/[FORM_NAME]', true)->folder());
    }
}
