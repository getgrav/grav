<?php

/**
 * With zlib.output_compression on, PHP's own compression handler sits at the
 * bottom of the output buffer stack and stops being removable once it has sent
 * its first compressed chunk (16 KB). Grav used to end every buffer on its way
 * out, so ending that one raised a notice the error handler turned into an
 * exception inside shutdown(), and Whoops' own cleanup then failed on the same
 * buffer and appended a fatal error to the gzip stream (#4294).
 *
 * A buffer that cannot be removed cannot be taken off again in this process
 * either, so each case runs in a child PHP with compression on, as it is on
 * the reporter's host.
 */
class GravOutputBuffersTest extends \Codeception\Test\Unit
{
    public function testShutdownFlushStopsAtTheCompressionBuffer(): void
    {
        [$exit, $body, $stderr] = $this->runCompressed(<<<'PHP'
            ob_start();                              // Grav's own buffer
            echo str_repeat('grav ', 5000);          // 25 KB, past zlib's chunk size
            ob_start();                              // a buffer a plugin left open
            echo 'tail';
            $endOutputBuffers->invoke(null, true);   // what shutdown() runs
            fwrite(STDERR, json_encode(array_column(ob_get_status(true), 'name')));
            PHP);

        $this->assertSame(0, $exit, $stderr);
        $this->assertSame('["zlib output compression"]', $stderr);
        $this->assertSame(str_repeat('grav ', 5000) . 'tail', $body);
    }

    public function testCleanDiscardsWhatItCanAndKeepsTheCompressionBuffer(): void
    {
        [$exit, $body, $stderr] = $this->runCompressed(<<<'PHP'
            echo str_repeat('sent ', 5000);          // straight into zlib, which locks itself
            ob_start();
            echo 'discard me';
            $endOutputBuffers->invoke(null, false);  // what cleanOutputBuffers() runs
            fwrite(STDERR, json_encode(array_column(ob_get_status(true), 'name')));
            PHP);

        $this->assertSame(0, $exit, $stderr);
        $this->assertSame('["zlib output compression"]', $stderr);
        $this->assertSame(str_repeat('sent ', 5000), $body);
    }

    public function testWhoopsCleanupStopsAtTheCompressionBuffer(): void
    {
        [, $body, $stderr] = $this->runCompressed(<<<'PHP'
            echo str_repeat('sent ', 5000);
            ob_start();
            echo 'half a page';
            register_shutdown_function(static function () {
                fwrite(STDERR, json_encode(array_column(ob_get_status(true), 'name')));
            });
            $whoops->pushHandler(static function () {
                echo 'error page';
                return \Whoops\Handler\Handler::QUIT;
            });
            $whoops->handleException(new RuntimeException('boom'));
            PHP);

        $this->assertSame('["zlib output compression"]', $stderr);
        $this->assertSame(str_repeat('sent ', 5000) . 'error page', $body);
    }

    /**
     * Run $code in a child PHP with zlib.output_compression on and Grav's error
     * handling in place: every notice goes through Whoops, which throws it.
     *
     * @return array{int, string, string} exit code, gunzipped stdout, stderr
     */
    private function runCompressed(string $code): array
    {
        if (!extension_loaded('zlib') || !function_exists('proc_open')) {
            $this->markTestSkipped('Needs the zlib extension and proc_open().');
        }

        $autoload = var_export(dirname(__DIR__, 4) . '/vendor/autoload.php', true);
        $script = <<<PHP
            <?php
            require {$autoload};
            \$status = ob_get_status(true);
            if ((\$status[0]['name'] ?? '') !== 'zlib output compression') {
                exit(3);
            }
            \$endOutputBuffers = new ReflectionMethod(\\Grav\\Common\\Grav::class, 'endOutputBuffers');
            \$whoops = new \\Whoops\\Run(new \\Grav\\Common\\Errors\\SystemFacade());
            set_error_handler([\$whoops, 'handleError']);

            PHP . $code . "\n";

        $process = proc_open(
            [PHP_BINARY, '-d', 'zlib.output_compression=1', '-d', 'output_buffering=0', '-d', 'error_reporting=-1', '-d', 'display_errors=stderr'],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            // zlib only compresses for a client that asked for it.
            ['HTTP_ACCEPT_ENCODING' => 'gzip'] + getenv()
        );
        $this->assertIsResource($process);

        fwrite($pipes[0], $script);
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);

        if ($exit === 3) {
            $this->markTestSkipped('zlib.output_compression did not engage in the child process.');
        }

        $body = @gzdecode($stdout);
        $this->assertNotFalse($body, 'Response is not a clean gzip stream. stderr: ' . $stderr);

        return [$exit, $body, $stderr];
    }
}
