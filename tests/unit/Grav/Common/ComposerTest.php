<?php

use Codeception\Util\Fixtures;
use Grav\Common\Composer;

class ComposerTest extends \Codeception\TestCase\Test
{
    protected function _before()
    {
    }

    protected function _after()
    {
    }

    public function testGetComposerLocation()
    {
        $composerLocation = Composer::getComposerLocation();
        $this->assertTrue(is_string($composerLocation));
        $this->assertTrue($composerLocation[0] == '/');
    }

    public function testGetComposerExecutor()
    {
        $composerExecutor = Composer::getComposerExecutor();
        $this->assertTrue(is_string($composerExecutor));
        $this->assertTrue($composerExecutor[0] == '/');
        $this->assertTrue(strstr($composerExecutor, 'php') !== null);
        $this->assertTrue(strstr($composerExecutor, 'composer') !== null);
    }

}

