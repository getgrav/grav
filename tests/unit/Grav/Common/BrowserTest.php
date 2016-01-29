<?php

use Codeception\Util\Fixtures;

class BrowserTest extends \Codeception\TestCase\Test
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    protected function _before()
    {
    }

    protected function _after()
    {
    }

    public function grav()
    {
        return Fixtures::get('grav');
    }

    public function testGetBrowser() { /* Already covered by PhpUserAgent tests */ }
    public function testGetPlatform() { /* Already covered by PhpUserAgent tests */ }
    public function testGetLongVersion() { /* Already covered by PhpUserAgent tests */ }
    public function testGetVersion() { /* Already covered by PhpUserAgent tests */ }

    public function testIsHuman()
    {
        //Already Partially covered by PhpUserAgent tests

        //Make sure it recognizes the test as not human
        $this->assertFalse($this->grav()['browser']->isHuman());
    }
}

