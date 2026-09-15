<?php

use Codeception\Util\Fixtures;
use Grav\Common\File\CompiledYamlFile;
use Grav\Common\Grav;
use Monolog\Handler\TestHandler;

/**
 * The compiled cache (cache/compiled/files) is include()d by every request without a
 * lock, so the writer must never leave a partial file on disk, and a warning about a
 * corrupt file must only fire when nobody is in the middle of regenerating it.
 */
class CompiledFileTest extends \PHPUnit\Framework\TestCase
{
    /** @var Grav */
    protected $grav;

    /** @var TestHandler */
    protected $logHandler;

    /** @var string */
    protected $workDir;

    /** @var string[] */
    protected $cleanup = [];

    protected function setUp(): void
    {
        parent::setUp();

        $grav = Fixtures::get('grav');
        $this->grav = $grav();

        $this->logHandler = new TestHandler();
        $this->grav['log']->pushHandler($this->logHandler);

        $this->workDir = sys_get_temp_dir() . '/grav-compiled-file-' . bin2hex(random_bytes(4));
        mkdir($this->workDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->grav['log']->popHandler();

        foreach ($this->cleanup as $file) {
            @unlink($file);
        }
        $this->removeDir($this->workDir);

        parent::tearDown();
    }

    public function testWritesTheCompiledFileWithoutLeavingTemporaryFilesBehind(): void
    {
        $source = $this->writeSource('atomic.yaml', "title: Hello\nitems:\n  - one\n  - two\n");
        $compiled = $this->compiledPath($source);
        @unlink($compiled);

        $data = $this->read($source);

        self::assertSame(['title' => 'Hello', 'items' => ['one', 'two']], $data);
        self::assertFileExists($compiled);
        self::assertSame([], glob($compiled . '.*.tmp'), 'No temporary file survives the rename');

        $cache = include $compiled;
        self::assertSame(CompiledYamlFile::class, $cache['@class']);
        self::assertSame($data, $cache['data']);

        // A changed source is picked up and the compiled file replaced in one piece.
        file_put_contents($source, "title: Changed\n");
        clearstatcache();
        self::assertSame(['title' => 'Changed'], $this->read($source));
        $cache = include $compiled;
        self::assertSame(['title' => 'Changed'], $cache['data']);
        self::assertSame([], glob($compiled . '.*.tmp'));
        self::assertEmpty($this->logHandler->getRecords());
    }

    public function testCorruptCompiledFileIsRegeneratedAndLogged(): void
    {
        $source = $this->writeSource('corrupt.yaml', "title: Restored\n");
        $compiled = $this->compiledPath($source);
        file_put_contents($compiled, "<?php\nreturn [\n    '@class' => 'Grav\\\\Common\\\\File\\\\CompiledYamlFile',\n    'data' => [\n");

        self::assertSame(['title' => 'Restored'], $this->read($source));

        $cache = include $compiled;
        self::assertSame(['title' => 'Restored'], $cache['data'], 'Compiled file was rebuilt from the source');

        $records = $this->logHandler->getRecords();
        self::assertCount(1, $records, 'One warning for the corrupt file, not one per read path');
        self::assertTrue($this->logHandler->hasWarningThatContains('Corrupt compiled cache'));
        self::assertStringContainsString($source, (string)$records[0]['message']);
    }

    public function testCorruptCompiledFileHeldByAWriterIsRegeneratedQuietly(): void
    {
        $source = $this->writeSource('inflight.yaml', "title: Quiet\n");
        $compiled = $this->compiledPath($source);
        file_put_contents($compiled, "<?php\nreturn [\n    'data' => [\n");

        // Another process is mid-regeneration: it holds the exclusive lock on the file.
        $writer = fopen($compiled, 'cb+');
        self::assertTrue(flock($writer, LOCK_EX | LOCK_NB));

        try {
            self::assertSame(['title' => 'Quiet'], $this->read($source), 'The request still gets its data from the source');
            self::assertSame([], $this->logHandler->getRecords(), 'An in-flight write is not corruption');
        } finally {
            flock($writer, LOCK_UN);
            fclose($writer);
        }

        // With the lock gone the same broken file does get reported.
        file_put_contents($compiled, "<?php\nreturn [\n    'data' => [\n");
        self::assertSame(['title' => 'Quiet'], $this->read($source));
        self::assertTrue($this->logHandler->hasWarningThatContains('Corrupt compiled cache'));
    }

    /**
     * Hammer one compiled file from parallel processes: writers keep changing the source so
     * the cache is regenerated over and over, readers include the compiled file raw the whole
     * time. A reader must never see anything but a complete file.
     */
    public function testParallelReadersNeverSeeAPartialCompiledFile(): void
    {
        if (!function_exists('proc_open')) {
            $this->markTestSkipped('Needs proc_open().');
        }

        $root = dirname(__DIR__, 5);
        $cacheDir = $this->workDir . '/cache';
        $logDir = $this->workDir . '/logs';
        mkdir($cacheDir . '/compiled/files', 0777, true);
        mkdir($logDir, 0777, true);

        // A big blueprint widens the window in which a truncate-then-write would be visible.
        $lines = ["form:", "  fields:"];
        for ($i = 0; $i < 1500; $i++) {
            $lines[] = "    field_{$i}:";
            $lines[] = "      type: text";
            $lines[] = "      label: PLUGIN_EXAMPLE.FIELD_{$i}";
            $lines[] = "      help: " . str_repeat('lorem ipsum dolor sit amet ', 3);
        }
        $body = implode("\n", $lines) . "\n";
        $source = $this->workDir . '/parallel.yaml';
        $base = $this->workDir . '/parallel.base.yaml';
        file_put_contents($source, $body);
        file_put_contents($base, $body);
        $compiled = $cacheDir . '/compiled/files/' . md5($source) . '.yaml.php';

        $env = [
            'GRAV_ROOT' => $root,
            'GRAV_CACHE_PATH' => $cacheDir,
            'GRAV_LOG_PATH' => $logDir,
        ] + getenv();
        $autoload = var_export($root . '/vendor/autoload.php', true);
        $seconds = 3;

        $script = <<<PHP
            <?php
            require {$autoload};
            [, \$role, \$source, \$compiled, \$base, \$seconds] = \$argv;
            \$body = file_get_contents(\$base);
            \$end = microtime(true) + \$seconds;
            \$expected = 1500;
            \$stats = ['role' => \$role, 'iterations' => 0, 'partial' => 0, 'bad' => 0, 'samples' => []];
            \$i = 0;
            while (microtime(true) < \$end) {
                clearstatcache();
                if (\$role === 'writer') {
                    // Change the size so the compiled file is out of date and gets rewritten.
                    \$tmp = \$source . '.new';
                    file_put_contents(\$tmp, \$body . '# rev ' . \$i . ' ' . str_repeat('x', \$i % 11) . "\\n");
                    rename(\$tmp, \$source);
                } else {
                    try {
                        \$raw = include \$compiled;
                        if (!is_array(\$raw) || !isset(\$raw['@class'], \$raw['data'])) {
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
                try {
                    \$file = \\Grav\\Common\\File\\CompiledYamlFile::instance(\$source);
                    \$data = \$file->content();
                    \$file->free();
                    if (count(\$data['form']['fields'] ?? []) !== \$expected) {
                        \$stats['bad']++;
                    }
                } catch (Throwable \$e) {
                    \$stats['bad']++;
                    if (count(\$stats['samples']) < 3) {
                        \$stats['samples'][] = 'content(): ' . \$e->getMessage();
                    }
                }
                \$stats['iterations']++;
                \$i++;
            }
            echo json_encode(\$stats);
            PHP;
        $child = $this->workDir . '/child.php';
        file_put_contents($child, $script);

        // Warm the cache so every reader starts from a complete file.
        $this->assertSame([], glob($compiled . '.*.tmp'));
        $this->assertNotEmpty(shell_exec(sprintf(
            'cd %s && GRAV_ROOT=%s GRAV_CACHE_PATH=%s GRAV_LOG_PATH=%s %s -r %s',
            escapeshellarg($root),
            escapeshellarg($root),
            escapeshellarg($cacheDir),
            escapeshellarg($logDir),
            escapeshellarg(PHP_BINARY),
            escapeshellarg('require ' . $autoload . '; echo count(\Grav\Common\File\CompiledYamlFile::instance(' . var_export($source, true) . ')->content()["form"]["fields"]);')
        )));
        self::assertFileExists($compiled);

        $roles = ['writer', 'writer', 'reader', 'reader', 'reader', 'reader'];
        $processes = [];
        foreach ($roles as $n => $role) {
            $process = proc_open(
                [PHP_BINARY, '-d', 'error_reporting=-1', '-d', 'display_errors=stderr', $child, $role, $source, $compiled, $base, (string)$seconds],
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
        $bad = array_sum(array_column($results, 'bad'));
        $samples = array_merge(...array_column($results, 'samples'));

        self::assertGreaterThan(count($roles) * 10, $iterations, 'The children got enough turns for the test to mean anything');
        self::assertSame(0, $partial, "Readers saw a partial compiled file {$partial} times in {$iterations} iterations: " . implode(' | ', $samples));
        self::assertSame(0, $bad, "Wrong data {$bad} times: " . implode(' | ', $samples));
        self::assertSame([], glob($compiled . '.*.tmp'), 'No temporary file left behind');

        $log = $logDir . '/grav.log';
        self::assertStringNotContainsString('Corrupt compiled cache', is_file($log) ? file_get_contents($log) : '');
    }

    /**
     * @param string $name
     * @param string $yaml
     * @return string
     */
    private function writeSource(string $name, string $yaml): string
    {
        $source = $this->workDir . '/' . $name;
        file_put_contents($source, $yaml);
        $this->cleanup[] = $this->compiledPath($source);

        return $source;
    }

    /**
     * @param string $source
     * @return array
     */
    private function read(string $source): array
    {
        $file = CompiledYamlFile::instance($source);
        try {
            return $file->content();
        } finally {
            $file->free();
        }
    }

    /**
     * @param string $source
     * @return string
     */
    private function compiledPath(string $source): string
    {
        return CACHE_DIR . 'compiled/files/' . md5($source) . '.yaml.php';
    }

    /**
     * @param string $dir
     */
    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($dir);
    }
}
