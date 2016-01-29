<?php

use Codeception\Util\Fixtures;
use Grav\Common\Uri;
use Grav\Common\Utils;

class UriTest extends \Codeception\TestCase\Test
{
    protected function _before()
    {
        $this->grav = Fixtures::get('grav');
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
        $this->assertSame($this->uri->paths(), ['grav', 'it', 'ueper']);
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper:xxx')->init();
        $this->assertSame($this->uri->paths(), ['grav', 'it']);
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper?test=x')->init();
        $this->assertSame($this->uri->paths(), ['grav', 'it', 'ueper']);
        $this->uri->initializeWithURL('http://localhost:8080/a/b/c/d')->init();
        $this->assertSame($this->uri->paths(), ['a', 'b', 'c', 'd']);
        $this->uri->initializeWithURL('http://localhost:8080/a/b/c/d/e/f/a/b/c/d/e/f/a/b/c/d/e/f')->init();
        $this->assertSame($this->uri->paths(), ['a', 'b', 'c', 'd', 'e', 'f', 'a', 'b', 'c', 'd', 'e', 'f', 'a', 'b', 'c', 'd', 'e', 'f']);
    }

    public function testRoute()
    {
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper')->init();
        $this->assertSame($this->uri->route(), '/grav/it/ueper');
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper:xxx')->init();
        $this->assertSame($this->uri->route(), '/grav/it');
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper?test=x')->init();
        $this->assertSame($this->uri->route(), '/grav/it/ueper');
        $this->uri->initializeWithURL('http://localhost:8080/a/b/c/d')->init();
        $this->assertSame($this->uri->route(), '/a/b/c/d');
        $this->uri->initializeWithURL('http://localhost:8080/a/b/c/d/e/f/a/b/c/d/e/f/a/b/c/d/e/f')->init();
        $this->assertSame($this->uri->route(), '/a/b/c/d/e/f/a/b/c/d/e/f/a/b/c/d/e/f');
    }

    public function testQuery()
    {
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper')->init();
        $this->assertSame($this->uri->query(), '');
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper:xxx')->init();
        $this->assertSame($this->uri->query(), '');
        $this->assertSame($this->uri->query('id'), null);
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper?test=x')->init();
        $this->assertSame($this->uri->query(), 'test=x');
        $this->assertSame($this->uri->query('id'), null);
        $this->assertSame($this->uri->query('test'), 'x');
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper?test=x&test2=y')->init();
        $this->assertSame($this->uri->query(), 'test=x&test2=y');
        $this->assertSame($this->uri->query('id'), null);
        $this->assertSame($this->uri->query('test'), 'x');
        $this->assertSame($this->uri->query('test2'), 'y');
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper?test=x&test2=y&test3=x&test4=y')->init();
        $this->assertSame($this->uri->query(), 'test=x&test2=y&test3=x&test4=y');
        $this->assertSame($this->uri->query('id'), null);
        $this->assertSame($this->uri->query('test'), 'x');
        $this->assertSame($this->uri->query('test2'), 'y');
        $this->assertSame($this->uri->query('test4'), 'y');
        //Test all after the ? is encoded in the query
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper?test=x&test2=y&test3=x&test4=y/test')->init();
        $this->assertSame($this->uri->query(), 'test=x&test2=y&test3=x&test4=y%2Ftest');
        $this->assertSame($this->uri->query('id'), null);
        $this->assertSame($this->uri->query('test'), 'x');
        $this->assertSame($this->uri->query('test2'), 'y');
        $this->assertSame($this->uri->query('test4'), 'y/test');
        $this->uri->initializeWithURL('http://localhost:8080/a/b/c/d')->init();
        $this->assertSame($this->uri->query(), '');
        $this->assertSame($this->uri->query('id'), null);
        $this->uri->initializeWithURL('http://localhost:8080/a/b/c/d/e/f/a/b/c/d/e/f/a/b/c/d/e/f')->init();
        $this->assertSame($this->uri->query(), '');
        $this->assertSame($this->uri->query('id'), null);
    }

