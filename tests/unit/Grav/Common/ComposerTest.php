<?php

use Codeception\Util\Fixtures;
use Grav\Common\Composer;

class ComposerTest extends \PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
    }

    protected function tearDown(): void
    {
    }

    public function testGetComposerLocation(): void
    {
        $composerLocation = Composer::getComposerLocation();
        self::assertIsString($composerLocation);
        // A global composer is an absolute path; Windows always uses the bundled one.
        self::assertTrue($composerLocation === Composer::DEFAULT_PATH || $composerLocation[0] === '/', $composerLocation);
    }

    public function testGetComposerExecutor(): void
    {
        $composerExecutor = Composer::getComposerExecutor();
        self::assertIsString($composerExecutor);
        self::assertMatchesRegularExpression('#^(/|[A-Za-z]:)#', $composerExecutor);
        self::assertNotNull(strstr($composerExecutor, 'php'));
        self::assertNotNull(strstr($composerExecutor, 'composer'));
    }
}
