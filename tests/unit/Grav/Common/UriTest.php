<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Common\Uri;
use Grav\Common\Utils;

/**
 * Class UriTest
 */
class UriTest extends \Codeception\TestCase\Test
{
    /** @var Grav $grav */
    protected $grav;

    /** @var Uri $uri */
    protected $uri;

    protected function _before()
    {
        $grav = Fixtures::get('grav');
        $this->grav = $grav();
        $this->uri = $this->grav['uri'];
    }

    protected function _after()
    {
    }

    public function testValidatingHostname()
    {
        $this->assertTrue($this->uri->validateHostname('localhost') == 1);
        $this->assertTrue($this->uri->validateHostname('google.com') == 1);
        $this->assertTrue($this->uri->validateHostname('google.it') == 1);
        $this->assertTrue($this->uri->validateHostname('goog.le') == 1);
        $this->assertTrue($this->uri->validateHostname('goog.wine') == 1);
        $this->assertTrue($this->uri->validateHostname('goog.localhost') == 1);

        $this->assertFalse($this->uri->validateHostname('localhost:80') == 1);
        $this->assertFalse($this->uri->validateHostname('http://localhost') == 1);
        $this->assertFalse($this->uri->validateHostname('localhost!') == 1);
    }

    public function testInitializingUris()
    {
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper')->init();
        $this->assertTrue($this->uri->params() == null);
        $this->assertTrue($this->uri->query() == '');

        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper:xxx')->init();
        $this->assertFalse($this->uri->params() == null);
        $this->assertTrue($this->uri->query() == '');

        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper?test=x')->init();

        $this->assertTrue($this->uri->params() == null);
        $this->assertTrue($this->uri->query() != '');
        $this->assertTrue($this->uri->query() == 'test=x');
        $this->assertTrue($this->uri->port() == '8080');

        $this->uri->initializeWithURL('http://localhost:80/grav/it/ueper?test=x')->init();
        $this->assertTrue($this->uri->port() == '80');

        $this->uri->initializeWithURL('http://localhost/grav/it/ueper?test=x')->init();
        $this->assertTrue($this->uri->port() == '80');

        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper')->init();
        $this->assertTrue($this->uri->params() == null);

        $this->uri->initializeWithURL('http://grav/grav/it/ueper')->init();
        $this->assertTrue($this->uri->params() == null);
    }

    public function testPaths()
    {
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper')->init();
        $this->assertSame(['grav', 'it', 'ueper'], $this->uri->paths());
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper:xxx')->init();
        $this->assertSame(['grav', 'it'], $this->uri->paths());
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper?test=x')->init();
        $this->assertSame(['grav', 'it', 'ueper'], $this->uri->paths());
        $this->uri->initializeWithURL('http://localhost:8080/a/b/c/d')->init();
        $this->assertSame(['a', 'b', 'c', 'd'], $this->uri->paths());
        $this->uri->initializeWithURL('http://localhost:8080/a/b/c/d/e/f/a/b/c/d/e/f/a/b/c/d/e/f')->init();
        $this->assertSame(['a', 'b', 'c', 'd', 'e', 'f', 'a', 'b', 'c', 'd', 'e', 'f', 'a', 'b', 'c', 'd', 'e', 'f'],
            $this->uri->paths());
    }

    public function testRoute()
    {
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper')->init();
        $this->assertSame('/grav/it/ueper', $this->uri->route());
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper:xxx')->init();
        $this->assertSame('/grav/it', $this->uri->route());
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper?test=x')->init();
        $this->assertSame('/grav/it/ueper', $this->uri->route());
        $this->uri->initializeWithURL('http://localhost:8080/a/b/c/d')->init();
        $this->assertSame('/a/b/c/d', $this->uri->route());
        $this->uri->initializeWithURL('http://localhost:8080/a/b/c/d/e/f/a/b/c/d/e/f/a/b/c/d/e/f')->init();
        $this->assertSame('/a/b/c/d/e/f/a/b/c/d/e/f/a/b/c/d/e/f', $this->uri->route());
    }

