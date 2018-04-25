<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;

/**
 * Class InstallCommandTest
 */
class InstallCommandTest extends \Codeception\TestCase\Test
{
    /** @var Grav $grav */
    protected $grav;

    /** @var InstallCommand */
    protected $installCommand;


    protected function _before()
    {
        $this->grav = Fixtures::get('grav');
        $this->installCommand = new InstallCommand();

    }

    protected function _after()
    {
    }
}