    public function testParams()
    {
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper')->init();
        $this->assertSame($this->uri->params(), null);
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper:xxx')->init();
        $this->assertSame($this->uri->params(), '/ueper:xxx');
        $this->assertSame($this->uri->params('ueper'), '/ueper:xxx');
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper:xxx/test:yyy')->init();
        $this->assertSame($this->uri->params(), '/ueper:xxx/test:yyy');
        $this->assertSame($this->uri->params('ueper'), '/ueper:xxx');
        $this->assertSame($this->uri->params('test'), '/test:yyy');
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper?test=x')->init();
        $this->assertSame($this->uri->params(), null);
        $this->assertSame($this->uri->params('ueper'), null);
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper?test=x&test2=y')->init();
        $this->assertSame($this->uri->params(), null);
        $this->assertSame($this->uri->params('ueper'), null);
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper?test=x&test2=y&test3=x&test4=y')->init();
        $this->assertSame($this->uri->params(), null);
        $this->assertSame($this->uri->params('ueper'), null);
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper?test=x&test2=y&test3=x&test4=y/test')->init();
        $this->assertSame($this->uri->params(), null);
        $this->assertSame($this->uri->params('ueper'), null);
        $this->uri->initializeWithURL('http://localhost:8080/a/b/c/d')->init();
        $this->assertSame($this->uri->params(), null);
        $this->assertSame($this->uri->params('ueper'), null);
        $this->uri->initializeWithURL('http://localhost:8080/a/b/c/d/e/f/a/b/c/d/e/f/a/b/c/d/e/f')->init();
        $this->assertSame($this->uri->params(), null);
        $this->assertSame($this->uri->params('ueper'), null);
    }

    public function testParam()
    {
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper:xxx')->init();
        $this->assertSame($this->uri->param('ueper'), 'xxx');
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper:xxx/test:yyy')->init();
        $this->assertSame($this->uri->param('ueper'), 'xxx');
        $this->assertSame($this->uri->param('test'), 'yyy');
    }

    public function testUrl()
    {
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper')->init();
        $this->assertSame($this->uri->url(), '/grav/it/ueper');
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper:xxx')->init();
        $this->assertSame($this->uri->url(), '/grav/it');
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper:xxx/test:yyy')->init();
        $this->assertSame($this->uri->url(), '/grav/it');
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper?test=x')->init();
        $this->assertSame($this->uri->url(), '/grav/it/ueper');
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper?test=x&test2=y')->init();
        $this->assertSame($this->uri->url(), '/grav/it/ueper');
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper?test=x&test2=y&test3=x&test4=y')->init();
        $this->assertSame($this->uri->url(), '/grav/it/ueper');
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper?test=x&test2=y&test3=x&test4=y/test')->init();
        $this->assertSame($this->uri->url(), '/grav/it/ueper');
        $this->uri->initializeWithURL('http://localhost:8080/a/b/c/d')->init();
        $this->assertSame($this->uri->url(), '/a/b/c/d');
        $this->uri->initializeWithURL('http://localhost:8080/a/b/c/d/e/f/a/b/c/d/e/f/a/b/c/d/e/f')->init();
        $this->assertSame($this->uri->url(), '/a/b/c/d/e/f/a/b/c/d/e/f/a/b/c/d/e/f');
    }

    public function testPath()
    {
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper')->init();
        $this->assertSame($this->uri->path(), '/grav/it/ueper');
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper:xxx')->init();
        $this->assertSame($this->uri->path(), '/grav/it');
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper:xxx/test:yyy')->init();
        $this->assertSame($this->uri->path(), '/grav/it');
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper?test=x')->init();
        $this->assertSame($this->uri->path(), '/grav/it/ueper');
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper?test=x&test2=y')->init();
        $this->assertSame($this->uri->path(), '/grav/it/ueper');
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper?test=x&test2=y&test3=x&test4=y')->init();
        $this->assertSame($this->uri->path(), '/grav/it/ueper');
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper?test=x&test2=y&test3=x&test4=y/test')->init();
        $this->assertSame($this->uri->path(), '/grav/it/ueper');
        $this->uri->initializeWithURL('http://localhost:8080/a/b/c/d')->init();
        $this->assertSame($this->uri->path(), '/a/b/c/d');
        $this->uri->initializeWithURL('http://localhost:8080/a/b/c/d/e/f/a/b/c/d/e/f/a/b/c/d/e/f')->init();
        $this->assertSame($this->uri->path(), '/a/b/c/d/e/f/a/b/c/d/e/f/a/b/c/d/e/f');
        $this->uri->initializeWithURL('http://localhost/')->init();
        $this->assertSame($this->uri->path(), '/');
    }

