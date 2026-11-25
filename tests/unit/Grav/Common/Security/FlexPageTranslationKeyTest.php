<?php

use Codeception\Util\Fixtures;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use Grav\Common\Page\Pages;
use Grav\Framework\Flex\Flex;
use Grav\Framework\Flex\FlexDirectory;

/**
 * The pages index keeps the Flex key of the translation picked for the active language,
 * for example `pages.obj:team|default.fr`. A request served from the cached index only has
 * that key, and has to get the same translation back: its rules are the ones to check.
 */
class FlexPageTranslationKeyTest extends \PHPUnit\Framework\TestCase
{
    /** @var Grav */
    protected $grav;
    /** @var string */
    protected $folder;
    /** @var FlexDirectory */
    protected $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $grav = Fixtures::get('grav');
        $this->grav = $grav();

        // Page storage resolves a plain folder against GRAV_ROOT.
        $this->folder = 'tmp/flex-page-translation-key-' . uniqid('', false);
        $this->write('team/default.md', 'Team');
        $this->write('team/default.fr.md', 'Equipe');
        $this->write('news/default.md', 'News');
        $this->write('presse/default.fr.md', 'Presse');

        $this->directory = new FlexDirectory('pages', 'blueprints://flex/pages.yaml', [
            'enabled' => true,
            'data' => ['storage' => ['options' => ['folder' => $this->folder, 'indexed' => false]]],
            'object' => ['cache' => ['index' => ['enabled' => false], 'object' => ['enabled' => false], 'render' => ['enabled' => false]]],
        ]);

        $flex = new Flex([], []);
        $flex->addDirectory($this->directory);
        unset($this->grav['flex']);
        $this->grav['flex'] = $flex;

        $this->grav['config']->set('system.pages.type', 'flex');
        $this->grav['language']->setActive('fr');
    }

    protected function tearDown(): void
    {
        Folder::delete(GRAV_ROOT . '/' . $this->folder);

        // The test switches the active language, the pages type and the Flex directories: leave a fresh instance.
        $grav = Fixtures::get('grav');
        $grav();

        parent::tearDown();
    }

    private function write(string $file, string $title): void
    {
        $path = GRAV_ROOT . "/{$this->folder}/{$file}";
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, "---\ntitle: {$title}\n---\n");
    }

    /**
     * Every call is a new request: only the index cache of the directory is shared.
     */
    private function request(): Pages
    {
        $pages = new Pages($this->grav);
        unset($this->grav['pages']);
        $this->grav['pages'] = $pages;
        $pages->register();
        $pages->init();

        return $pages;
    }

    /**
     * @return array<string,array{string,string}|null> Language and title of the page in every folder.
     */
    private function snapshot(Pages $pages): array
    {
        $list = [];
        foreach (['team', 'news', 'presse'] as $folder) {
            $page = $pages->get(GRAV_ROOT . "/{$this->folder}/{$folder}");
            $list[$folder] = $page ? [(string)$page->language(), $page->title()] : null;
        }

        return $list;
    }

    public function testStorageKeyParamsSelectTheTranslation(): void
    {
        self::assertSame('Equipe', $this->directory->getObject('team|default.fr')->title());
        self::assertSame('Equipe', $this->directory->getObject('team|.fr')->title());
        self::assertSame('Equipe', $this->directory->getObject('team|fr')->title());
        self::assertSame('Equipe', $this->directory->getObject('pages.obj:team|default.fr', 'flex_key')->title());
        self::assertSame('Team', $this->directory->getObject('team')->title());
    }

    public function testCachedIndexReturnsTheTranslationOfTheActiveLanguage(): void
    {
        $expected = ['team' => ['fr', 'Equipe'], 'news' => ['', 'News'], 'presse' => ['fr', 'Presse']];

        self::assertSame($expected, $this->snapshot($this->request()), 'Index built by this request');
        self::assertSame($expected, $this->snapshot($this->request()), 'Index read from the cache');
    }

    public function testCachedIndexOfAnotherLanguageKeepsItsPages(): void
    {
        $this->grav['language']->setActive('en');
        $expected = ['team' => ['', 'Team'], 'news' => ['', 'News'], 'presse' => null];

        self::assertSame($expected, $this->snapshot($this->request()), 'Index built by this request');
        self::assertSame($expected, $this->snapshot($this->request()), 'Index read from the cache');

        // The French index is another cache entry.
        $this->grav['language']->setActive('fr');
        self::assertSame(['fr', 'Equipe'], $this->snapshot($this->request())['team']);
        self::assertSame(['fr', 'Equipe'], $this->snapshot($this->request())['team']);
    }
}
