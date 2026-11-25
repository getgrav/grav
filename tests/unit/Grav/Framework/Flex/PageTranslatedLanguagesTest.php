<?php

use Codeception\Util\Fixtures;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use Grav\Framework\Flex\FlexDirectory;

/**
 * A page file without a language in its name is the translation of the default language, and
 * translatedLanguages() has to list it as such in admin too: callers take a language which is
 * missing from the list as a translation which can be created.
 */
class PageTranslatedLanguagesTest extends \PHPUnit\Framework\TestCase
{
    /** @var string */
    protected $folder;

    protected function setUp(): void
    {
        parent::setUp();

        // Languages of the test site are en (default), fr and vi.
        $grav = Fixtures::get('grav');
        $grav();

        $this->folder = 'tmp/page-translated-languages-' . uniqid('', false);
    }

    protected function tearDown(): void
    {
        unset(Grav::instance()['admin']);

        Folder::delete(GRAV_ROOT . '/' . $this->folder);
        parent::tearDown();
    }

    /**
     * @param string[] $files
     */
    private function directory(array $files): FlexDirectory
    {
        mkdir(GRAV_ROOT . "/{$this->folder}/handbook", 0777, true);
        foreach ($files as $file) {
            file_put_contents(GRAV_ROOT . "/{$this->folder}/handbook/{$file}", "---\ntitle: Page\n---\n{$file}");
        }

        return new FlexDirectory('pages', 'blueprints://flex/pages.yaml', [
            'enabled' => true,
            'data' => ['storage' => ['options' => ['folder' => $this->folder, 'indexed' => false]]],
            'object' => ['cache' => ['index' => ['enabled' => false], 'object' => ['enabled' => false], 'render' => ['enabled' => false]]],
        ]);
    }

    /**
     * @dataProvider providerFiles
     */
    public function testFileWithoutLanguageIsTheDefaultLanguage(array $files, string $key, bool $admin, array $translated, array $untranslated): void
    {
        $page = $this->directory($files)->getObject($key);
        if ($admin) {
            // As the admin and API plugins do.
            Grav::instance()['admin'] = new stdClass();
        }

        self::assertSame($translated, array_keys($page->translatedLanguages()));
        self::assertSame($untranslated, $page->untranslatedLanguages(true));
    }

    public static function providerFiles(): array
    {
        return [
            'site' => [['default.md', 'default.fr.md'], 'handbook|.fr', false, ['en', 'fr'], ['vi']],
            'admin' => [['default.md', 'default.fr.md'], 'handbook|.fr', true, ['en', 'fr'], ['vi']],
            'admin, default page' => [['default.md', 'default.fr.md'], 'handbook', true, ['en', 'fr'], ['vi']],
            'admin, only the default page' => [['default.md'], 'handbook', true, ['en'], ['fr', 'vi']],
            'admin, both files for the default language' => [['default.md', 'default.en.md'], 'handbook|.en', true, ['en'], ['fr', 'vi']],
            'admin, no default language' => [['default.fr.md', 'default.vi.md'], 'handbook|.fr', true, ['fr', 'vi'], ['en']],
        ];
    }

    public function testFileOfTheDefaultLanguageComesFirstInAdmin(): void
    {
        $page = $this->directory(['default.md', 'default.en.md', 'default.fr.md'])->getObject('handbook|.fr');
        Grav::instance()['admin'] = new stdClass();

        self::assertSame(['en' => '/en/handbook', 'fr' => '/fr/handbook'], $page->translatedLanguages());
    }
}
