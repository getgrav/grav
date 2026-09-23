<?php

use Codeception\Util\Fixtures;
use Grav\Common\Config\CompiledLanguages;
use Grav\Common\Config\Languages;
use Grav\Common\Grav;
use Grav\Common\Utils;
use Symfony\Component\Yaml\Yaml;

/**
 * Languages are compiled one language at a time, when they are first read. Whatever is
 * read must be exactly what the old all-languages merge produced.
 */
class CompiledLanguagesTest extends \PHPUnit\Framework\TestCase
{
    /** @var Grav */
    protected $grav;

    /** @var string */
    protected $workDir;

    protected function setUp(): void
    {
        parent::setUp();

        $grav = Fixtures::get('grav');
        $this->grav = $grav();

        $this->workDir = sys_get_temp_dir() . '/grav-compiled-languages-' . bin2hex(random_bytes(4));
        mkdir($this->workDir . '/cache', 0777, true);

        // Highest priority first, the way ConfigServiceProvider lists them.
        $this->write('user/languages/en.yaml', ['SITE' => ['TITLE' => 'User title']]);
        $this->write('system/languages/en.yaml', ['SITE' => ['TITLE' => 'System title', 'NAME' => 'Grav'], 'ONLY_SYSTEM' => 'yes']);
        $this->write('system/languages/fr.yaml', ['SITE' => ['TITLE' => 'Titre', 'NAME' => 'Grav']]);
        $this->write('system/languages/zh-cn.yaml', ['SITE' => ['TITLE' => 'lowercase code']]);
        $this->write('plugins/multi/languages.yaml', [
            'en' => ['PLUGIN_MULTI' => ['HELLO' => 'Hello'], 'SITE' => ['NAME' => 'From plugin']],
            'fr' => ['PLUGIN_MULTI' => ['HELLO' => 'Bonjour']],
            'de' => ['PLUGIN_MULTI' => ['HELLO' => 'Hallo']],
        ]);
        $this->write('plugins/single/languages/en.yaml', ['PLUGIN_SINGLE' => ['BYE' => 'Bye']]);
        $this->write('plugins/single/languages/zh-CN.yaml', ['PLUGIN_SINGLE' => ['BYE' => 'uppercase code']]);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->workDir);

