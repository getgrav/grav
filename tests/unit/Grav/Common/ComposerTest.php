<?php

use Codeception\Util\Fixtures;
use Grav\Common\Composer;

class ComposerTest extends \Codeception\TestCase\Test
{
    protected function _before(): void
    {
    }

    protected function _after(): void
    {
    }

    public function testGetComposerLocation(): void
    {
        $composerLocation = Composer::getComposerLocation();
        self::assertIsString($composerLocation);
        self::assertSame('/', $composerLocation[0]);
    }

    public function testGetComposerExecutor(): void
    {
        $composerExecutor = Composer::getComposerExecutor();
        self::assertIsString($composerExecutor);
        self::assertSame('/', $composerExecutor[0]);
        self::assertNotNull(strstr($composerExecutor, 'php'));
        self::assertNotNull(strstr($composerExecutor, 'composer'));
    }
}
