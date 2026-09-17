<?php

use Grav\Common\GPM\GPM;
use Grav\Console\Gpm\UpdateCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

class UpdateCommandDiagnosticsTest extends \PHPUnit\Framework\TestCase
{
    public function testLinkedUpdatesAreExplainedWithoutOfferingTheirTargetsForReplacement(): void
    {
        $gpm = $this->getMockBuilder(GPM::class)->disableOriginalConstructor()
            ->onlyMethods(['getInstalledPlugins', 'getRepositoryPlugins'])->getMock();
        $gpm->method('getInstalledPlugins')->willReturn([
            'form' => (object)['symlink' => true, 'version' => '9.1.6'],
            'email' => (object)['symlink' => false, 'version' => '5.0.3'],
            'current' => (object)['symlink' => true, 'version' => '2.0.0'],
        ]);
        $gpm->method('getRepositoryPlugins')->willReturn([
            'form' => (object)['version' => '9.1.28'],
            'email' => (object)['version' => '5.2.1'],
            'current' => (object)['version' => '2.0.0'],
        ]);
        $command = new UpdateDiagnosticsProbe();
        $output = new BufferedOutput();
        $command->prepare($gpm, new SymfonyStyle(new ArrayInput([]), $output));
        $command->report(['plugins' => true, 'themes' => false], []);
        $message = $output->fetch();
        self::assertStringContainsString('Skipped form (9.1.6 → 9.1.28): symbolic link', $message);
        self::assertStringNotContainsString('email', $message);
        self::assertStringNotContainsString('current', $message);
        $command->report(['plugins' => true], ['email']);
        self::assertSame('', $output->fetch());
    }
}

class UpdateDiagnosticsProbe extends UpdateCommand
{
    public function prepare(GPM $gpm, SymfonyStyle $output): void
    {
        $this->gpm = $gpm;
        $this->output = $output;
    }

    public function report(array $types, array $packages): void
    {
        $this->reportSkippedSymlinks($types, $packages);
    }
}
