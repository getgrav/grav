<?php

use Clockwork\Authentication\SimpleAuthenticator;
use Clockwork\Clockwork;
use Grav\Common\Config\Config;
use Grav\Common\Debugger;
use Nyholm\Psr7\ServerRequest;

/**
 * Class DebuggerAuthorizationTest
 *
 * Reads of stored profiler data must require the configured token, whatever
 * address the request arrives from.
 */
class DebuggerAuthorizationTest extends \PHPUnit\Framework\TestCase
{
    private function authorized(string $configuredToken, string $presentedToken = '', string $remote = '127.0.0.1'): bool
    {
        $debugger = new Debugger();

        $config = new ReflectionProperty(Debugger::class, 'config');
        $config->setAccessible(true);
        $config->setValue($debugger, new Config(['system' => ['debugger' => ['token' => $configuredToken]]]));

        $clockwork = new Clockwork();
        $clockwork->authenticator(new SimpleAuthenticator($configuredToken !== '' ? $configuredToken : bin2hex(random_bytes(32))));
        $property = new ReflectionProperty(Debugger::class, 'clockwork');
        $property->setAccessible(true);
        $property->setValue($debugger, $clockwork);

        $headers = $presentedToken !== '' ? ['X-Clockwork-Auth' => $presentedToken] : [];
        $request = new ServerRequest('GET', '/__clockwork/latest', $headers, null, '1.1', ['REMOTE_ADDR' => $remote]);

        $method = new ReflectionMethod(Debugger::class, 'isDebuggerRequestAuthorized');
        $method->setAccessible(true);

        return $method->invoke($debugger, $request);
    }

    public function testLoopbackRequestWithoutConfiguredTokenIsDenied(): void
    {
        $this->assertFalse($this->authorized(''));
        $this->assertFalse($this->authorized('', '', '::1'));
    }

    public function testLoopbackRequestWithoutPresentedTokenIsDenied(): void
    {
        $this->assertFalse($this->authorized('secret'));
    }

    public function testLoopbackRequestWithWrongTokenIsDenied(): void
    {
        $this->assertFalse($this->authorized('secret', 'wrong'));
    }

    public function testRawConfiguredTokenIsAccepted(): void
    {
        $this->assertTrue($this->authorized('secret', 'secret', '203.0.113.7'));
    }
}
