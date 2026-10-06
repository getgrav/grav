<?php

use Grav\Common\Grav;

/**
 * #3320: a redirect target such as `/foo[301]?a=1&b=2` kept the literal `[301]`
 * in the Location header and fell back to a 302, because the code regex only
 * accepted `.\w+` or `/...` after the status code.
 */
class GravRedirectTest extends \Codeception\Test\Unit
{
    /** @var Grav */
    protected $grav;

    protected function _before(): void
    {
        $this->grav = Grav::instance();
    }

    /**
     * @dataProvider redirectProvider
     */
    public function testRedirectCodeIsStrippedFromRoute(string $route, int $code, string $location): void
    {
        $response = $this->grav->getRedirectResponse($route);
        $root = rtrim($this->grav['uri']->rootUrl(), '/');

        $this->assertSame($code, $response->getStatusCode());
        $this->assertSame($root . $location, $response->getHeaderLine('Location'));
    }

    public function redirectProvider(): array
    {
        return [
            'plain' => ['/foo[301]', 301, '/foo'],
            'bare query' => ['/foo[301]?bar', 301, '/foo?bar'],
            'query with value' => ['/foo[301]?foo=bar', 301, '/foo?foo=bar'],
            'query with several values' => ['/foo[301]?a=1&b=2', 301, '/foo?a=1&b=2'],
            'query with array values' => ['/foo[301]?a[]=1&a[]=2', 301, '/foo?a[]=1&a[]=2'],
            'other code with query' => ['/foo[302]?a=1&b=2', 302, '/foo?a=1&b=2'],
            'see other with query' => ['/foo[303]?a=1', 303, '/foo?a=1'],
            'temporary with query' => ['/foo[307]?a=1', 307, '/foo?a=1'],
            'code after a slash' => ['/foo/[301]', 301, '/foo'],
            'code after a slash with query' => ['/foo/[301]?a=1', 301, '/foo/?a=1'],
            'fragment' => ['/foo[301]#top', 301, '/foo#top'],
            'fragment with a dash' => ['/foo[301]#top-section', 301, '/foo#top-section'],
            'query and fragment' => ['/foo[301]?a=1#top', 301, '/foo?a=1#top'],
            'extension' => ['/foo[301].html', 301, '/foo.html'],
            'extension with query' => ['/foo[301].html?a=1&b=2', 301, '/foo.html?a=1&b=2'],
            'sub route' => ['/foo[301]/bar', 301, '/foo/bar'],
            'sub route with query' => ['/foo[301]/bar?a=1&b=2', 301, '/foo/bar?a=1&b=2'],
            'code at the end after a query' => ['/foo?a=1&b=2[301]', 301, '/foo?a=1&b=2'],
        ];
    }

    public function testRouteWithoutCodeUsesTheDefault(): void
    {
        $response = $this->grav->getRedirectResponse('/foo?a=1&b=2');

        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringEndsWith('/foo?a=1&b=2', $response->getHeaderLine('Location'));
    }

    public function testExplicitCodeWinsOverTheOneInTheRoute(): void
    {
        $response = $this->grav->getRedirectResponse('/foo[301]?a=1&b=2', 307);

        $this->assertSame(307, $response->getStatusCode());
        $this->assertStringEndsWith('/foo[301]?a=1&b=2', $response->getHeaderLine('Location'));
    }

    public function testExternalTargetKeepsItsQuery(): void
    {
        $response = $this->grav->getRedirectResponse('https://example.com/foo[301]?a=1&b=2');

        $this->assertSame(301, $response->getStatusCode());
        $this->assertSame('https://example.com/foo?a=1&b=2', $response->getHeaderLine('Location'));
    }
}