    public function testQuery()
    {
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper')->init();
        $this->assertSame('', $this->uri->query());
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper:xxx')->init();
        $this->assertSame('', $this->uri->query());
        $this->assertSame(null, $this->uri->query('id'));
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper?test=x')->init();
        $this->assertSame('test=x', $this->uri->query());
        $this->assertSame(null, $this->uri->query('id'));
        $this->assertSame('x', $this->uri->query('test'));
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper?test=x&test2=y')->init();
        $this->assertSame('test=x&test2=y', $this->uri->query());
        $this->assertSame(null, $this->uri->query('id'));
        $this->assertSame('x', $this->uri->query('test'));
        $this->assertSame('y', $this->uri->query('test2'));
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper?test=x&test2=y&test3=x&test4=y')->init();
        $this->assertSame('test=x&test2=y&test3=x&test4=y', $this->uri->query());
        $this->assertSame(null, $this->uri->query('id'));
        $this->assertSame('x', $this->uri->query('test'));
        $this->assertSame('y', $this->uri->query('test2'));
        $this->assertSame('y', $this->uri->query('test4'));
        //Test all after the ? is encoded in the query
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper?test=x&test2=y&test3=x&test4=y/test')->init();
        $this->assertSame('test=x&test2=y&test3=x&test4=y%2Ftest', $this->uri->query());
        $this->assertSame(null, $this->uri->query('id'));
        $this->assertSame('x', $this->uri->query('test'));
        $this->assertSame('y', $this->uri->query('test2'));
        $this->assertSame('y/test', $this->uri->query('test4'));
        $this->uri->initializeWithURL('http://localhost:8080/a/b/c/d')->init();
        $this->assertSame('', $this->uri->query());
        $this->assertSame(null, $this->uri->query('id'));
        $this->uri->initializeWithURL('http://localhost:8080/a/b/c/d/e/f/a/b/c/d/e/f/a/b/c/d/e/f')->init();
        $this->assertSame('', $this->uri->query());
        $this->assertSame(null, $this->uri->query('id'));
    }

    public function testParams()
    {
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper')->init();
        $this->assertSame(null, $this->uri->params());
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper:xxx')->init();
        $this->assertSame('/ueper:xxx', $this->uri->params());
        $this->assertSame('/ueper:xxx', $this->uri->params('ueper'));
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper:xxx/test:yyy')->init();
        $this->assertSame('/ueper:xxx/test:yyy', $this->uri->params());
        $this->assertSame('/ueper:xxx', $this->uri->params('ueper'));
        $this->assertSame('/test:yyy', $this->uri->params('test'));
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper:xxx++/test:yyy')->init();
        $this->assertSame('/ueper:xxx++/test:yyy', $this->uri->params());
        $this->assertSame('/ueper:xxx++', $this->uri->params('ueper'));
        $this->assertSame('/test:yyy', $this->uri->params('test'));
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper:xxx++/test:yyy#something')->init();
        $this->assertSame('/ueper:xxx++/test:yyy', $this->uri->params());
        $this->assertSame('/ueper:xxx++', $this->uri->params('ueper'));
        $this->assertSame('/test:yyy', $this->uri->params('test'));
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper:xxx++/test:yyy?foo=bar')->init();
        $this->assertSame('/ueper:xxx++/test:yyy', $this->uri->params());
        $this->assertSame('/ueper:xxx++', $this->uri->params('ueper'));
        $this->assertSame('/test:yyy', $this->uri->params('test'));
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper?test=x')->init();
        $this->assertSame(null, $this->uri->params());
        $this->assertSame(null, $this->uri->params('ueper'));
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper?test=x&test2=y')->init();
        $this->assertSame(null, $this->uri->params());
        $this->assertSame(null, $this->uri->params('ueper'));
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper?test=x&test2=y&test3=x&test4=y')->init();
        $this->assertSame(null, $this->uri->params());
        $this->assertSame(null, $this->uri->params('ueper'));
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper?test=x&test2=y&test3=x&test4=y/test')->init();
        $this->assertSame(null, $this->uri->params());
        $this->assertSame(null, $this->uri->params('ueper'));
        $this->uri->initializeWithURL('http://localhost:8080/a/b/c/d')->init();
        $this->assertSame(null, $this->uri->params());
        $this->assertSame(null, $this->uri->params('ueper'));
        $this->uri->initializeWithURL('http://localhost:8080/a/b/c/d/e/f/a/b/c/d/e/f/a/b/c/d/e/f')->init();
        $this->assertSame(null, $this->uri->params());
        $this->assertSame(null, $this->uri->params('ueper'));
    }

