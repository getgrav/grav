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
    public function testRedirectCodeIsStrippedFromRoute(string $route, string $location): void
    {
        $response = $this->grav->getRedirectResponse($route);
        $root = rtrim($this->grav['uri']->rootUrl(), '/');

        $this->assertSame(301, $response->getStatusCode());
        $this->assertSame($root . $location, $response->getHeaderLine('Location'));
    }

    public function redirectProvider(): array
    {
        return [
            'plain' => ['/foo[301]', '/foo'],
            'bare query' => ['/foo[301]?bar', '/foo?bar'],
            'query with value' => ['/foo[301]?foo=bar', '/foo?foo=bar'],
            'query with several values' => ['/foo[301]?a=1&b=2', '/foo?a=1&b=2'],
            'fragment' => ['/foo[301]#top', '/foo#top'],
            'extension' => ['/foo[301].html', '/foo.html'],
            'sub route' => ['/foo[301]/bar', '/foo/bar'],
        ];
    }
}
