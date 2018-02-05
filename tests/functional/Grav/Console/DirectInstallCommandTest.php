<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Console\Gpm\DirectInstallCommand;


/**
 * Class DirectInstallCommandTest
 */
class DirectInstallCommandTest extends \Codeception\TestCase\Test
{
    /** @var Grav $grav */
    protected $grav;

    /** @var DirectInstallCommand */
    protected $directInstall;


    protected function _before()
    {
        $this->grav = Fixtures::get('grav');
        $this->directInstallCommand = new DirectInstallCommand();
    }

}

/**
 * Why this test file is empty
 *
 * Wasn't able to call a symfony\console. Kept having $output problem.
 * symfony console \NullOutput didn't cut it.
 *
 * We would also need to Mock tests since downloading packages would
 * make tests slow and unreliable. But it's not worth the time ATM.
 *
 * Look at Gpm/InstallCommandTest.php
 *
 * For the full story: https://git.io/vSlI3
 */
