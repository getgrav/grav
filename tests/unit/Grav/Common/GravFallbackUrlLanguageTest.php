<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use RocketTheme\Toolbox\Event\Event;

/**
 * Media of a page that has no file for the active language is not served through
 * the page route: the folder is indexed without the page's header, so neither its
 * `published` state nor its `access` rules are known in that language.
 *
 * The test site has English as the default language, which French falls back to.
 */
class GravFallbackUrlLanguageTest extends \Codeception\Test\Unit
{
    /** @var Grav */
    protected $grav;

    /** @var string|null Folder of the file handed to `Utils::download()` */
    protected $served;

    /** @var callable */
    protected $listener;

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $this->grav = $grav();
        $this->grav['config']->set('system.media.allowed_fallback_types', ['txt']);
        $this->grav['locator']->addPath('page', '', 'tests/fake/untranslated-site/user/pages', false);

        // `Utils::download()` ends the request, so stop it before it sends anything.
        $this->served = null;
        $this->listener = function (Event $event): void {
            $this->served = basename(dirname((string)$event['file']));
            throw new \RuntimeException('download');
        };
        $this->grav['events']->addListener('onBeforeDownload', $this->listener);
    }

    protected function tearDown(): void
    {
        $this->grav['events']->removeListener('onBeforeDownload', $this->listener);
        $this->grav['config']->set('system.media.allowed_fallback_types', []);
        parent::tearDown();
    }

    /**
     * @return string|null Folder the file was served from
     */
    private function request(string $language, string $path): ?string
    {
        // The pages are indexed from the files of the active language.
        $this->grav['language']->setActive($language);
        $this->grav['pages']->init();

        $uri = $this->grav['uri'];
        $uri->initializeWithURL('http://testing.dev' . $path)->init();

        try {
            $this->assertFalse($this->grav->fallbackUrl($uri->path()));
        } catch (\RuntimeException) {
        }

        return $this->served;
    }

    /**
     * @dataProvider refusedProvider
     */
    public function testMediaOfAnUntranslatedPageIsNotServed(string $language, string $path): void
    {
        $this->assertNull($this->request($language, $path));
    }

    /**
     * @dataProvider servedProvider
     */
    public function testMediaIsServed(string $language, string $path, string $folder): void
    {
        $this->assertSame($folder, $this->request($language, $path));
    }

    public function testFolderBelowAPagesRootThatHoldsAPageFileIsServed(): void
    {
        // Vietnamese reads `.vi.md` and `.fr.md` only, so the `readme.md` in the
        // pages root is a page file it cannot use. The root is not a page.
        $this->grav['config']->set('system.languages.content_fallback.vi', ['fr']);

        $this->assertSame('media', $this->request('vi', '/media/notes.txt'));
    }

    public function refusedProvider(): array
    {
        return [
            'page' => ['en', '/french/notes.txt'],
            'folder below the page' => ['en', '/french/docs/notes.txt'],
            'module of the page' => ['en', '/french/_module/notes.txt'],
            'unpublished page' => ['en', '/draft/notes.txt'],
            'unpublished page, in its language' => ['fr', '/draft/notes.txt'],
        ];
    }

    public function servedProvider(): array
    {
        return [
            'in its own language' => ['fr', '/french/notes.txt', '02.french'],
            'folder below the page, in its language' => ['fr', '/french/docs/notes.txt', 'docs'],
            'module of the page, in its language' => ['fr', '/french/_module/notes.txt', '_module'],
            'translated page below it' => ['en', '/french/child/notes.txt', 'child'],
            'through the fallback language' => ['fr', '/english/notes.txt', '04.english'],
            'folder without page files' => ['en', '/media/notes.txt', 'media'],
        ];
    }
}
