<?php

use Clockwork\Clockwork;
use Clockwork\Authentication\SimpleAuthenticator;
use Grav\Common\Config\Config;
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

        return $method->invoke(new Debugger(), $request);
    }

    private function authorized(string $configuredToken, string $presentedToken = ''): bool
    {
        $debugger = new Debugger();
        $property = new ReflectionProperty(Debugger::class, 'config');
        $property->setValue($debugger, new Config(['system' => ['debugger' => ['token' => $configuredToken]]]));

        // As Debugger::init() wires it up for the clockwork provider.
        $clockwork = new Clockwork();
        $clockwork->authenticator((new ReflectionMethod(Debugger::class, 'debuggerAuthenticator'))->invoke($debugger));
        (new ReflectionProperty(Debugger::class, 'clockwork'))->setValue($debugger, $clockwork);

        $request = new ServerRequest(
            'GET',
            '/__clockwork/latest',
            ['X-Clockwork-Auth' => $presentedToken],
            null,
            '1.1',
            ['REMOTE_ADDR' => '127.0.0.1']
        );
        $method = new ReflectionMethod(Debugger::class, 'isDebuggerRequestAuthorized');

        return $method->invoke($debugger, $request);
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

    public function testLoopbackRequestWithoutConfiguredTokenIsDenied(): void
    {
        $this->assertFalse($this->authorized(''));
    }

    public function testLoopbackRequestWithoutPresentedTokenIsDenied(): void
    {
        $this->assertFalse($this->authorized('secret'));
    }

    public function testRawConfiguredTokenIsAccepted(): void
    {
        $this->assertTrue($this->authorized('secret', 'secret'));
    }

    public function testTokenIssuedByTheAuthHandshakeIsAccepted(): void
    {
        $issued = (new SimpleAuthenticator('secret'))->attempt(['password' => 'secret']);

        $this->assertIsString($issued);
        $this->assertTrue($this->authorized('secret', $issued));
        $this->assertFalse($this->authorized('other', $issued));
    }

    /**
     * The header is the hash side of password_verify(), which runs at whatever
     * cost the hash names. Only the parameters the handshake issues may get there:
     * even a correct hash of the token at any other cost has to be turned away
     * without being computed.
     */
    public function testHashWithParametersTheHandshakeNeverIssuesIsDenied(): void
    {
        $default = password_get_info(password_hash('secret', PASSWORD_DEFAULT));
        if ($default['algo'] !== PASSWORD_BCRYPT) {
            $this->markTestSkipped('PASSWORD_DEFAULT is not bcrypt.');
        }

        $cheaper = password_hash('secret', PASSWORD_BCRYPT, ['cost' => $default['options']['cost'] - 1]);
        $this->assertTrue(password_verify('secret', $cheaper));
        $this->assertFalse($this->authorized('secret', $cheaper));

        // Other schemes password_verify() would run: legacy bcrypt prefix, SHA-512 crypt.
        $this->assertFalse($this->authorized('secret', '$2a$' . substr($cheaper, 4)));
        $this->assertFalse($this->authorized('secret', crypt('secret', '$6$rounds=1000$saltsaltsaltsalt$')));

        // Argon2 takes its memory size from the hash as well, outside memory_limit.
        if (defined('PASSWORD_ARGON2ID')) {
            $argon2id = password_hash('secret', PASSWORD_ARGON2ID, ['memory_cost' => 1024, 'time_cost' => 1, 'threads' => 1]);
            $this->assertTrue(password_verify('secret', $argon2id));
            $this->assertFalse($this->authorized('secret', $argon2id));
        }
    }
}
