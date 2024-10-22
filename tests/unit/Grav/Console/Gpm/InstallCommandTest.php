<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Console\Gpm\InstallCommand;

/**
 * Class InstallCommandTest
 */
class InstallCommandTest extends \PHPUnit\Framework\TestCase
{
    /** @var Grav $grav */
    protected $grav;

    /** @var InstallCommand */
    protected $installCommand;


    protected function setUp(): void
    {
        parent::setUp();
        $this->grav = Fixtures::get('grav');
        $this->installCommand = new InstallCommand();
    }

    protected function tearDown(): void
    {
    }
}
