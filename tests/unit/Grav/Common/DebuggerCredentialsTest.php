<?php

use Grav\Common\Debugger;
use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Stream;

/**
 * The Clockwork browser extension posts its password to /__clockwork/auth as
 * multipart/form-data, which PHP delivers only through the parsed body. The
 * credentials reader must accept that as well as raw JSON and query-string bodies.
 */
class DebuggerCredentialsTest extends \Codeception\Test\Unit
{
    private function credentials(ServerRequest $request): array
    {
        $method = new ReflectionMethod(Debugger::class, 'debuggerCredentials');
        $method->setAccessible(true);

        return $method->invoke(new Debugger(), $request);
    }

    public function testMultipartFormBodyFromTheExtension(): void
    {
        // What the extension's worker sends: FormData, so the raw body is a
        // multipart document and the fields live in the parsed body.
        $request = (new ServerRequest('POST', '/__clockwork/auth', ['Content-Type' => 'multipart/form-data; boundary=x']))
            ->withBody(Stream::create("--x\r\nContent-Disposition: form-data; name=\"password\"\r\n\r\nsecret\r\n--x--\r\n"))
            ->withParsedBody(['username' => '', 'password' => 'secret']);

        $this->assertSame(['username' => '', 'password' => 'secret'], $this->credentials($request));
    }

    public function testJsonBody(): void
    {
        $request = (new ServerRequest('POST', '/__clockwork/auth'))
            ->withBody(Stream::create('{"username":"u","password":"secret"}'));

        $this->assertSame(['username' => 'u', 'password' => 'secret'], $this->credentials($request));
    }

    public function testUrlEncodedBody(): void
    {
        $request = (new ServerRequest('POST', '/__clockwork/auth'))
            ->withBody(Stream::create('username=&password=secret'));

        $this->assertSame(['username' => '', 'password' => 'secret'], $this->credentials($request));
    }

    public function testEmptyRequestGivesEmptyCredentials(): void
    {
        $request = new ServerRequest('POST', '/__clockwork/auth');

        $this->assertSame(['username' => '', 'password' => ''], $this->credentials($request));
    }
}
