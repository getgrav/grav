<?php

use Grav\Common\Utils;

class UtilsTest extends \Codeception\TestCase\Test
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

    // tests    
    public function testValidation()
    {                
        $this->assertTrue(Utils::startsWith('english', 'en'));
    }

}