<?php

use Grav\Common\Service\RequestServiceProvider;
use Pimple\Container;

/**
 * Class RequestServiceProviderTest
 */
class RequestServiceProviderTest extends \PHPUnit\Framework\TestCase
{
    /** @var array */
    protected $server;
    /** @var array */
    protected $get;

    protected function setUp(): void
    {
        parent::setUp();
        $this->server = $_SERVER;
        $this->get = $_GET;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
        $_GET = $this->get;
        parent::tearDown();
    }

    public function testQueryStringFromTheServerIsLeftAlone(): void
    {
        $server = ['REQUEST_URI' => '/api/v1/pages/home?lang=en', 'QUERY_STRING' => 'lang=en'];

        self::assertNull(RequestServiceProvider::queryFromRequestUri($server, ['lang' => 'en']));
        // PHP parsed something into $_GET: trust it, whatever QUERY_STRING says.
        self::assertNull(RequestServiceProvider::queryFromRequestUri(['REQUEST_URI' => '/a?lang=en'], ['lang' => 'fr']));
    }

    public function testRequestWithoutQueryHasNothingToRecover(): void
    {
        self::assertNull(RequestServiceProvider::queryFromRequestUri(['REQUEST_URI' => '/api/v1/pages/home'], []));
        self::assertNull(RequestServiceProvider::queryFromRequestUri(['REQUEST_URI' => '/api/v1/pages/home?', 'QUERY_STRING' => ''], []));
        self::assertNull(RequestServiceProvider::queryFromRequestUri([], []));
    }

    /**
     * nginx `try_files $uri $uri/ /index.php;` (no `?$query_string`) hands PHP an empty
     * QUERY_STRING and $_GET while REQUEST_URI keeps the query (#4338).
     */
    public function testQueryIsRecoveredFromRequestUri(): void
    {
        $server = ['REQUEST_URI' => '/api/v1/pages/home?translations=true&lang=en', 'QUERY_STRING' => ''];

        self::assertSame('translations=true&lang=en', RequestServiceProvider::queryFromRequestUri($server, []));

        unset($server['QUERY_STRING']);
        self::assertSame('translations=true&lang=en', RequestServiceProvider::queryFromRequestUri($server, []));
    }

    public function testRequestServiceReadsQueryParamsFromRequestUri(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_HOST'] = 'example.com';
        $_SERVER['REQUEST_URI'] = '/api/v1/pages/home?translations=true&lang=en&filter%5Btag%5D=a+b';
        $_SERVER['QUERY_STRING'] = '';
        $_GET = [];

        $container = new Container();
        (new RequestServiceProvider())->register($container);
        $request = $container['request'];

        self::assertSame(
            ['translations' => 'true', 'lang' => 'en', 'filter' => ['tag' => 'a b']],
            $request->getQueryParams()
        );
        self::assertSame('translations=true&lang=en&filter%5Btag%5D=a+b', $request->getUri()->getQuery());
        self::assertSame('/api/v1/pages/home', $request->getUri()->getPath());
    }

    public function testRequestServiceKeepsQueryParamsFromTheServer(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_HOST'] = 'example.com';
        $_SERVER['REQUEST_URI'] = '/api/v1/pages/home?lang=en';
        $_SERVER['QUERY_STRING'] = 'lang=en';
        $_GET = ['lang' => 'en'];

        $container = new Container();
        (new RequestServiceProvider())->register($container);

        self::assertSame(['lang' => 'en'], $container['request']->getQueryParams());
    }
}
