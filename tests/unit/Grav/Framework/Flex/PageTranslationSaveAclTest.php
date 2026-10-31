<?php

use Codeception\Util\Fixtures;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use Grav\Common\User\DataUser\User as DataUser;
use Grav\Framework\Flex\FlexDirectory;

/**
 * Every translation of a page is a file with page permissions of its own. The data
 * sent to a page can name another language, so the user who may save the loaded
 * translation also has to be authorized for the one which gets replaced, and the
 * file of another translation is never written over.
 */
class PageTranslationSaveAclTest extends \PHPUnit\Framework\TestCase
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

        // Languages of the test site are en (default), fr and vi.
        $grav = Fixtures::get('grav');
        /** @var Grav $grav */
        $grav = $grav();

        $this->folder = 'tmp/page-translation-save-' . uniqid('', false);
        $this->user = $grav['user'] ?? null;

        $user = new DataUser(['username' => 'editor', 'state' => 'enabled', 'access' => ['site' => ['login' => true]]]);
        $user->set('authenticated', true);
        $user->set('authorized', true);

        unset($grav['user']);
        $grav['user'] = $user;
    }

    protected function tearDown(): void
    {
        $grav = Grav::instance();
        unset($grav['user']);
        $grav['user'] = $this->user;

        Folder::delete(GRAV_ROOT . '/' . $this->folder);
        parent::tearDown();
    }

    /**
     * @param array<string,string> $files Frontmatter of every file of the page. The content is the name of the file.
     */
    private function directory(array $files): FlexDirectory
    {
        mkdir(GRAV_ROOT . "/{$this->folder}/handbook", 0777, true);
        foreach ($files as $file => $frontmatter) {
            file_put_contents(GRAV_ROOT . "/{$this->folder}/handbook/{$file}", "---\ntitle: Page\n{$frontmatter}---\n{$file}");
        }

        return new FlexDirectory('pages', 'blueprints://flex/pages.yaml', [
            'enabled' => true,
            'data' => ['storage' => ['options' => ['folder' => $this->folder, 'indexed' => false]]],
            'object' => ['cache' => ['index' => ['enabled' => false], 'object' => ['enabled' => false], 'render' => ['enabled' => false]]],
        ]);
    }

    /**
     * @return array<string,string> Content of every file of the page.
     */
    private function files(): array
    {
        $list = [];
        foreach (glob(GRAV_ROOT . "/{$this->folder}/handbook/*") ?: [] as $file) {
            $parts = explode("---\n", (string)file_get_contents($file));
            $list[basename($file)] = trim((string)end($parts));
        }

        return $list;
    }

    /**
     * @dataProvider providerLockedTranslation
     */
    public function testLockedTranslationIsNotReplacedThroughAnotherOne(array $files, string $key, array $data): void
    {
        $page = $this->directory($files)->getObject($key);
        self::assertTrue($page->isAuthorized('update'));

        $page->update($data + ['content' => 'changed']);

        foreach ([null, Grav::instance()['user']] as $user) {
            $code = null;
            try {
                $page->check($user);
            } catch (\RuntimeException $e) {
                $code = $e->getCode();
            }

            self::assertSame(403, $code);
        }
    }

    public static function providerLockedTranslation(): array
    {
        return [
            'default page to translation' => [['default.md' => self::OPEN, 'default.fr.md' => self::DENY], 'handbook', ['lang' => 'fr']],
            'translation to translation' => [['default.en.md' => self::DENY, 'default.fr.md' => self::OPEN], 'handbook|.fr', ['lang' => 'en']],
            'translation with another template' => [['default.md' => self::OPEN, 'post.fr.md' => self::DENY], 'handbook', ['lang' => 'fr']],
            'new file in front of the default page' => [['default.md' => self::DENY, 'default.fr.md' => self::OPEN], 'handbook|.fr', ['lang' => 'en']],
            'new file in front of the fallback language' => [['default.en.md' => self::DENY, 'default.fr.md' => self::OPEN], 'handbook|.fr', ['lang' => 'vi']],
            'language property' => [['default.md' => self::OPEN, 'default.fr.md' => self::DENY], 'handbook', ['language' => 'fr']],
            'language in the header' => [['default.md' => self::OPEN, 'default.fr.md' => self::DENY], 'handbook', ['header' => ['lang' => 'fr']]],
        ];
    }

    public function testNoLanguageIsTheLanguageOfTheLoadedTranslation(): void
    {
        $page = $this->directory(['default.md' => self::DENY, 'default.fr.md' => self::OPEN])->getObject('handbook|.fr');

        $page->update(['lang' => '', 'content' => 'changed']);
        $page->check();
        $page->save(false);

        self::assertSame(['default.fr.md' => 'changed', 'default.md' => 'default.md'], $this->files());
    }

    /**
     * @dataProvider providerTakenFile
     */
    public function testFileOfThePageIsNotWrittenOver(string $taken, array $data, string $error): void
    {
        $page = $this->directory(['default.md' => self::OPEN, $taken => self::OPEN])->getObject('handbook');
        $page->update($data + ['content' => 'changed']);
        $page->check();

        try {
            $page->save(false);
            $error = 'The page was saved';
        } catch (\RuntimeException $e) {
            self::assertStringContainsString($error, $e->getMessage());
        }

        self::assertEquals(['default.md' => 'default.md', $taken => $taken], $this->files(), $error);
    }

    public static function providerTakenFile(): array
    {
        return [
            'language' => ['default.fr.md', ['lang' => 'fr'], 'already exists'],
            'language in the template' => ['default.fr.md', ['template' => 'default.fr'], 'Invalid page template'],
            'language in a new template' => ['default.fr.md', ['template' => 'post.fr'], 'Invalid page template'],
        ];
    }

    public function testFileOfAnotherTemplateIsNotWrittenOver(): void
    {
        $storage = $this->directory(['default.md' => self::OPEN, 'post.md' => self::OPEN])->getStorage();
        $meta = ['storage_key' => 'handbook', 'template' => 'default', 'markdown' => ['' => ['default' => 1, 'post' => 1]]];
        $row = ['folder' => 'handbook', 'template' => 'post', 'markdown' => 'changed', '__META' => $meta];

        try {
            $storage->replaceRows(['handbook' => $row]);
            $error = 'The page was saved';
        } catch (\RuntimeException $e) {
            $error = $e->getMessage();
        }

        self::assertStringContainsString('already exists', $error);
        self::assertEquals(['default.md' => 'default.md', 'post.md' => 'post.md'], $this->files());
    }

    public function testNewTranslationCanBeWritten(): void
    {
        $page = $this->directory(['default.md' => self::OPEN])->getObject('handbook');

        $page->update(['lang' => 'fr', 'content' => 'changed']);
        $page->check();
        $page->save(false);

        self::assertSame(['default.fr.md' => 'changed', 'default.md' => 'default.md'], $this->files());
    }

    public function testLoadedTranslationIsSaved(): void
    {
        $directory = $this->directory(['default.md' => self::OPEN, 'default.fr.md' => self::DENY, 'default.vi.md' => self::OPEN]);

        foreach (['handbook', 'handbook|.vi'] as $key) {
            $page = $directory->getObject($key);
            $page->update(['content' => 'changed']);
            $page->check();
            $page->save(false);
        }

        self::assertSame(['default.fr.md' => 'default.fr.md', 'default.md' => 'changed', 'default.vi.md' => 'changed'], $this->files());
    }

    public function testTemplateCanBeChanged(): void
    {
        $page = $this->directory(['default.md' => self::OPEN, 'default.fr.md' => self::DENY])->getObject('handbook');

        $page->update(['template' => 'post', 'content' => 'changed']);
        $page->check();
        $page->save(false);

        self::assertSame(['default.fr.md' => 'default.fr.md', 'post.md' => 'changed'], $this->files());
    }
}
