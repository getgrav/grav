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

    protected function _before(): void
    {
        $grav = Fixtures::get('grav');
        $this->grav = $grav();
    }

    protected function _after(): void
    {
    }

    public function testGetBrowser(): void
    {
 /* Already covered by PhpUserAgent tests */
    }

    public function testGetPlatform(): void
    {
 /* Already covered by PhpUserAgent tests */
    }

    public function testGetLongVersion(): void
    {
 /* Already covered by PhpUserAgent tests */
    }

    public function testGetVersion(): void
    {
 /* Already covered by PhpUserAgent tests */
    }

    public function testIsHuman(): void
    {
        //Already Partially covered by PhpUserAgent tests

        //Make sure it recognizes the test as not human
        self::assertFalse($this->grav['browser']->isHuman());
    }
}
