<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Console\Gpm\InstallCommand;

/**
 * Class InstallCommandTest
 */
class InstallCommandTest extends \Codeception\TestCase\Test
{
    /** @var Grav $grav */
    protected $grav;

    /** @var InstallCommand */
    protected $installCommand;


    protected function _before(): void
    {
        $this->grav = Fixtures::get('grav');
        $this->installCommand = new InstallCommand();
    }

    protected function _after(): void
    {
    }
}
