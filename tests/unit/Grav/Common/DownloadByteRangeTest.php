<?php

require_once GRAV_ROOT . '/tests/_support/HttpCgi.php';

class DownloadByteRangeTest extends \PHPUnit\Framework\TestCase
{
    public function testDownloadsSendOnlyTheSelectedBytes(): void
    {
        foreach (['bytes=0-0' => ['0', 206], 'bytes=2-4' => ['234', 206], 'bytes=-3' => ['789', 206], 'bytes=8-999' => ['89', 206], 'bytes=0-1,5-6' => ['01', 206], 'x' => ['0123456789', 200], 'bytes=30-' => ['0123456789', 200]] as $range => [$expected, $status]) {
            [$headers, $body] = $this->download($range, '0123456789');
            self::assertSame($expected, $body, $range);
            self::assertStringContainsString('Content-Length: ' . strlen($expected), $headers);
            self::assertSame($status === 206, str_contains($headers, 'Status: 206'));
        }
        [$headers, $body] = $this->download('bytes=0-', '');
        self::assertSame('', $body);
        self::assertStringContainsString('Content-Length: 0', $headers);
        self::assertStringNotContainsString('Status: 206', $headers);
    }

    public function testErrorResponseReplacesEarlierCacheHeaders(): void
    {
        [$exit, $output, $error] = HttpCgi::run(<<<'PHP_CODE'
            header('Cache-Control: public, max-age=600');
            header('Expires: Wed, 01 Jan 2031 00:00:00 GMT');
            header('Last-Modified: Wed, 01 Jan 2025 00:00:00 GMT');
            header('Pragma: cache');
            (new \Grav\Common\Errors\SystemFacade())->setHttpResponseCode(500);
            echo 'error';
            PHP_CODE);
        self::assertSame(0, $exit, $error);
        self::assertStringContainsString('Cache-Control: no-store, max-age=0', $output);
        foreach (['Expires:', 'Last-Modified:', 'Pragma:'] as $header) {
            self::assertStringNotContainsString($header, $output);
        }
    }

    private function download(string $range, string $contents): array
    {
        $contents = var_export($contents, true);
        [$exit, $output, $error] = HttpCgi::run(<<<PHP_CODE
            \$grav = \\Grav\\Common\\Grav::instance();
            \$grav['config'] = new \\Grav\\Common\\Config\\Config(['system' => ['cache' => ['enabled' => true], 'pages' => ['expires' => 60]]]);
            \$file = tempnam(GRAV_ROOT . '/tests/_output', 'download-');
            register_shutdown_function(static function () use (\$file) { unlink(\$file); });
            file_put_contents(\$file, {$contents});
            \\Grav\\Common\\Utils::download(\$file, true, 0, 1024, ['mime' => 'text/plain']);
            PHP_CODE, ['HTTP_RANGE' => $range]);
        self::assertSame(0, $exit, $error);
        self::assertSame('', $error);

        return explode("\r\n\r\n", $output, 2);
    }
}
