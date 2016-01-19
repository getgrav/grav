<?php

use Codeception\Util\Fixtures;
use Grav\Common\Uri;
use Grav\Common\Utils;

class UriTest extends \Codeception\TestCase\Test
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    protected function _before()
    {
        $uri = $this->getURI();
    }

    protected function _after()
    {
    }

    public function grav()
    {
        $grav = Fixtures::get('grav');
        return $grav;
    }

    public function getURI()
    {
        return $this->grav()['uri'];
    }

    public function testValidatingHostname()
    {
        $uri = $this->getURI();

        $this->assertTrue($uri->validateHostname('localhost') == 1);
        $this->assertTrue($uri->validateHostname('google.com') == 1);
        $this->assertTrue($uri->validateHostname('google.it') == 1);
        $this->assertTrue($uri->validateHostname('goog.le') == 1);
        $this->assertTrue($uri->validateHostname('goog.wine') == 1);
        $this->assertTrue($uri->validateHostname('goog.localhost') == 1);

        $this->assertFalse($uri->validateHostname('localhost:80') == 1);
        $this->assertFalse($uri->validateHostname('http://localhost') == 1);
        $this->assertFalse($uri->validateHostname('localhost!') == 1);
    }

    public function testInitializingUris()
    {
        $uri = $this->getURI();

        $uri->initializeWithURL('http://localhost:8080/grav/it/ueper')->init();
        $this->assertTrue($uri->params() == null);
        $this->assertTrue($uri->query() == '');

        $uri->initializeWithURL('http://localhost:8080/grav/it/ueper:xxx')->init();
        $this->assertFalse($uri->params() == null);
        $this->assertTrue($uri->query() == '');

        $uri->initializeWithURL('http://localhost:8080/grav/it/ueper?test=x')->init();

        $this->assertTrue($uri->params() == null);
        $this->assertTrue($uri->query() != '');
        $this->assertTrue($uri->query() == 'test=x');
        $this->assertTrue($uri->port() == '8080');

        $uri->initializeWithURL('http://localhost:80/grav/it/ueper?test=x')->init();
        $this->assertTrue($uri->port() == '80');

        $uri->initializeWithURL('http://localhost/grav/it/ueper?test=x')->init();
        $this->assertTrue($uri->port() == '80');

        $uri->initializeWithURL('http://localhost:8080/grav/it/ueper')->init();
        $this->assertTrue($uri->params() == null);

        $uri->initializeWithURL('http://grav/grav/it/ueper')->init();
        $this->assertTrue($uri->params() == null);
    }

    public function testPaths()
    {
        $uri = $this->getURI();

        $uri->initializeWithURL('http://localhost:8080/grav/it/ueper')->init();
        $this->assertSame($uri->paths(), ['grav', 'it', 'ueper']);
        $uri->initializeWithURL('http://localhost:8080/grav/it/ueper:xxx')->init();
        $this->assertSame($uri->paths(), ['grav', 'it']);
        $uri->initializeWithURL('http://localhost:8080/grav/it/ueper?test=x')->init();
        $this->assertSame($uri->paths(), ['grav', 'it', 'ueper']);
        $uri->initializeWithURL('http://localhost:8080/a/b/c/d')->init();
        $this->assertSame($uri->paths(), ['a', 'b', 'c', 'd']);
        $uri->initializeWithURL('http://localhost:8080/a/b/c/d/e/f/a/b/c/d/e/f/a/b/c/d/e/f')->init();
        $this->assertSame($uri->paths(), ['a', 'b', 'c', 'd', 'e', 'f', 'a', 'b', 'c', 'd', 'e', 'f', 'a', 'b', 'c', 'd', 'e', 'f']);
    }

    public function testRoute()
    {
        $uri = $this->getURI();

        $uri->initializeWithURL('http://localhost:8080/grav/it/ueper')->init();
        $this->assertSame($uri->route(), '/grav/it/ueper');
        $uri->initializeWithURL('http://localhost:8080/grav/it/ueper:xxx')->init();
        $this->assertSame($uri->route(), '/grav/it');
        $uri->initializeWithURL('http://localhost:8080/grav/it/ueper?test=x')->init();
        $this->assertSame($uri->route(), '/grav/it/ueper');
        $uri->initializeWithURL('http://localhost:8080/a/b/c/d')->init();
        $this->assertSame($uri->route(), '/a/b/c/d');
        $uri->initializeWithURL('http://localhost:8080/a/b/c/d/e/f/a/b/c/d/e/f/a/b/c/d/e/f')->init();
        $this->assertSame($uri->route(), '/a/b/c/d/e/f/a/b/c/d/e/f/a/b/c/d/e/f');
    }

    public function testQuery()
    {
        $uri = $this->getURI();

        $uri->initializeWithURL('http://localhost:8080/grav/it/ueper')->init();
        $this->assertSame($uri->query(), '');
        $uri->initializeWithURL('http://localhost:8080/grav/it/ueper:xxx')->init();
        $this->assertSame($uri->query(), '');
        $this->assertSame($uri->query('id'), null);
        $uri->initializeWithURL('http://localhost:8080/grav/it/ueper?test=x')->init();
        $this->assertSame($uri->query(), 'test=x');
        $this->assertSame($uri->query('id'), null);
        $this->assertSame($uri->query('test'), 'x');
        $uri->initializeWithURL('http://localhost:8080/grav/it/ueper?test=x&test2=y')->init();
        $this->assertSame($uri->query(), 'test=x&test2=y');
        $this->assertSame($uri->query('id'), null);
        $this->assertSame($uri->query('test'), 'x');
        $this->assertSame($uri->query('test2'), 'y');
        $uri->initializeWithURL('http://localhost:8080/grav/it/ueper?test=x&test2=y&test3=x&test4=y')->init();
        $this->assertSame($uri->query(), 'test=x&test2=y&test3=x&test4=y');
        $this->assertSame($uri->query('id'), null);
        $this->assertSame($uri->query('test'), 'x');
        $this->assertSame($uri->query('test2'), 'y');
        $this->assertSame($uri->query('test4'), 'y');
        //Test all after the ? is encoded in the query
        $uri->initializeWithURL('http://localhost:8080/grav/it/ueper?test=x&test2=y&test3=x&test4=y/test')->init();
        $this->assertSame($uri->query(), 'test=x&test2=y&test3=x&test4=y%2Ftest');
        $this->assertSame($uri->query('id'), null);
        $this->assertSame($uri->query('test'), 'x');
        $this->assertSame($uri->query('test2'), 'y');
        $this->assertSame($uri->query('test4'), 'y/test');
        $uri->initializeWithURL('http://localhost:8080/a/b/c/d')->init();
        $this->assertSame($uri->query(), '');
        $this->assertSame($uri->query('id'), null);
        $uri->initializeWithURL('http://localhost:8080/a/b/c/d/e/f/a/b/c/d/e/f/a/b/c/d/e/f')->init();
        $this->assertSame($uri->query(), '');
        $this->assertSame($uri->query('id'), null);
    }

    public function testParams()
    {
        $uri = $this->getURI();

        $uri->initializeWithURL('http://localhost:8080/grav/it/ueper')->init();
        $this->assertSame($uri->params(), null);
        $uri->initializeWithURL('http://localhost:8080/grav/it/ueper:xxx')->init();
        $this->assertSame($uri->params(), '/ueper:xxx');
        $this->assertSame($uri->params('ueper'), '/ueper:xxx');
        $uri->initializeWithURL('http://localhost:8080/grav/it/ueper:xxx/test:yyy')->init();
        $this->assertSame($uri->params(), '/ueper:xxx/test:yyy');
        $this->assertSame($uri->params('ueper'), '/ueper:xxx');
        $this->assertSame($uri->params('test'), '/test:yyy');
        $uri->initializeWithURL('http://localhost:8080/grav/it/ueper?test=x')->init();
        $this->assertSame($uri->params(), null);
        $this->assertSame($uri->params('ueper'), null);
        $uri->initializeWithURL('http://localhost:8080/grav/it/ueper?test=x&test2=y')->init();
        $this->assertSame($uri->params(), null);
        $this->assertSame($uri->params('ueper'), null);
        $uri->initializeWithURL('http://localhost:8080/grav/it/ueper?test=x&test2=y&test3=x&test4=y')->init();
        $this->assertSame($uri->params(), null);
        $this->assertSame($uri->params('ueper'), null);
        $uri->initializeWithURL('http://localhost:8080/grav/it/ueper?test=x&test2=y&test3=x&test4=y/test')->init();
        $this->assertSame($uri->params(), null);
        $this->assertSame($uri->params('ueper'), null);
        $uri->initializeWithURL('http://localhost:8080/a/b/c/d')->init();
        $this->assertSame($uri->params(), null);
        $this->assertSame($uri->params('ueper'), null);
        $uri->initializeWithURL('http://localhost:8080/a/b/c/d/e/f/a/b/c/d/e/f/a/b/c/d/e/f')->init();
        $this->assertSame($uri->params(), null);
        $this->assertSame($uri->params('ueper'), null);
    }

    public function testParam()
    {
        $uri = $this->getURI();

        $uri->initializeWithURL('http://localhost:8080/grav/it/ueper:xxx')->init();
        $this->assertSame($uri->param('ueper'), 'xxx');
        $uri->initializeWithURL('http://localhost:8080/grav/it/ueper:xxx/test:yyy')->init();
        $this->assertSame($uri->param('ueper'), 'xxx');
        $this->assertSame($uri->param('test'), 'yyy');
    }

    public function testUrl()
    {
        $uri = $this->getURI();
        $uri->initializeWithURL('http://localhost:8080/grav/it/ueper')->init();
        $this->assertSame($uri->url(), '/grav/it/ueper');
        $uri->initializeWithURL('http://localhost:8080/grav/it/ueper:xxx')->init();
        $this->assertSame($uri->url(), '/grav/it');
        $uri->initializeWithURL('http://localhost:8080/grav/it/ueper:xxx/test:yyy')->init();
        $this->assertSame($uri->url(), '/grav/it');
        $uri->initializeWithURL('http://localhost:8080/grav/it/ueper?test=x')->init();
        $this->assertSame($uri->url(), '/grav/it/ueper');
        $uri->initializeWithURL('http://localhost:8080/grav/it/ueper?test=x&test2=y')->init();
        $this->assertSame($uri->url(), '/grav/it/ueper');
        $uri->initializeWithURL('http://localhost:8080/grav/it/ueper?test=x&test2=y&test3=x&test4=y')->init();
        $this->assertSame($uri->url(), '/grav/it/ueper');
        $uri->initializeWithURL('http://localhost:8080/grav/it/ueper?test=x&test2=y&test3=x&test4=y/test')->init();
        $this->assertSame($uri->url(), '/grav/it/ueper');
        $uri->initializeWithURL('http://localhost:8080/a/b/c/d')->init();
        $this->assertSame($uri->url(), '/a/b/c/d');
        $uri->initializeWithURL('http://localhost:8080/a/b/c/d/e/f/a/b/c/d/e/f/a/b/c/d/e/f')->init();
        $this->assertSame($uri->url(), '/a/b/c/d/e/f/a/b/c/d/e/f/a/b/c/d/e/f');
    }

    public function testPath()
    {
        $uri = $this->getURI();
        $uri->initializeWithURL('http://localhost:8080/grav/it/ueper')->init();
        $this->assertSame($uri->path(), '/grav/it/ueper');
        $uri->initializeWithURL('http://localhost:8080/grav/it/ueper:xxx')->init();
        $this->assertSame($uri->path(), '/grav/it');
        $uri->initializeWithURL('http://localhost:8080/grav/it/ueper:xxx/test:yyy')->init();
        $this->assertSame($uri->path(), '/grav/it');
        $uri->initializeWithURL('http://localhost:8080/grav/it/ueper?test=x')->init();
        $this->assertSame($uri->path(), '/grav/it/ueper');
        $uri->initializeWithURL('http://localhost:8080/grav/it/ueper?test=x&test2=y')->init();
        $this->assertSame($uri->path(), '/grav/it/ueper');
        $uri->initializeWithURL('http://localhost:8080/grav/it/ueper?test=x&test2=y&test3=x&test4=y')->init();
        $this->assertSame($uri->path(), '/grav/it/ueper');
        $uri->initializeWithURL('http://localhost:8080/grav/it/ueper?test=x&test2=y&test3=x&test4=y/test')->init();
        $this->assertSame($uri->path(), '/grav/it/ueper');
        $uri->initializeWithURL('http://localhost:8080/a/b/c/d')->init();
        $this->assertSame($uri->path(), '/a/b/c/d');
        $uri->initializeWithURL('http://localhost:8080/a/b/c/d/e/f/a/b/c/d/e/f/a/b/c/d/e/f')->init();
        $this->assertSame($uri->path(), '/a/b/c/d/e/f/a/b/c/d/e/f/a/b/c/d/e/f');
        $uri->initializeWithURL('http://localhost/')->init();
        $this->assertSame($uri->path(), '/');
    }

    public function testExtension()
    {
        $uri = $this->getURI();
        $uri->initializeWithURL('http://localhost/a-page')->init();
        $this->assertSame($uri->extension(), null);
        $uri->initializeWithURL('http://localhost/a-page')->init();
        $this->assertSame($uri->extension('x'), 'x');
        $uri->initializeWithURL('http://localhost/a-page.html')->init();
        $this->assertSame($uri->extension(), 'html');
        $uri->initializeWithURL('http://localhost/a-page.xml')->init();
        $this->assertSame($uri->extension(), 'xml');
        $uri->initializeWithURL('http://localhost/a-page.foo')->init();
        $this->assertSame($uri->extension(), 'foo');
    }

    public function testHost()
    {
        $uri = $this->getURI();

        $address = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '::1';
        if ($uri->host() == 'localhost' || $address == '::1' || $address == '127.0.0.1') {
            $address = 'localhost';
        }

        $uri->initializeWithURL('http://localhost/a-page')->init();
        $this->assertSame($uri->host(), $address);
        $uri->initializeWithURL('http://localhost/')->init();
        $this->assertSame($uri->host(), $address);
        //Host is set to localhost when running from local
        $uri->initializeWithURL('http://google.com/')->init();
        $this->assertSame($uri->host(), $address);
    }

    public function testPort()
    {
        $uri = $this->getURI();
        $uri->initializeWithURL('http://localhost/a-page')->init();
        $this->assertSame($uri->port(), '80');
        $uri->initializeWithURL('http://localhost:8080/a-page')->init();
        $this->assertSame($uri->port(), 8080);
        $uri->initializeWithURL('http://localhost:443/a-page')->init();
        $this->assertSame($uri->port(), 443);
        $uri->initializeWithURL('https://localhost/a-page')->init();
        $this->assertSame($uri->port(), '80');
    }

    public function testEnvironment()
    {
        $uri = $this->getURI();

        $address = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '::1';
        if ($uri->host() == 'localhost' || $address == '::1' || $address == '127.0.0.1') {
            $address = 'localhost';
        }

        $uri->initializeWithURL('http://localhost/a-page')->init();
        $this->assertSame($uri->environment(), $address);
        $uri->initializeWithURL('http://localhost:8080/a-page')->init();
        $this->assertSame($uri->environment(), $address);
        $uri->initializeWithURL('http://foobar.it:443/a-page')->init();
        $this->assertSame($uri->environment(), $address);
        $uri->initializeWithURL('https://google.com/a-page')->init();
        $this->assertSame($uri->environment(), $address);
    }

    public function testBasename()
    {
        $uri = $this->getURI();

        $uri->initializeWithURL('http://localhost:8080/grav/it/ueper')->init();
        $this->assertSame($uri->basename(), 'ueper');
        $uri->initializeWithURL('http://localhost:8080/grav/it/ueper:xxx')->init();
        $this->assertSame($uri->basename(), 'it');
        $uri->initializeWithURL('http://localhost:8080/grav/it/ueper:xxx/test:yyy')->init();
        $this->assertSame($uri->basename(), 'it');
        $uri->initializeWithURL('http://localhost:8080/grav/it/ueper?test=x')->init();
        $this->assertSame($uri->basename(), 'ueper');
        $uri->initializeWithURL('http://localhost:8080/grav/it/ueper?test=x&test2=y')->init();
        $this->assertSame($uri->basename(), 'ueper');
        $uri->initializeWithURL('http://localhost:8080/grav/it/ueper?test=x&test2=y&test3=x&test4=y')->init();
        $this->assertSame($uri->basename(), 'ueper');
        $uri->initializeWithURL('http://localhost:8080/grav/it/ueper?test=x&test2=y&test3=x&test4=y/test')->init();
        $this->assertSame($uri->basename(), 'ueper');
        $uri->initializeWithURL('http://localhost:8080/a/b/c/d')->init();
        $this->assertSame($uri->basename(), 'd');
        $uri->initializeWithURL('http://localhost:8080/a/b/c/d/e/f/a/b/c/d/e/f/a/b/c/d/e/f')->init();
        $this->assertSame($uri->basename(), 'f');
        $uri->initializeWithURL('http://localhost/')->init();
        $this->assertSame($uri->basename(), '');
        $uri->initializeWithURL('http://localhost/test.xml')->init();
        $this->assertSame($uri->basename(), 'test.xml');
    }

    public function testBase()
    {
        $uri = $this->getURI();

        $uri->initializeWithURL('http://localhost/a-page')->init();
        $this->assertSame($uri->base(), 'http://localhost');
        $uri->initializeWithURL('http://localhost:8080/a-page')->init();
        $this->assertSame($uri->base(), 'http://localhost:8080');
        $uri->initializeWithURL('http://foobar.it:80/a-page')->init();
        $this->assertSame($uri->base(), 'http://foobar.it');
        $uri->initializeWithURL('https://google.com/a-page')->init();
        $this->assertSame($uri->base(), 'http://google.com');
    }

    public function testRootUrl()
    {
        $uri = $this->getURI();

        //Without explicitly adding the root path via `initializeWithUrlAndRootPath`,
        //tests always default to the base empty root path
        $uri->initializeWithURL('http://localhost/a-page')->init();
        $this->assertSame($uri->rootUrl(true), 'http://localhost');
        $uri->initializeWithURL('http://localhost:8080/a-page')->init();
        $this->assertSame($uri->rootUrl(true), 'http://localhost:8080');
        $uri->initializeWithURL('http://foobar.it:80/a-page')->init();
        $this->assertSame($uri->rootUrl(true), 'http://foobar.it');
        $uri->initializeWithURL('https://google.com/a-page/xxx')->init();
        $this->assertSame($uri->rootUrl(true), 'http://google.com');

        $uri->initializeWithUrlAndRootPath('https://localhost/grav/page-foo', '/grav')->init();
        $this->assertSame($uri->rootUrl(), '/grav');
        $this->assertSame($uri->rootUrl(true), 'http://localhost/grav');
    }

    public function testCurrentPage()
    {
        $uri = $this->getURI();

        $uri->initializeWithURL('http://localhost/foo/page:test')->init();
        $this->assertSame($uri->currentPage(), 'test');
        $uri->initializeWithURL('http://localhost:8080/a-page')->init();
        $this->assertSame($uri->currentPage(), 1);
        $uri->initializeWithURL('http://localhost:8080/a-page/page:2')->init();
        $this->assertSame($uri->currentPage(), '2');
        $uri->initializeWithURL('http://localhost:8080/a-page/page:x')->init();
        $this->assertSame($uri->currentPage(), 'x');
        $uri->initializeWithURL('http://localhost:8080/a-page/page:')->init();
        $this->assertSame($uri->currentPage(), '');
    }

    public function testReferrer()
    {
        $uri = $this->getURI();

        $uri->initializeWithURL('http://localhost/foo/page:test')->init();
        $this->assertSame($uri->referrer(), '/foo');
        $uri->initializeWithURL('http://localhost/foo/bar/page:test')->init();
        $this->assertSame($uri->referrer(), '/foo/bar');
    }

    public function testIp()
    {
        $uri = $this->getURI();

        $uri->initializeWithURL('http://localhost/foo/page:test')->init();
        $this->assertSame($uri->ip(), 'UNKNOWN');
    }

    public function testIsExternal()
    {
        $uri = $this->getURI();

        $uri->initializeWithURL('http://localhost/')->init();
        $this->assertFalse($uri->isExternal('/test'));
        $this->assertFalse($uri->isExternal('/foo/bar'));
        $this->assertTrue($uri->isExternal('http://localhost/test'));
        $this->assertTrue($uri->isExternal('http://google.it/test'));
    }

    public function testBuildUrl()
    {
        $parsed_url = [
            'scheme' => 'http',
            'host' => 'localhost',
            'port' => '8080',
        ];

        $this->assertSame($this->grav()['uri']::buildUrl($parsed_url), 'http://localhost:8080');

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

        $this->assertSame($this->grav()['uri']::buildUrl($parsed_url), 'http://foo:bar@localhost:8080/test?x=2#xxx');
    }

    public function testConvertUrl()
    {
        //TODO when we have a fixed testing page structure
    }

    public function testAddNonce()
    {
        $url = 'http://localhost/foo';
        $this->assertStringStartsWith($url, $this->grav()['uri']::addNonce($url, 'test-action'));
        $this->assertStringStartsWith($url . '/nonce:', $this->grav()['uri']::addNonce($url, 'test-action'));

        $uri = $this->getURI();

        $uri->initializeWithURL($this->grav()['uri']::addNonce($url, 'test-action'))->init();
        $this->assertTrue(is_string($uri->param('nonce')));
        $this->assertSame($uri->param('nonce'), Utils::getNonce('test-action'));
    }
}