        parent::tearDown();
    }

    public function testMatchesTheFullMerge(): void
    {
        $languages = $this->load();

        self::assertSame($this->fullMerge(), $languages->toArray());
        self::assertSame(['en', 'zh-CN', 'fr', 'de', 'zh-cn'], array_keys($languages->toArray()));
    }

    public function testReadsOnlyTheLanguageThatIsAskedFor(): void
    {
        $languages = $this->load();

        self::assertSame('User title', $languages->get('en.SITE.TITLE'));
        self::assertSame('Grav', $languages->get('en.SITE.NAME'));
        self::assertSame('Hello', $languages->get('en.PLUGIN_MULTI.HELLO'));
        self::assertSame('Bye', $languages->get('en.PLUGIN_SINGLE.BYE'));
        self::assertSame(['en'], $this->compiledLanguages());

        self::assertSame(['HELLO' => 'Bonjour'], $languages['fr']['PLUGIN_MULTI']);
        self::assertSame(['en', 'fr'], $this->compiledLanguages());

        $full = $this->fullMerge();
        self::assertSame(Utils::arrayFlattenDotNotation($full['de']), $languages->flattenByLang('de'));
        self::assertSame(['de', 'en', 'fr'], $this->compiledLanguages());
    }

    public function testALanguageNoFileHasIsNotCompiled(): void
    {
        $languages = $this->load();

        self::assertNull($languages->get('xx.SITE.TITLE'));
        self::assertSame('fallback', $languages->get('../../etc.SITE', 'fallback'));
        self::assertSame([], $this->compiledLanguages());
    }

    public function testCodesThatDifferOnlyByCaseKeepTheirOwnTranslations(): void
    {
        $languages = $this->load();

        self::assertSame('lowercase code', $languages->get('zh-cn.SITE.TITLE'));
        self::assertSame('uppercase code', $languages->get('zh-CN.PLUGIN_SINGLE.BYE'));
        self::assertNull($languages->get('zh-CN.SITE.TITLE'));
        self::assertCount(2, glob($this->workDir . '/cache/master-test-lang-zh-cn*.php'));

        // Read again from the compiled files.
        $languages = $this->load();
        self::assertSame('lowercase code', $languages->get('zh-cn.SITE.TITLE'));
        self::assertSame('uppercase code', $languages->get('zh-CN.PLUGIN_SINGLE.BYE'));
    }

    public function testMergesWaitForTheirLanguageAndKeepTheirOrder(): void
    {
        $merges = [
            ['fr' => ['SITE' => ['TITLE' => 'Theme titre']], 'en' => ['SITE' => ['TITLE' => 'Theme title']]],
            ['fr' => ['SITE' => ['TITLE' => 'Override titre'], 'EXTRA' => 'x'], 'it' => ['SITE' => ['TITLE' => 'Titolo']]],
        ];

        $expected = new Languages($this->fullMerge());
        foreach ($merges as $merge) {
            $expected->mergeRecursive($merge);
        }

        $languages = $this->load();
        self::assertSame('User title', $languages->get('en.SITE.TITLE'));
        foreach ($merges as $merge) {
            $languages->mergeRecursive($merge);
        }

        self::assertSame('Theme title', $languages->get('en.SITE.TITLE'));
        self::assertSame(['en'], $this->compiledLanguages(), 'A merge does not load its language');
        self::assertSame('Override titre', $languages->get('fr.SITE.TITLE'));
        self::assertSame('Grav', $languages->get('fr.SITE.NAME'));
        self::assertSame('Titolo', $languages->get('it.SITE.TITLE'));
        self::assertSame($expected->toArray(), $languages->toArray());
    }

    public function testEditingOneLanguageRebuildsOnlyThatLanguage(): void
    {
        $languages = $this->load();
        $languages->get('en.SITE.TITLE');
        $languages->get('fr.SITE.TITLE');
        $checksum = $languages->checksum();

        $en = $this->compiledFile('en');
        $enTime = filemtime($en);
        touch($en, $enTime - 100);
        clearstatcache();

        $this->write('system/languages/fr.yaml', ['SITE' => ['TITLE' => 'Nouveau titre']], time() + 10);

        $languages = $this->load();
        self::assertNotSame($checksum, $languages->checksum());
        self::assertSame('Nouveau titre', $languages->get('fr.SITE.TITLE'));
        self::assertSame('User title', $languages->get('en.SITE.TITLE'));
        clearstatcache();
        self::assertSame($enTime - 100, filemtime($en), 'English was not rebuilt');
    }

    public function testSerializesEveryLanguage(): void
    {
        $languages = $this->load();
        $languages->mergeRecursive(['it' => ['A' => 'b']]);

        $copy = unserialize(serialize($languages));
        self::assertInstanceOf(Languages::class, $copy);
        self::assertSame($languages->toArray(), $copy->toArray());
        self::assertSame('b', $copy->get('it.A'));
    }

    public function testAPlainLanguagesObjectIsUnchanged(): void
    {
        $languages = new Languages(['en' => ['A' => 'a']]);
        $languages->mergeRecursive(['fr' => ['A' => 'b']]);

        self::assertSame(['en' => ['A' => 'a'], 'fr' => ['A' => 'b']], $languages->toArray());
        self::assertSame('b', $languages->get('fr.A'));
        self::assertCount(2, $languages);
    }

    private function load(): Languages
    {
        $compiled = new CompiledLanguages($this->workDir . '/cache', $this->files(), $this->workDir);

        return $compiled->name('master-test')->load();
    }

    /**
     * The file list in the order ConfigServiceProvider builds it.
     */
    private function files(): array
    {
        $groups = [
            'user/languages' => ['en'],
            'system/languages' => ['en', 'fr', 'zh-cn'],
            'plugins' => ['plugins/multi' => 'plugins/multi/languages.yaml'],
            'plugins/single/languages' => ['en', 'zh-CN'],
        ];

        $files = [];
        foreach ($groups as $group => $list) {
            foreach ($list as $name => $file) {
                if (is_int($name)) {
                    $name = $file;
                    $file = "{$group}/{$file}.yaml";
                }
                clearstatcache(true, "{$this->workDir}/{$file}");
                $files[$group][$name] = ['file' => $file, 'modified' => filemtime("{$this->workDir}/{$file}")];
            }
        }

        return $files;
    }

    /**
     * Every language merged at once, the way CompiledLanguages worked before.
     */
    private function fullMerge(): array
    {
        $items = [];
        foreach (array_reverse($this->files()) as $list) {
            foreach ($list as $name => $item) {
                $content = Yaml::parse(file_get_contents("{$this->workDir}/{$item['file']}"));
                $items = Utils::arrayMergeRecursiveUnique($items, str_ends_with($item['file'], 'languages.yaml') ? $content : [$name => $content]);
            }
        }

        return $items;
    }

    /**
     * @return string[] Languages that have a compiled file, sorted.
     */
    private function compiledLanguages(): array
    {
        $codes = [];
        foreach (glob($this->workDir . '/cache/master-test-lang-*.php') as $file) {
            $cache = include $file;
            $codes[] = $cache['code'];
        }
        sort($codes);

        return $codes;
    }

    private function compiledFile(string $code): string
    {
        foreach (glob($this->workDir . '/cache/master-test-lang-*.php') as $file) {
            $cache = include $file;
            if ($cache['code'] === $code) {
                return $file;
            }
        }

        self::fail("No compiled file for {$code}");
    }

    private function write(string $file, array $data, ?int $time = null): void
    {
        $path = "{$this->workDir}/{$file}";
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, Yaml::dump($data, 10));
        if ($time) {
            touch($path, $time);
        }
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }
}
