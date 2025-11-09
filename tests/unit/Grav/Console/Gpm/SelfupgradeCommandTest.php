<?php

use Codeception\Util\Fixtures;
use Grav\Console\Gpm\SelfupgradeCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

class SelfupgradeCommandTest extends \Codeception\TestCase\Test
{
    public function testHandlePreflightReportSucceedsWithoutIssues(): void
    {
        $command = new TestSelfupgradeCommand();
        [$style] = $this->injectIo($command);

        $result = $command->runHandle([
            'plugins_pending' => [],
            'psr_log_conflicts' => [],
            'warnings' => [],
            'is_major_minor_upgrade' => false
        ]);

        self::assertTrue($result);
        self::assertSame([], $style->messages);
    }

    public function testHandlePreflightReportFailsWhenPendingEvenWithAllYes(): void
    {
        $command = new TestSelfupgradeCommand();
        [$style] = $this->injectIo($command);
        $this->setAllYes($command, true);

        $result = $command->runHandle([
            'plugins_pending' => ['foo' => ['type' => 'plugin', 'current' => '1', 'available' => '2']],
            'psr_log_conflicts' => ['bar' => ['requires' => '^1.0']],
            'warnings' => ['pending'],
            'is_major_minor_upgrade' => true
        ]);

        self::assertFalse($result);
        $output = implode("\n", $style->messages);
        self::assertStringContainsString('Run `bin/gpm update` first', $output);
    }

    public function testHandlePreflightReportAbortsOnPendingWhenDeclined(): void
    {
        $command = new TestSelfupgradeCommand();
        [$style] = $this->injectIo($command);
        $this->setAllYes($command, false);

        $result = $command->runHandle([
            'plugins_pending' => ['foo' => ['type' => 'plugin', 'current' => '1', 'available' => '2']],
            'psr_log_conflicts' => [],
            'warnings' => [],
            'is_major_minor_upgrade' => true
        ]);

        self::assertFalse($result);
        self::assertStringContainsString('Run `bin/gpm update` first', implode("\n", $style->messages));
    }

    public function testHandlePreflightReportAbortsOnConflictWhenDeclined(): void
    {
        $command = new TestSelfupgradeCommand();
        [$style] = $this->injectIo($command, ['abort']);
        $this->setAllYes($command, false);

        $result = $command->runHandle([
            'plugins_pending' => [],
            'psr_log_conflicts' => ['foo' => ['requires' => '^1.0']],
            'warnings' => [],
            'is_major_minor_upgrade' => false
        ]);

        self::assertFalse($result);
        self::assertStringContainsString('Adjust composer requirements', implode("\n", $style->messages));
    }

    public function testHandlePreflightReportDisablesPluginsWhenRequested(): void
    {
        $gravFactory = Fixtures::get('grav');
        $grav = $gravFactory();
        $stub = new class {
            public $disabled = [];
            public function disablePlugin(string $slug, array $context = []): void
            {
                $this->disabled[] = $slug;
            }
        };
        $grav['recovery'] = $stub;

        $command = new TestSelfupgradeCommand();
        [$style] = $this->injectIo($command, ['disable']);

        $result = $command->runHandle([
            'plugins_pending' => [],
            'psr_log_conflicts' => ['foo' => ['requires' => '^1.0']],
            'warnings' => [],
            'is_major_minor_upgrade' => false
        ]);

        self::assertTrue($result);
        self::assertSame(['foo'], $stub->disabled);
        $output = implode("\n", $style->messages);
        self::assertStringContainsString('Continuing with conflicted plugins disabled.', $output);
    }

    public function testHandlePreflightReportContinuesWhenRequested(): void
    {
        $command = new TestSelfupgradeCommand();
        [$style] = $this->injectIo($command, ['continue']);

        $result = $command->runHandle([
            'plugins_pending' => [],
            'psr_log_conflicts' => ['foo' => ['requires' => '^1.0']],
            'warnings' => [],
            'is_major_minor_upgrade' => false
        ]);

        self::assertTrue($result);
        $output = implode("\n", $style->messages);
        self::assertStringContainsString('Proceeding with potential psr/log incompatibilities still active.', $output);
    }

    /**
     * @param TestSelfupgradeCommand $command
     * @param array<int, mixed> $responses
     * @return array{0:SelfUpgradeMemoryStyle,1:InputInterface}
     */
    private function injectIo(TestSelfupgradeCommand $command, array $responses = []): array
    {
        $input = new ArrayInput([]);
        $buffer = new BufferedOutput();
        $style = new SelfUpgradeMemoryStyle($input, $buffer, $responses);

        $this->setProtectedProperty($command, 'input', $input);
        $this->setProtectedProperty($command, 'output', $style);

        $input->bind($command->getDefinition());

        return [$style, $input];
    }

    private function setAllYes(SelfupgradeCommand $command, bool $value): void
    {
        $ref = new \ReflectionProperty(SelfupgradeCommand::class, 'all_yes');
        $ref->setAccessible(true);
        $ref->setValue($command, $value);
    }

    private function setProtectedProperty(object $object, string $property, $value): void
    {
        $ref = new \ReflectionProperty(\Grav\Console\GpmCommand::class, $property);
        $ref->setAccessible(true);
        $ref->setValue($object, $value);
    }
}

class TestSelfupgradeCommand extends SelfupgradeCommand
{
    public function runHandle(array $report): bool
    {
        return $this->handlePreflightReport($report);
    }
}

class SelfUpgradeMemoryStyle extends SymfonyStyle
{
    /** @var array<int, string> */
    public $messages = [];
    /** @var array<int, mixed> */
    private $responses;

    /**
     * @param InputInterface $input
     * @param BufferedOutput $output
     * @param array<int, mixed> $responses
     */
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

    public function askQuestion($question)
    {
        if ($this->responses) {
            return array_shift($this->responses);
        }

        return parent::askQuestion($question);
    }

    public function choice($question, array $choices, $default = null, $attempts = null, $errorMessage = 'Invalid value.')
    {
        if ($this->responses) {
            return array_shift($this->responses);
        }

        return parent::choice($question, $choices, $default, $attempts, $errorMessage);
    }
}