    public function testParam()
    {
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper:xxx')->init();
        $this->assertSame('xxx', $this->uri->param('ueper'));
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper:xxx/test:yyy')->init();
        $this->assertSame('xxx', $this->uri->param('ueper'));
        $this->assertSame('yyy', $this->uri->param('test'));
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper:xxx++/test:yy%20y/foo:bar_baz-bank')->init();
        $this->assertSame('xxx++', $this->uri->param('ueper'));
        $this->assertSame('yy y', $this->uri->param('test'));
        $this->assertSame('bar_baz-bank', $this->uri->param('foo'));
    }

    public function testFragment()
    {
        $this->uri->initializeWithURL('http://localhost:8080/a/b/c#my-fragment');
        $this->assertSame('my-fragment', $this->uri->fragment());
        $this->uri->initializeWithURL('http://localhost:8080/a/b/c');
        $this->uri->fragment('something-new');
        $this->assertSame('something-new', $this->uri->fragment());
    }

    public function testUrl()
    {
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper')->init();
        $this->assertSame('/grav/it/ueper', $this->uri->url());
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper:xxx')->init();
        $this->assertSame('/grav/it', $this->uri->url());
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper:xxx/test:yyy')->init();
        $this->assertSame('/grav/it', $this->uri->url());
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper?test=x')->init();
        $this->assertSame('/grav/it/ueper', $this->uri->url());
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper?test=x&test2=y')->init();
        $this->assertSame('/grav/it/ueper', $this->uri->url());
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper?test=x&test2=y&test3=x&test4=y')->init();
        $this->assertSame('/grav/it/ueper', $this->uri->url());
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper?test=x&test2=y&test3=x&test4=y/test')->init();
        $this->assertSame('/grav/it/ueper', $this->uri->url());
        $this->uri->initializeWithURL('http://localhost:8080/a/b/c/d')->init();
        $this->assertSame('/a/b/c/d', $this->uri->url());
        $this->uri->initializeWithURL('http://localhost:8080/a/b/c/d/e/f/a/b/c/d/e/f/a/b/c/d/e/f')->init();
        $this->assertSame('/a/b/c/d/e/f/a/b/c/d/e/f/a/b/c/d/e/f', $this->uri->url());
    }

    public function testPath()
    {
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper')->init();
        $this->assertSame('/grav/it/ueper', $this->uri->path());
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper:xxx')->init();
        $this->assertSame('/grav/it', $this->uri->path());
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper:xxx/test:yyy')->init();
        $this->assertSame('/grav/it', $this->uri->path());
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper?test=x')->init();
        $this->assertSame('/grav/it/ueper', $this->uri->path());
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper?test=x&test2=y')->init();
        $this->assertSame('/grav/it/ueper', $this->uri->path());
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper?test=x&test2=y&test3=x&test4=y')->init();
        $this->assertSame('/grav/it/ueper', $this->uri->path());
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper?test=x&test2=y&test3=x&test4=y/test')->init();
        $this->assertSame('/grav/it/ueper', $this->uri->path());
        $this->uri->initializeWithURL('http://localhost:8080/a/b/c/d')->init();
        $this->assertSame('/a/b/c/d', $this->uri->path());
        $this->uri->initializeWithURL('http://localhost:8080/a/b/c/d/e/f/a/b/c/d/e/f/a/b/c/d/e/f')->init();
        $this->assertSame('/a/b/c/d/e/f/a/b/c/d/e/f/a/b/c/d/e/f', $this->uri->path());
        $this->uri->initializeWithURL('http://localhost/')->init();
        $this->assertSame('/', $this->uri->path());
    }

