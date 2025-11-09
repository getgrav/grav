<?php

use Grav\Console\Gpm\PreflightCommand;
use Grav\Common\Upgrade\SafeUpgradeService;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

class PreflightCommandTest extends \Codeception\TestCase\Test
{
    public function testServeOutputsJsonWhenRequested(): void
    {
        $service = new StubSafeUpgradeService([
            'plugins_pending' => [],
            'psr_log_conflicts' => [],
            'monolog_conflicts' => [],
            'warnings' => []
        ]);
        $command = new TestPreflightCommand($service);

        [$style, $output] = $this->injectIo($command, new ArrayInput(['--json' => true]));
        $status = $command->runServe();

        self::assertSame(0, $status);
        $buffer = $output->fetch();
        self::assertJson(trim($buffer));
    }

    public function testServeWarnsWhenIssuesDetected(): void
    {
        $service = new StubSafeUpgradeService([
            'plugins_pending' => ['alpha' => ['type' => 'plugin', 'current' => '1', 'available' => '2']],
            'psr_log_conflicts' => ['beta' => ['requires' => '^1']],
            'monolog_conflicts' => ['gamma' => [['file' => 'user/plugins/gamma/gamma.php', 'method' => '->addError(']]],
            'warnings' => ['pending updates']
        ]);
        $command = new TestPreflightCommand($service);

        [$style] = $this->injectIo($command, new ArrayInput([]));
        $status = $command->runServe();

        self::assertSame(2, $status);
        $output = implode("\n", $style->messages);
        self::assertStringContainsString('pending updates', $output);
        self::assertStringContainsString('beta', $output);
        self::assertStringContainsString('gamma', $output);
    }

    /**
     * @param TestPreflightCommand $command
     * @param ArrayInput $input
     * @return array{0:PreflightMemoryStyle,1:BufferedOutput}
     */
    private function injectIo(TestPreflightCommand $command, ArrayInput $input): array
    {
        $buffer = new BufferedOutput();
        $style = new PreflightMemoryStyle($input, $buffer);

        $this->setProtectedProperty($command, 'input', $input);
        $this->setProtectedProperty($command, 'output', $style);

        $input->bind($command->getDefinition());

        return [$style, $buffer];
    }

    private function setProtectedProperty(object $object, string $property, $value): void
    {
        $ref = new \ReflectionProperty(\Grav\Console\GpmCommand::class, $property);
        $ref->setAccessible(true);
        $ref->setValue($object, $value);
    }
}

class TestPreflightCommand extends PreflightCommand
{
    /** @var SafeUpgradeService */
    private $service;

    public function __construct(SafeUpgradeService $service)
    {
        parent::__construct();
        $this->service = $service;
    }

    protected function createSafeUpgradeService(): SafeUpgradeService
    {
        return $this->service;
    }

    public function runServe(): int
    {
        return $this->serve();
    }
}

class StubSafeUpgradeService extends SafeUpgradeService
{
    /** @var array */
    private $report;

    public function __construct(array $report)
    {
        $this->report = $report;
        parent::__construct([]);
    }

    public function preflight(?string $targetVersion = null): array
    {
        return $this->report;
    }
}

class PreflightMemoryStyle extends SymfonyStyle
{
    /** @var array<int, string> */
    public $messages = [];

    public function __construct(InputInterface $input, BufferedOutput $output)
    {
        parent::__construct($input, $output);
    }

    public function title($message): void
    {
        $this->messages[] = 'title:' . $message;
        parent::title($message);
    }

    public function writeln($messages, $type = self::OUTPUT_NORMAL): void
    {
        foreach ((array)$messages as $message) {
            $this->messages[] = (string)$message;
        }
        parent::writeln($messages, $type);
    }

    public function warning($message): void
    {
        $this->messages[] = 'warning:' . $message;
        parent::warning($message);
    }

    public function success($message): void
    {
        $this->messages[] = 'success:' . $message;
        parent::success($message);
    }
}
