<?php

use Codeception\Util\Fixtures;
use Grav\Common\Service\RequestServiceProvider;
use Grav\Common\Uri;
use Nyholm\Psr7\ServerRequest;
use Pimple\Container;

class ForwardedProtocolTest extends \PHPUnit\Framework\TestCase
{
    public function testForwardedProtocolsFollowTheConfiguration(): void
    {
        $grav = Fixtures::get('grav')();
        $server = ['HTTP_HOST' => 'example.com', 'SERVER_PORT' => 8080, 'REQUEST_SCHEME' => 'http', 'REQUEST_URI' => '/'];
        foreach (['HTTP_CLOUDFRONT_FORWARDED_PROTO', 'X-FORWARDED-PROTO', 'HTTP_X_FORWARDED_PROTO'] as $header) {
            foreach ([false, true] as $enabled) {
                $grav['config']->set('system.http_x_forwarded.protocol', $enabled);
                foreach (['https' => $enabled ? 'https' : 'http', ' HTTPS ' => $enabled ? 'https' : 'http', 'invalid' => 'http', 'https://example.com/' => 'http'] as $value => $scheme) {
                    $env = $server + [$header => $value];
                    self::assertSame($scheme . '://', (new Uri($env))->scheme());
                    $request = new ServerRequest('GET', 'http://example.com:8080/', [], null, '1.1', $env);
                    self::assertSame($scheme, RequestServiceProvider::applyForwardedProtocol($request, $enabled)->getUri()->getScheme());
                }
                $env = $server + [$header => 'invalid', 'HTTPS' => 'on'];
                self::assertSame('https://', (new Uri($env))->scheme());
                if (!$enabled) {
                    self::assertSame(8080, (new Uri($env))->port());
                }
            }
        }
    }

    public function testUntrustedProtocolCannotChangeAnAbsoluteResetUrl(): void
    {
        $grav = Fixtures::get('grav')();
        $grav['config']->set('system.http_x_forwarded.protocol', false);
        $env = ['HTTP_HOST' => 'example.com', 'SERVER_PORT' => 443, 'HTTPS' => 'on', 'REQUEST_URI' => '/', 'HTTP_CLOUDFRONT_FORWARDED_PROTO' => 'https://evil.example/'];
        $uri = new Uri($env);
        self::assertSame('https://example.com', $uri->rootUrl(true));
    }

    public function testInitialRequestUsesTheServerProtocolBeforeConfigurationLoads(): void
    {
        $previous = $_SERVER;
        try {
            $_SERVER = ['REQUEST_METHOD' => 'GET', 'HTTP_HOST' => 'example.com', 'REQUEST_URI' => '/', 'HTTPS' => 'on', 'HTTP_X_FORWARDED_PROTO' => 'invalid'];
            $container = new Container();
            (new RequestServiceProvider())->register($container);
            self::assertSame('https', $container['request']->getUri()->getScheme());
            self::assertSame('invalid', $container['request']->getHeaderLine('X-Forwarded-Proto'));
        } finally {
            $_SERVER = $previous;
        }
    }

    /**
     * TLS ends at a proxy: the web server says HTTPS=on but listens on port 80 and
     * passes X-Forwarded-Proto. The request URL must not become https on port 80.
     */
    public function testTlsEndingAtAProxyKeepsTheDefaultPort(): void
    {
        $previous = $_SERVER;
        try {
            $env = ['REQUEST_METHOD' => 'GET', 'HTTP_HOST' => 'example.com', 'REQUEST_URI' => '/admin/', 'HTTPS' => 'on', 'SERVER_PORT' => '80', 'REQUEST_SCHEME' => 'http', 'HTTP_X_FORWARDED_PROTO' => 'https'];
            foreach ([false, true] as $enabled) {
                $_SERVER = $env;
                $container = new Container();
                (new RequestServiceProvider())->register($container);
                $request = RequestServiceProvider::applyForwardedProtocol($container['request'], $enabled);
                self::assertSame('https://example.com/admin/', (string)$request->getUri(), $enabled ? 'forwarding on' : 'forwarding off');
            }

            // Plain http on 443 (or https on 80) is never a port anyone typed, but a real custom port is kept.
            $_SERVER = ['REQUEST_METHOD' => 'GET', 'HTTP_HOST' => 'example.com', 'REQUEST_URI' => '/', 'SERVER_PORT' => '443', 'REQUEST_SCHEME' => 'http'];
            $container = new Container();
            (new RequestServiceProvider())->register($container);
            self::assertSame('http://example.com/', (string)$container['request']->getUri());

            $_SERVER = ['REQUEST_METHOD' => 'GET', 'HTTP_HOST' => 'example.com', 'REQUEST_URI' => '/', 'HTTPS' => 'on', 'SERVER_PORT' => '8443'];
            $container = new Container();
            (new RequestServiceProvider())->register($container);
            self::assertSame('https://example.com:8443/', (string)$container['request']->getUri());
        } finally {
            $_SERVER = $previous;
        }
    }
}