    public function testExtension()
    {
        $this->uri->initializeWithURL('http://localhost/a-page')->init();
        $this->assertSame(null, $this->uri->extension());
        $this->uri->initializeWithURL('http://localhost/a-page')->init();
        $this->assertSame('x', $this->uri->extension('x'));
        $this->uri->initializeWithURL('http://localhost/a-page.html')->init();
        $this->assertSame('html', $this->uri->extension());
        $this->uri->initializeWithURL('http://localhost/a-page.xml')->init();
        $this->assertSame('xml', $this->uri->extension());
        $this->uri->initializeWithURL('http://localhost/a-page.foo')->init();
        $this->assertSame('foo', $this->uri->extension());
    }

    public function testHost()
    {
        $address = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '::1';
        if ($this->uri->host() == 'localhost' || $address == '::1' || $address == '127.0.0.1') {
            $address = 'localhost';
        }

        $this->uri->initializeWithURL('http://localhost/a-page')->init();
        $this->assertSame($address, $this->uri->host());
        $this->uri->initializeWithURL('http://localhost/')->init();
        $this->assertSame($address, $this->uri->host());
    }

    public function testPort()
    {
        $this->uri->initializeWithURL('http://localhost/a-page')->init();
        $this->assertSame('80', $this->uri->port());
        $this->uri->initializeWithURL('http://localhost:8080/a-page')->init();
        $this->assertSame(8080, $this->uri->port());
        $this->uri->initializeWithURL('http://localhost:443/a-page')->init();
        $this->assertSame(443, $this->uri->port());
        $this->uri->initializeWithURL('https://localhost/a-page')->init();
        $this->assertSame('80', $this->uri->port());
    }

    public function testEnvironment()
    {
        $this->uri->initializeWithURL('http://localhost/a-page')->init();
        $this->assertSame('localhost', $this->uri->environment());
        $this->uri->initializeWithURL('http://127.0.0.1/a-page')->init();
        $this->assertSame('localhost', $this->uri->environment());
        $this->uri->initializeWithURL('http://localhost:8080/a-page')->init();
        $this->assertSame('localhost', $this->uri->environment());
        $this->uri->initializeWithURL('http://foobar.it:443/a-page')->init();
        $this->assertSame('foobar.it', $this->uri->environment());
        $this->uri->initializeWithURL('https://google.com/a-page')->init();
        $this->assertSame('google.com', $this->uri->environment());
    }

    public function testBasename()
    {
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper')->init();
        $this->assertSame('ueper', $this->uri->basename());
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper:xxx')->init();
        $this->assertSame('it', $this->uri->basename());
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper:xxx/test:yyy')->init();
        $this->assertSame('it', $this->uri->basename());
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper?test=x')->init();
        $this->assertSame('ueper', $this->uri->basename());
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper?test=x&test2=y')->init();
        $this->assertSame('ueper', $this->uri->basename());
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper?test=x&test2=y&test3=x&test4=y')->init();
        $this->assertSame('ueper', $this->uri->basename());
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper?test=x&test2=y&test3=x&test4=y/test')->init();
        $this->assertSame('ueper', $this->uri->basename());
        $this->uri->initializeWithURL('http://localhost:8080/a/b/c/d')->init();
        $this->assertSame('d', $this->uri->basename());
        $this->uri->initializeWithURL('http://localhost:8080/a/b/c/d/e/f/a/b/c/d/e/f/a/b/c/d/e/f')->init();
        $this->assertSame('f', $this->uri->basename());
        $this->uri->initializeWithURL('http://localhost/')->init();
        $this->assertSame('', $this->uri->basename());
        $this->uri->initializeWithURL('http://localhost/test.xml')->init();
        $this->assertSame('test.xml', $this->uri->basename());
    }

    public function testBase()
    {
        $this->uri->initializeWithURL('http://localhost/a-page')->init();
        $this->assertSame('http://localhost', $this->uri->base());
        $this->uri->initializeWithURL('http://localhost:8080/a-page')->init();
        $this->assertSame('http://localhost:8080', $this->uri->base());
        $this->uri->initializeWithURL('http://foobar.it:80/a-page')->init();
        $this->assertSame('http://foobar.it', $this->uri->base());
        $this->uri->initializeWithURL('https://google.com/a-page')->init();
        $this->assertSame('http://google.com', $this->uri->base());
    }

