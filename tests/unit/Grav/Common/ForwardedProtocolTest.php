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
        foreach (['HTTP_X_FORWARDED_PROTO', 'X-FORWARDED-PROTO', 'HTTP_CLOUDFRONT_FORWARDED_PROTO'] as $header) {
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
}