    public function testExtension()
    {
        $this->uri->initializeWithURL('http://localhost/a-page')->init();
        $this->assertSame($this->uri->extension(), null);
        $this->uri->initializeWithURL('http://localhost/a-page')->init();
        $this->assertSame($this->uri->extension('x'), 'x');
        $this->uri->initializeWithURL('http://localhost/a-page.html')->init();
        $this->assertSame($this->uri->extension(), 'html');
        $this->uri->initializeWithURL('http://localhost/a-page.xml')->init();
        $this->assertSame($this->uri->extension(), 'xml');
        $this->uri->initializeWithURL('http://localhost/a-page.foo')->init();
        $this->assertSame($this->uri->extension(), 'foo');
    }

    public function testHost()
    {
        $address = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '::1';
        if ($this->uri->host() == 'localhost' || $address == '::1' || $address == '127.0.0.1') {
            $address = 'localhost';
        }

        $this->uri->initializeWithURL('http://localhost/a-page')->init();
        $this->assertSame($this->uri->host(), $address);
        $this->uri->initializeWithURL('http://localhost/')->init();
        $this->assertSame($this->uri->host(), $address);
        //Host is set to localhost when running from local
        $this->uri->initializeWithURL('http://google.com/')->init();
        $this->assertSame($this->uri->host(), $address);
    }

    public function testPort()
    {
        $this->uri->initializeWithURL('http://localhost/a-page')->init();
        $this->assertSame($this->uri->port(), '80');
        $this->uri->initializeWithURL('http://localhost:8080/a-page')->init();
        $this->assertSame($this->uri->port(), 8080);
        $this->uri->initializeWithURL('http://localhost:443/a-page')->init();
        $this->assertSame($this->uri->port(), 443);
        $this->uri->initializeWithURL('https://localhost/a-page')->init();
        $this->assertSame($this->uri->port(), '80');
    }

    public function testEnvironment()
    {
        $address = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '::1';
        if ($this->uri->host() == 'localhost' || $address == '::1' || $address == '127.0.0.1') {
            $address = 'localhost';
        }

        $this->uri->initializeWithURL('http://localhost/a-page')->init();
        $this->assertSame($this->uri->environment(), $address);
        $this->uri->initializeWithURL('http://localhost:8080/a-page')->init();
        $this->assertSame($this->uri->environment(), $address);
        $this->uri->initializeWithURL('http://foobar.it:443/a-page')->init();
        $this->assertSame($this->uri->environment(), $address);
        $this->uri->initializeWithURL('https://google.com/a-page')->init();
        $this->assertSame($this->uri->environment(), $address);
    }

    public function testBasename()
    {
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper')->init();
        $this->assertSame($this->uri->basename(), 'ueper');
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper:xxx')->init();
        $this->assertSame($this->uri->basename(), 'it');
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper:xxx/test:yyy')->init();
        $this->assertSame($this->uri->basename(), 'it');
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper?test=x')->init();
        $this->assertSame($this->uri->basename(), 'ueper');
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper?test=x&test2=y')->init();
        $this->assertSame($this->uri->basename(), 'ueper');
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper?test=x&test2=y&test3=x&test4=y')->init();
        $this->assertSame($this->uri->basename(), 'ueper');
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper?test=x&test2=y&test3=x&test4=y/test')->init();
        $this->assertSame($this->uri->basename(), 'ueper');
        $this->uri->initializeWithURL('http://localhost:8080/a/b/c/d')->init();
        $this->assertSame($this->uri->basename(), 'd');
        $this->uri->initializeWithURL('http://localhost:8080/a/b/c/d/e/f/a/b/c/d/e/f/a/b/c/d/e/f')->init();
        $this->assertSame($this->uri->basename(), 'f');
        $this->uri->initializeWithURL('http://localhost/')->init();
        $this->assertSame($this->uri->basename(), '');
        $this->uri->initializeWithURL('http://localhost/test.xml')->init();
        $this->assertSame($this->uri->basename(), 'test.xml');
    }