    public function testRootUrl()
    {
        //Without explicitly adding the root path via `initializeWithUrlAndRootPath`,
        //tests always default to the base empty root path
        $this->uri->initializeWithURL('http://localhost/a-page')->init();
        $this->assertSame('http://localhost', $this->uri->rootUrl(true));
        $this->uri->initializeWithURL('http://localhost:8080/a-page')->init();
        $this->assertSame('http://localhost:8080', $this->uri->rootUrl(true));
        $this->uri->initializeWithURL('http://foobar.it:80/a-page')->init();
        $this->assertSame('http://foobar.it', $this->uri->rootUrl(true));
        $this->uri->initializeWithURL('https://google.com/a-page/xxx')->init();
        $this->assertSame('http://google.com', $this->uri->rootUrl(true));

        $this->uri->initializeWithUrlAndRootPath('https://localhost/grav/page-foo', '/grav')->init();
        $this->assertSame('/grav', $this->uri->rootUrl());
        $this->assertSame('http://localhost/grav', $this->uri->rootUrl(true));
    }

    public function testCurrentPage()
    {
        $this->uri->initializeWithURL('http://localhost/foo/page:test')->init();
        $this->assertSame('test', $this->uri->currentPage());
        $this->uri->initializeWithURL('http://localhost:8080/a-page')->init();
        $this->assertSame(1, $this->uri->currentPage());
        $this->uri->initializeWithURL('http://localhost:8080/a-page/page:2')->init();
        $this->assertSame('2', $this->uri->currentPage());
        $this->uri->initializeWithURL('http://localhost:8080/a-page/page:x')->init();
        $this->assertSame('x', $this->uri->currentPage());
        $this->uri->initializeWithURL('http://localhost:8080/a-page/page:')->init();
        $this->assertSame('', $this->uri->currentPage());
    }

    public function testReferrer()
    {
        $this->uri->initializeWithURL('http://localhost/foo/page:test')->init();
        $this->assertSame('/foo', $this->uri->referrer());
        $this->uri->initializeWithURL('http://localhost/foo/bar/page:test')->init();
        $this->assertSame('/foo/bar', $this->uri->referrer());
    }

    public function testIp()
    {
        $this->uri->initializeWithURL('http://localhost/foo/page:test')->init();
        $this->assertSame('UNKNOWN', $this->uri->ip());
    }

    public function testIsExternal()
    {
        $this->uri->initializeWithURL('http://localhost/')->init();
        $this->assertFalse($this->uri->isExternal('/test'));
        $this->assertFalse($this->uri->isExternal('/foo/bar'));
        $this->assertTrue($this->uri->isExternal('http://localhost/test'));
        $this->assertTrue($this->uri->isExternal('http://google.it/test'));
    }

    public function testBuildUrl()
    {
        $parsed_url = [
            'scheme' => 'http',
            'host'   => 'localhost',
            'port'   => '8080',
        ];

        $this->assertSame('http://localhost:8080', Uri::buildUrl($parsed_url));

        $parsed_url = [
            'scheme'   => 'http',
            'host'     => 'localhost',
            'port'     => '8080',
            'user'     => 'foo',
            'pass'     => 'bar',
            'path'     => '/test',
            'query'    => 'x=2',
            'fragment' => 'xxx',
        ];

        $this->assertSame('http://foo:bar@localhost:8080/test?x=2#xxx', Uri::buildUrl($parsed_url));
    }

    public function testConvertUrl()
    {

    }

    public function testAddNonce()
    {
        $url = 'http://localhost/foo';
        $this->assertStringStartsWith($url, Uri::addNonce($url, 'test-action'));
        $this->assertStringStartsWith($url . '/nonce:', Uri::addNonce($url, 'test-action'));

        $this->uri->initializeWithURL(Uri::addNonce($url, 'test-action'))->init();
        $this->assertTrue(is_string($this->uri->param('nonce')));
        $this->assertSame(Utils::getNonce('test-action'), $this->uri->param('nonce'));
    }
}

