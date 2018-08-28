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
        $this->assertInternalType('string', $composerLocation);
        $this->assertSame('/', $composerLocation[0]);
    }

    public function testGetComposerExecutor()
    {
        $composerExecutor = Composer::getComposerExecutor();
        $this->assertInternalType('string', $composerExecutor);
        $this->assertSame('/', $composerExecutor[0]);
        $this->assertNotNull(strstr($composerExecutor, 'php'));
        $this->assertNotNull(strstr($composerExecutor, 'composer'));
    }

}
