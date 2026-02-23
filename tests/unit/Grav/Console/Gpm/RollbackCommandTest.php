<?php

use Grav\Console\Gpm\RollbackCommand;
use Grav\Common\Upgrade\SafeUpgradeService;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

class RollbackCommandTest extends \Codeception\TestCase\Test
{
    public function testListSnapshotsOutputsEntries(): void
    {
        $service = new StubRollbackService();
        $command = new TestRollbackCommand($service);
        $command->setSnapshots([
            ['id' => 'snap-1', 'target_version' => '1.7.49'],
            ['id' => 'snap-2', 'target_version' => '1.7.50']
        ]);

        [$style] = $this->injectIo($command, new ArrayInput(['--list' => true]));
        $status = $command->runServe();

        self::assertSame(0, $status);
        $output = implode("\n", $style->messages);
        self::assertStringContainsString('snap-1', $output);
        self::assertStringContainsString('snap-2', $output);
        self::assertFalse($service->rollbackCalled);
    }

    public function testListSnapshotsHandlesAbsence(): void
    {
        $service = new StubRollbackService();
        $command = new TestRollbackCommand($service);

        [$style] = $this->injectIo($command, new ArrayInput(['--list' => true]));
        $status = $command->runServe();

        self::assertSame(0, $status);
        self::assertStringContainsString('No snapshots found', implode("\n", $style->messages));
    }

    public function testRollbackAbortsWhenNoSnapshotsAvailable(): void
    {
        $service = new StubRollbackService();
        $command = new TestRollbackCommand($service);

        [$style] = $this->injectIo($command, new ArrayInput([]));
        $status = $command->runServe();

        self::assertSame(1, $status);
        self::assertStringContainsString('No snapshots available', implode("\n", $style->messages));
    }

    public function testRollbackAbortsWhenSnapshotMissing(): void
    {
        $service = new StubRollbackService();
        $command = new TestRollbackCommand($service);
        $command->setSnapshots([
            ['id' => 'snap-1', 'target_version' => '1.7.49']
        ]);

        [$style] = $this->injectIo($command, new ArrayInput(['manifest' => 'missing']));
        $status = $command->runServe();

        self::assertSame(1, $status);
        self::assertStringContainsString('Snapshot missing not found.', implode("\n", $style->messages));
    }

    public function testRollbackCancelsWhenUserDeclines(): void
    {
        $service = new StubRollbackService();
        $command = new TestRollbackCommand($service, [false]);
        $command->setSnapshots([
            ['id' => 'snap-1', 'target_version' => '1.7.49']
        ]);

        [$style] = $this->injectIo($command, new ArrayInput([]));
        $status = $command->runServe();

        self::assertSame(1, $status);
        self::assertStringContainsString('Rollback aborted.', implode("\n", $style->messages));
    }

    public function testRollbackSucceedsAndClearsRecoveryFlag(): void
    {
        $service = new StubRollbackService();
        $command = new TestRollbackCommand($service, [true]);
        $command->setSnapshots([
            ['id' => 'snap-1', 'target_version' => '1.7.49']
        ]);
        $this->setAllYes($command, true);

        $this->injectIo($command, new ArrayInput([]));
        $status = $command->runServe();

        self::assertSame(0, $status);
        self::assertTrue($service->rollbackCalled);
        self::assertTrue($service->clearFlagCalled);
    }

    private function setAllYes(RollbackCommand $command, bool $value): void
    {
        $ref = new \ReflectionProperty(RollbackCommand::class, 'allYes');
        $ref->setAccessible(true);
        $ref->setValue($command, $value);
    }

    /**
     * @param TestRollbackCommand $command
     * @param ArrayInput $input
     * @return array{0:RollbackMemoryStyle}
     */
    private function injectIo(TestRollbackCommand $command, ArrayInput $input): array
    {
        $buffer = new BufferedOutput();
        $style = new RollbackMemoryStyle($input, $buffer, $command->responses);

        $this->setProtectedProperty($command, 'input', $input);
        $this->setProtectedProperty($command, 'output', $style);

        $input->bind($command->getDefinition());

        return [$style];
    }

    private function setProtectedProperty(object $object, string $property, $value): void
    {
        $ref = new \ReflectionProperty(\Grav\Console\GpmCommand::class, $property);
        $ref->setAccessible(true);
        $ref->setValue($object, $value);
    }
}

class TestRollbackCommand extends RollbackCommand
{
    /** @var SafeUpgradeService */
    private $service;
    /** @var array<int, array> */
    private $snapshots = [];
    /** @var array<int, mixed> */
    public $responses = [];

    public function __construct(SafeUpgradeService $service, array $responses = [])
    {
        parent::__construct();
        $this->service = $service;
        $this->responses = $responses;
    }

    public function setSnapshots(array $snapshots): void
    {
        $this->snapshots = $snapshots;
    }

    protected function createSafeUpgradeService(): SafeUpgradeService
    {
        return $this->service;
    }

    protected function collectSnapshots(): array
    {
        return $this->snapshots;
    }

    public function runServe(): int
    {
        return $this->serve();
    }
}

class StubRollbackService extends SafeUpgradeService
{
    public $rollbackCalled = false;
    public $clearFlagCalled = false;

    public function __construct()
    {
        parent::__construct([]);
    }

    public function rollback(?string $id = null): ?array
    {
        $this->rollbackCalled = true;

        return ['id' => $id];
    }

    public function clearRecoveryFlag(): void
    {
        $this->clearFlagCalled = true;
    }
}

class RollbackMemoryStyle extends SymfonyStyle
{
    /** @var array<int, string> */
    public $messages = [];
    /** @var array<int, mixed> */
    private $responses;

    public function __construct(InputInterface $input, BufferedOutput $output, array $responses = [])
    {
        parent::__construct($input, $output);
        $this->responses = $responses;
    }

    public function newLine($count = 1): void
    {
        for ($i = 0; $i < $count; $i++) {
            $this->messages[] = '';
        }
        parent::newLine($count);
    }

    public function writeln($messages, $type = self::OUTPUT_NORMAL): void
    {
        foreach ((array)$messages as $message) {
            $this->messages[] = (string)$message;
        }
        parent::writeln($messages, $type);
    }

    public function error($message): void
    {
        $this->messages[] = 'error:' . $message;
        parent::error($message);
    }

    public function success($message): void
    {
        $this->messages[] = 'success:' . $message;
        parent::success($message);
    }

    public function askQuestion($question)
    {
        if ($this->responses) {
            return array_shift($this->responses);
        }

        return parent::askQuestion($question);
    }
}