    public function testBase()
    {
        $this->uri->initializeWithURL('http://localhost/a-page')->init();
        $this->assertSame($this->uri->base(), 'http://localhost');
        $this->uri->initializeWithURL('http://localhost:8080/a-page')->init();
        $this->assertSame($this->uri->base(), 'http://localhost:8080');
        $this->uri->initializeWithURL('http://foobar.it:80/a-page')->init();
        $this->assertSame($this->uri->base(), 'http://foobar.it');
        $this->uri->initializeWithURL('https://google.com/a-page')->init();
        $this->assertSame($this->uri->base(), 'http://google.com');
    }

    public function testRootUrl()
    {
        //Without explicitly adding the root path via `initializeWithUrlAndRootPath`,
        //tests always default to the base empty root path
        $this->uri->initializeWithURL('http://localhost/a-page')->init();
        $this->assertSame($this->uri->rootUrl(true), 'http://localhost');
        $this->uri->initializeWithURL('http://localhost:8080/a-page')->init();
        $this->assertSame($this->uri->rootUrl(true), 'http://localhost:8080');
        $this->uri->initializeWithURL('http://foobar.it:80/a-page')->init();
        $this->assertSame($this->uri->rootUrl(true), 'http://foobar.it');
        $this->uri->initializeWithURL('https://google.com/a-page/xxx')->init();
        $this->assertSame($this->uri->rootUrl(true), 'http://google.com');

        $this->uri->initializeWithUrlAndRootPath('https://localhost/grav/page-foo', '/grav')->init();
        $this->assertSame($this->uri->rootUrl(), '/grav');
        $this->assertSame($this->uri->rootUrl(true), 'http://localhost/grav');
    }

    public function testCurrentPage()
    {
        $this->uri->initializeWithURL('http://localhost/foo/page:test')->init();
        $this->assertSame($this->uri->currentPage(), 'test');
        $this->uri->initializeWithURL('http://localhost:8080/a-page')->init();
        $this->assertSame($this->uri->currentPage(), 1);
        $this->uri->initializeWithURL('http://localhost:8080/a-page/page:2')->init();
        $this->assertSame($this->uri->currentPage(), '2');
        $this->uri->initializeWithURL('http://localhost:8080/a-page/page:x')->init();
        $this->assertSame($this->uri->currentPage(), 'x');
        $this->uri->initializeWithURL('http://localhost:8080/a-page/page:')->init();
        $this->assertSame($this->uri->currentPage(), '');
    }

    public function testReferrer()
    {
        $this->uri->initializeWithURL('http://localhost/foo/page:test')->init();
        $this->assertSame($this->uri->referrer(), '/foo');
        $this->uri->initializeWithURL('http://localhost/foo/bar/page:test')->init();
        $this->assertSame($this->uri->referrer(), '/foo/bar');
    }

    public function testIp()
    {
        $this->uri->initializeWithURL('http://localhost/foo/page:test')->init();
        $this->assertSame($this->uri->ip(), 'UNKNOWN');
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
            'host' => 'localhost',
            'port' => '8080',
        ];

        $this->assertSame(Uri::buildUrl($parsed_url), 'http://localhost:8080');

        $parsed_url = [
            'scheme' => 'http',
            'host' => 'localhost',
            'port' => '8080',
            'user' => 'foo',
            'pass' => 'bar',
            'path' => '/test',
            'query' => 'x=2',
            'fragment' => 'xxx',
        ];

        $this->assertSame(Uri::buildUrl($parsed_url), 'http://foo:bar@localhost:8080/test?x=2#xxx');
    }

    public function testConvertUrl()
    {
        //TODO when we have a fixed testing page structure
    }

    public function testAddNonce()
    {
        $url = 'http://localhost/foo';
        $this->assertStringStartsWith($url, Uri::addNonce($url, 'test-action'));
        $this->assertStringStartsWith($url . '/nonce:', Uri::addNonce($url, 'test-action'));

        $this->uri->initializeWithURL(Uri::addNonce($url, 'test-action'))->init();
        $this->assertTrue(is_string($this->uri->param('nonce')));
        $this->assertSame($this->uri->param('nonce'), Utils::getNonce('test-action'));
    }
}

