<?php

use Codeception\Util\Fixtures;
use Grav\Common\Config\CompiledBase;
use Grav\Common\Config\CompiledBlueprints;
use Grav\Common\Config\CompiledConfig;
use Grav\Common\Config\CompiledLanguages;
use Grav\Common\Grav;
use Grav\Common\Utils;
use Symfony\Component\Yaml\Yaml;

/**
 * The compiled configuration, blueprints and languages (cache/compiled/{config,blueprints,languages})
 * are include()d during config init by every request, without a lock and before Grav's error
 * handler exists. The writer must never leave a partial file where a reader can include it, and a
 * reader that does meet a broken file must rebuild it instead of dying on a ParseError. (#4356)
 */
class CompiledBaseTest extends \PHPUnit\Framework\TestCase
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

        $this->workDir = sys_get_temp_dir() . '/grav-compiled-base-' . bin2hex(random_bytes(4));
        mkdir($this->workDir . '/cache', 0777, true);

        $this->write('config/system.yaml', ['title' => 'Site', 'items' => ['one', 'two']]);
        $this->write('blueprints/system.yaml', ['form' => ['fields' => ['title' => ['type' => 'text', 'label' => 'Title']]]]);
        $this->write('languages/en.yaml', ['SITE' => ['TITLE' => 'Title']]);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->workDir);

        parent::tearDown();
    }

    /**
     * @return array
     */
    public function compiledTypes(): array
    {
        return [
            'config' => ['config'],
            'blueprints' => ['blueprints'],
            'languages' => ['languages'],
        ];
    }

    /**
     * @dataProvider compiledTypes
     */
    public function testAPartialCompiledFileIsRebuiltInsteadOfFatal(string $type): void
    {
        $filename = $this->workDir . '/cache/master-test.php';

        // What a reader used to include while another request was halfway through rewriting it.
        file_put_contents($filename, "<?php\nreturn [\n    '@class' => 'Grav\\\\Common\\\\Config\\\\Compiled',\n    'data' => [\n        'title' => 'Si");

        $object = $this->compiled($type)->load();

        $this->assertLoaded($type, $object);
        $cache = include $filename;
        self::assertIsArray($cache, 'The broken file was replaced by a complete one');
        self::assertSame($this->classFor($type), $cache['@class']);
    }

    /**
     * @dataProvider compiledTypes
     */
    public function testARewriteNeverTruncatesTheFileAReaderHasOpen(string $type): void
    {
        if (Utils::isWindows()) {
            $this->markTestSkipped('Windows does not replace a file another handle has open.');
        }

        $filename = $this->workDir . '/cache/master-test.php';
        $this->compiled($type)->load();
        $old = file_get_contents($filename);

        // A request that started including the old file before the rewrite.
        $reader = fopen($filename, 'rb');

        try {
            // A changed source invalidates the checksum, so the next request rewrites the file.
            $this->touchSources(time() + 10);
            $this->assertLoaded($type, $this->compiled($type)->load());
            clearstatcache();

            self::assertNotSame($old, file_get_contents($filename), 'The compiled file was rewritten');
            self::assertSame($old, stream_get_contents($reader, -1, 0), 'The open file was replaced, not truncated and refilled in place');
        } finally {
            fclose($reader);
        }

        self::assertSame([], glob($filename . '*.tmp'), 'No temporary file survives the rename');
    }

    /**
     * Hammer one compiled configuration from parallel processes: writers keep changing the file
     * list so the compiled file is rewritten over and over, readers include it raw the whole time.
     * A reader must never see anything but a complete file.
     */
    public function testParallelReadersNeverSeeAPartialCompiledConfig(): void
    {
        if (!function_exists('proc_open')) {
            $this->markTestSkipped('Needs proc_open().');
        }

        $root = dirname(__DIR__, 5);
        $cacheDir = $this->workDir . '/grav-cache';
        mkdir($cacheDir . '/compiled/files', 0777, true);

        // A big configuration widens the window in which a truncate-then-write would be visible.
        $data = [];
        for ($i = 0; $i < 3000; $i++) {
            $data["key_{$i}"] = ['label' => "PLUGIN_EXAMPLE.FIELD_{$i}", 'help' => str_repeat('lorem ipsum dolor sit amet ', 3)];
        }
        $this->write('config/system.yaml', $data);
        $compiled = $this->workDir . '/cache/master-test.php';

        $autoload = var_export($root . '/vendor/autoload.php', true);
        $seconds = 3;

        $script = <<<PHP
            <?php
            require {$autoload};
            [, \$role, \$workDir, \$seconds] = \$argv;
            \$compiled = \$workDir . '/cache/master-test.php';
            \$end = microtime(true) + \$seconds;
            \$stats = ['role' => \$role, 'iterations' => 0, 'partial' => 0, 'samples' => []];
            \$i = 0;
            while (microtime(true) < \$end) {
                clearstatcache();
                if (\$role === 'writer') {
                    // A file list the compiled file was not built from: the checksum misses and
                    // the compiled file is rewritten.
                    \$files = ['config' => ['system' => ['file' => 'config/system.yaml', 'modified' => getmypid() * 100000 + \$i]]];
                    (new \\Grav\\Common\\Config\\CompiledConfig(\$workDir . '/cache', \$files, \$workDir))->name('master-test')->load();
                } else {
                    try {
                        \$raw = include \$compiled;
                        if (!is_array(\$raw) || !isset(\$raw['@class'], \$raw['data']['system']['key_2999'])) {
                            \$stats['partial']++;
                            if (count(\$stats['samples']) < 3) {
                                \$stats['samples'][] = 'include returned ' . gettype(\$raw);
                            }
                        }
                    } catch (Throwable \$e) {
                        \$stats['partial']++;
                        if (count(\$stats['samples']) < 3) {
                            \$stats['samples'][] = \$e->getMessage();
                        }
                    }
                }
                \$stats['iterations']++;
                \$i++;
            }
            echo json_encode(\$stats);
            PHP;
        $child = $this->workDir . '/child.php';
        file_put_contents($child, $script);

        $env = [
            'GRAV_ROOT' => $root,
            'GRAV_CACHE_PATH' => $cacheDir,
            'GRAV_LOG_PATH' => $this->workDir . '/logs',
        ] + getenv();

        // Warm the compiled file so every reader starts from a complete one.
        $files = ['config' => ['system' => ['file' => 'config/system.yaml', 'modified' => 1]]];
        (new CompiledConfig($this->workDir . '/cache', $files, $this->workDir))->name('master-test')->load();
        self::assertFileExists($compiled);

        $roles = ['writer', 'writer', 'reader', 'reader', 'reader', 'reader'];
        $processes = [];
        foreach ($roles as $n => $role) {
            $process = proc_open(
                [PHP_BINARY, '-d', 'error_reporting=-1', '-d', 'display_errors=stderr', $child, $role, $this->workDir, (string)$seconds],
                [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes,
                $root,
                $env
            );
            self::assertIsResource($process);
            $processes[$n] = [$process, $pipes];
        }

        $results = [];
        foreach ($processes as $n => [$process, $pipes]) {
            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exit = proc_close($process);
            self::assertSame(0, $exit, "Child {$n} ({$roles[$n]}) failed: {$stderr}");
            $result = json_decode($stdout, true);
            self::assertIsArray($result, "Child {$n} output: {$stdout} {$stderr}");
            $results[] = $result;
        }

        $iterations = array_sum(array_column($results, 'iterations'));
        $partial = array_sum(array_column($results, 'partial'));
        $samples = array_merge(...array_column($results, 'samples'));

        self::assertGreaterThan(count($roles) * 10, $iterations, 'The children got enough turns for the test to mean anything');
        self::assertSame(0, $partial, "Readers saw a partial compiled file {$partial} times in {$iterations} iterations: " . implode(' | ', $samples));
        self::assertSame([], glob($compiled . '*.tmp'), 'No temporary file left behind');
    }

    private function compiled(string $type): CompiledBase
    {
        $class = $this->classFor($type);
        $name = $type === 'languages' ? 'en' : 'system';
        $file = "{$type}/{$name}.yaml";
        clearstatcache(true, "{$this->workDir}/{$file}");
        $files = [$type => [$name => ['file' => $file, 'modified' => filemtime("{$this->workDir}/{$file}")]]];

        return (new $class($this->workDir . '/cache', $files, $this->workDir))->name('master-test');
    }

    private function classFor(string $type): string
    {
        return ['config' => CompiledConfig::class, 'blueprints' => CompiledBlueprints::class, 'languages' => CompiledLanguages::class][$type];
    }

    private function assertLoaded(string $type, $object): void
    {
        switch ($type) {
            case 'config':
                self::assertSame('Site', $object->get('system.title'));
                break;
            case 'blueprints':
                self::assertSame('Title', $object->get('system.title')['label'] ?? null);
                break;
            case 'languages':
                self::assertSame('Title', $object->get('en.SITE.TITLE'));
                break;
        }
    }

    private function touchSources(int $time): void
    {
        foreach (['config/system.yaml', 'blueprints/system.yaml', 'languages/en.yaml'] as $file) {
            touch("{$this->workDir}/{$file}", $time);
        }
    }

    private function write(string $file, array $data): void
    {
        $path = "{$this->workDir}/{$file}";
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, Yaml::dump($data, 10));
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
