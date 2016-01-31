<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;

/**
 * Class BrowserTest
 */
class BrowserTest extends \Codeception\TestCase\Test
{
    /** @var Grav $grav */
    protected $grav;

    protected function _before()
    {
        $grav = Fixtures::get('grav');
        $this->grav = $grav();
    }

    protected function _after()
    {
    }

    public function testGetBrowser()
    { /* Already covered by PhpUserAgent tests */
    }

    public function testGetPlatform()
    { /* Already covered by PhpUserAgent tests */
    }

    public function testGetLongVersion()
    { /* Already covered by PhpUserAgent tests */
    }

    public function testGetVersion()
    { /* Already covered by PhpUserAgent tests */
    }

    public function testIsHuman()
    {
        //Already Partially covered by PhpUserAgent tests

        //Make sure it recognizes the test as not human
        $this->assertFalse($this->grav['browser']->isHuman());
    }
}

