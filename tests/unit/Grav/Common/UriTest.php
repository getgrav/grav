<?php

use Codeception\Util\Fixtures;
use Grav\Common\Uri;

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
        $this->assertsame($uri->paths(), ['grav', 'it', 'ueper']);
        $uri->initializeWithURL('http://localhost:8080/grav/it/ueper:xxx')->init();
        $this->assertsame($uri->paths(), ['grav', 'it']);
        $uri->initializeWithURL('http://localhost:8080/grav/it/ueper?test=x')->init();
        $this->assertsame($uri->paths(), ['grav', 'it', 'ueper']);
        $uri->initializeWithURL('http://localhost:8080/a/b/c/d')->init();
        $this->assertsame($uri->paths(), ['a', 'b', 'c', 'd']);
        $uri->initializeWithURL('http://localhost:8080/a/b/c/d/e/f/a/b/c/d/e/f/a/b/c/d/e/f')->init();
        $this->assertsame($uri->paths(), ['a', 'b', 'c', 'd', 'e', 'f', 'a', 'b', 'c', 'd', 'e', 'f', 'a', 'b', 'c', 'd', 'e', 'f']);
    }

    public function testRoute()
    {
        $uri = $this->getURI();

        $uri->initializeWithURL('http://localhost:8080/grav/it/ueper')->init();
        $this->assertsame($uri->route(), '/grav/it/ueper');
        $uri->initializeWithURL('http://localhost:8080/grav/it/ueper:xxx')->init();
        $this->assertsame($uri->route(), '/grav/it');
        $uri->initializeWithURL('http://localhost:8080/grav/it/ueper?test=x')->init();
        $this->assertsame($uri->route(), '/grav/it/ueper');
        $uri->initializeWithURL('http://localhost:8080/a/b/c/d')->init();
        $this->assertsame($uri->route(), '/a/b/c/d');
        $uri->initializeWithURL('http://localhost:8080/a/b/c/d/e/f/a/b/c/d/e/f/a/b/c/d/e/f')->init();
        $this->assertsame($uri->route(), '/a/b/c/d/e/f/a/b/c/d/e/f/a/b/c/d/e/f');
    }

    public function testQuery()
    {
        $uri = $this->getURI();

        $uri->initializeWithURL('http://localhost:8080/grav/it/ueper')->init();
        $this->assertsame($uri->query(), '');
        $uri->initializeWithURL('http://localhost:8080/grav/it/ueper:xxx')->init();
        $this->assertsame($uri->query(), '');
        $this->assertsame($uri->query('id'), null);
        $uri->initializeWithURL('http://localhost:8080/grav/it/ueper?test=x')->init();
        $this->assertsame($uri->query(), 'test=x');
        $this->assertsame($uri->query('id'), null);
        $this->assertsame($uri->query('test'), 'x');
        $uri->initializeWithURL('http://localhost:8080/grav/it/ueper?test=x&test2=y')->init();
        $this->assertsame($uri->query(), 'test=x&test2=y');
        $this->assertsame($uri->query('id'), null);
        $this->assertsame($uri->query('test'), 'x');
        $this->assertsame($uri->query('test2'), 'y');
        $uri->initializeWithURL('http://localhost:8080/grav/it/ueper?test=x&test2=y&test3=x&test4=y')->init();
        $this->assertsame($uri->query(), 'test=x&test2=y&test3=x&test4=y');
        $this->assertsame($uri->query('id'), null);
        $this->assertsame($uri->query('test'), 'x');
        $this->assertsame($uri->query('test2'), 'y');
        $this->assertsame($uri->query('test4'), 'y');
        //Test all after the ? is encoded in the query
        $uri->initializeWithURL('http://localhost:8080/grav/it/ueper?test=x&test2=y&test3=x&test4=y/test')->init();
        $this->assertsame($uri->query(), 'test=x&test2=y&test3=x&test4=y%2Ftest');
        $this->assertsame($uri->query('id'), null);
        $this->assertsame($uri->query('test'), 'x');
        $this->assertsame($uri->query('test2'), 'y');
        $this->assertsame($uri->query('test4'), 'y/test');
        $uri->initializeWithURL('http://localhost:8080/a/b/c/d')->init();
        $this->assertsame($uri->query(), '');
        $this->assertsame($uri->query('id'), null);
        $uri->initializeWithURL('http://localhost:8080/a/b/c/d/e/f/a/b/c/d/e/f/a/b/c/d/e/f')->init();
        $this->assertsame($uri->query(), '');
        $this->assertsame($uri->query('id'), null);
    }

    public function testParams()
    {
        $uri = $this->getURI();

        $uri->initializeWithURL('http://localhost:8080/grav/it/ueper')->init();
        $this->assertsame($uri->params(), null);
        $uri->initializeWithURL('http://localhost:8080/grav/it/ueper:xxx')->init();
        $this->assertsame($uri->params(), '/ueper:xxx');
        $this->assertsame($uri->params('ueper'), '/ueper:xxx');
        $uri->initializeWithURL('http://localhost:8080/grav/it/ueper:xxx/test:yyy')->init();
        $this->assertsame($uri->params(), '/ueper:xxx/test:yyy');
        $this->assertsame($uri->params('ueper'), '/ueper:xxx');
        $this->assertsame($uri->params('test'), '/test:yyy');
        $uri->initializeWithURL('http://localhost:8080/grav/it/ueper?test=x')->init();
        $this->assertsame($uri->params(), null);
        $this->assertsame($uri->params('ueper'), null);
        $uri->initializeWithURL('http://localhost:8080/grav/it/ueper?test=x&test2=y')->init();
        $this->assertsame($uri->params(), null);
        $this->assertsame($uri->params('ueper'), null);
        $uri->initializeWithURL('http://localhost:8080/grav/it/ueper?test=x&test2=y&test3=x&test4=y')->init();
        $this->assertsame($uri->params(), null);
        $this->assertsame($uri->params('ueper'), null);
        $uri->initializeWithURL('http://localhost:8080/grav/it/ueper?test=x&test2=y&test3=x&test4=y/test')->init();
        $this->assertsame($uri->params(), null);
        $this->assertsame($uri->params('ueper'), null);
        $uri->initializeWithURL('http://localhost:8080/a/b/c/d')->init();
        $this->assertsame($uri->params(), null);
        $this->assertsame($uri->params('ueper'), null);
        $uri->initializeWithURL('http://localhost:8080/a/b/c/d/e/f/a/b/c/d/e/f/a/b/c/d/e/f')->init();
        $this->assertsame($uri->params(), null);
        $this->assertsame($uri->params('ueper'), null);
    }

    public function testParam()
    {
        $uri = $this->getURI();

        $uri->initializeWithURL('http://localhost:8080/grav/it/ueper:xxx')->init();
        $this->assertsame($uri->param('ueper'), 'xxx');
        $uri->initializeWithURL('http://localhost:8080/grav/it/ueper:xxx/test:yyy')->init();
        $this->assertsame($uri->param('ueper'), 'xxx');
        $this->assertsame($uri->param('test'), 'yyy');
    }





}

