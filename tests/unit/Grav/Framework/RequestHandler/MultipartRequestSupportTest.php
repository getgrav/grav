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

    private function process(string $body): ServerRequestInterface
    {
        $handler = new class implements RequestHandlerInterface {
            public ServerRequestInterface $request;

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->request = $request;

                return new Response();
            }
        };

        $request = new ServerRequest('PUT', '/', ['Content-Type' => 'multipart/form-data; boundary=b'], $body);
        (new MultipartRequestSupport())->process($request, $handler);

        return $handler->request;
    }
}
