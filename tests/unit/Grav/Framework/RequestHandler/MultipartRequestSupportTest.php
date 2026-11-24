<?php

use Grav\Framework\Psr7\Response;
use Grav\Framework\RequestHandler\Middlewares\MultipartRequestSupport;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * The middleware runs first on every PUT and PATCH request, before any access check.
 */
class MultipartRequestSupportTest extends \PHPUnit\Framework\TestCase
{
    public function testFieldsAndFilesAreParsed(): void
    {
        $body = "--b\r\nContent-Disposition: form-data; name=\"title\"\r\n\r\nHello\r\n"
            . "--b\r\nContent-Disposition: form-data; name=\"file\"; filename=\"a.txt\"\r\nContent-Type: text/plain\r\n\r\nabc\r\n"
            . "--b--\r\n";
        $request = $this->process($body);

        self::assertSame(['title' => 'Hello'], $request->getParsedBody());
        self::assertSame('a.txt', $request->getUploadedFiles()['file']->getClientFilename());
        self::assertSame('text/plain', $request->getUploadedFiles()['file']->getClientMediaType());
    }

    public function testHeaderValuesKeepTheirColons(): void
    {
        $request = $this->process("--b\r\nContent-Disposition: form-data; name=\"title:part\"\r\nX-Note: one:two\r\n\r\nHello\r\n--b--\r\n");
        self::assertSame(['title:part' => 'Hello'], $request->getParsedBody());
    }

    public function testIncompletePartsAndHeaderLinesAreIgnored(): void
    {
        foreach (["Content-Disposition: form-data; name=\"title\"", "X-Note\r\n\r\nbody", "Content-Disposition: invalid\r\n\r\nbody"] as $part) {
            self::assertSame([], $this->process("--b\r\n{$part}\r\n--b--\r\n")->getParsedBody());
        }
    }

    public function testHeaderParsingScalesWithTheHeaderCount(): void
    {
        $times = [];
        foreach ([4000, 16000] as $count) {
            $lines = '';
            for ($i = 0; $i < $count; $i++) {
                $lines .= dechex($i) . ": value\r\n";
            }
            $body = "--b\r\n{$lines}Content-Disposition: form-data; name=\"title\"\r\n\r\nHello\r\n--b--\r\n";
            $runs = [];
            for ($i = 0; $i < 3; $i++) {
                $start = microtime(true);
                self::assertSame(['title' => 'Hello'], $this->process($body)->getParsedBody());
                $runs[] = microtime(true) - $start;
            }
            $times[] = min($runs);
        }
        self::assertLessThan(max(0.01, $times[0]) * 8, $times[1]);
    }

    public function testHeaderNamesSharingAHashBucketCostNoMoreThanOthers(): void
    {
        // As array keys, numeric names would be integers, and multiples of 2^20 all fall into one hash bucket.
        $times = [];
        foreach ([2 ** 20 + 1, 2 ** 20] as $step) {
            $lines = '';
            for ($i = 0; $i <= 20000; $i++) {
                $lines .= ($i * $step) . ":\r\n";
            }
            $body = "--b\r\n{$lines}Content-Disposition: form-data; name=\"title\"\r\n\r\nHello\r\n--b--\r\n";
            $runs = [];
            for ($i = 0; $i < 3; $i++) {
                $start = microtime(true);
                self::assertSame(['title' => 'Hello'], $this->process($body)->getParsedBody());
                $runs[] = microtime(true) - $start;
            }
            $times[] = min($runs);
        }
        self::assertLessThan(max(0.01, $times[0]) * 8, $times[1]);
    }

    public function testLastOfRepeatedHeadersIsUsed(): void
    {
        $request = $this->process("--b\r\nContent-Disposition: form-data; name=\"first\"\r\nCONTENT-DISPOSITION: form-data; name=\"second\"\r\n\r\nHello\r\n--b--\r\n");
        self::assertSame(['second' => 'Hello'], $request->getParsedBody());
    }

    public function testMorePartsThanAPostMayHaveAreRefused(): void
    {
        $max = (int) ini_get('max_multipart_body_parts');
        if ($max < 0) {
            $max = (int) ini_get('max_input_vars') + (int) ini_get('max_file_uploads');
        }

        $body = '';
        for ($i = 0; $i < $max; $i++) {
            $body .= "--b\r\nContent-Disposition: form-data; name=\"f{$i}\"\r\n\r\n{$i}\r\n";
        }
        $request = $this->process($body . "--b--\r\n", 'multipart/form-data; boundary=b', $response);
        self::assertNotNull($request);
        self::assertCount($max, $request->getParsedBody());
        self::assertSame(200, $response->getStatusCode());

        $body .= "--b\r\nContent-Disposition: form-data; name=\"last\"\r\n\r\nstraw\r\n";
        $request = $this->process($body . "--b--\r\n", 'multipart/form-data; boundary=b', $response);
        self::assertNull($request);
        self::assertSame(413, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->getHeaderLine('Content-Type'));
    }

    public function testBoundaryLengthIsLimitedToRfc2046(): void
    {
        foreach ([1 => ['title' => 'Hello'], 70 => ['title' => 'Hello'], 71 => [], 8000 => []] as $length => $expected) {
            $boundary = str_repeat('b', $length);
            $body = "--{$boundary}\r\nContent-Disposition: form-data; name=\"title\"\r\n\r\nHello\r\n--{$boundary}--\r\n";
            $request = $this->process($body, "multipart/form-data; boundary={$boundary}");

            self::assertSame($expected, $request->getParsedBody());
            self::assertSame([], $request->getUploadedFiles());
        }
    }

    public function testMissingOrEmptyBoundaryParsesNothing(): void
    {
        $body = "--\r\nContent-Disposition: form-data; name=\"title\"\r\n\r\nHello\r\n----\r\n";
        foreach (['multipart/form-data', 'multipart/form-data; boundary='] as $contentType) {
            $request = $this->process($body, $contentType);

            self::assertSame([], $request->getParsedBody());
            self::assertSame([], $request->getUploadedFiles());
        }
    }

    private function process(string $body, string $contentType = 'multipart/form-data; boundary=b', ?ResponseInterface &$response = null): ?ServerRequestInterface
    {
        $handler = new class implements RequestHandlerInterface {
            public ?ServerRequestInterface $request = null;

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->request = $request;

                return new Response();
            }
        };

        $request = new ServerRequest('PUT', '/', ['Content-Type' => $contentType], $body);
        $response = (new MultipartRequestSupport())->process($request, $handler);

        return $handler->request;
    }
}
