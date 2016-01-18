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



}

