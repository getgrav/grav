<?php

use Codeception\Util\Fixtures;
use Grav\Common\Config\Setup;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use Grav\Common\Scheduler\Job;
use Grav\Common\Scheduler\Scheduler;

/**
 * `bin/grav scheduler --env=<host>` loaded user/env/<host>/config, but the commands it started
 * did not: a Grav CLI child with nothing pinning its environment resolves to 'cli'. Command jobs
 * now get the scheduler's environment as GRAV_ENVIRONMENT, under the same rule the generated
 * cron line uses to decide whether to append --env (#4248).
 */
class JobEnvironmentTest extends \Codeception\Test\Unit
{
    private const ENVIRONMENT = 'scheduler-env-test.local';

    /** @var Grav */
    private $grav;

    /** @var string|null */
    private $savedEnvironment;

    /** @var string */
    private $root;

    /** @var array The runner's own GRAV_ENVIRONMENT, if any: [getenv, $_SERVER, $_ENV] */
    private $savedVariable;

    protected function _before(): void
    {
        // Children inherit the runner's environment, so take a GRAV_ENVIRONMENT exported in the
        // shell running the tests out of the way for the duration.
        $this->savedVariable = [getenv('GRAV_ENVIRONMENT'), $_SERVER['GRAV_ENVIRONMENT'] ?? null, $_ENV['GRAV_ENVIRONMENT'] ?? null];
        putenv('GRAV_ENVIRONMENT');
        unset($_SERVER['GRAV_ENVIRONMENT'], $_ENV['GRAV_ENVIRONMENT']);

        $grav = Fixtures::get('grav');
        $this->grav = $grav();
        $this->savedEnvironment = Setup::$environment;
        $this->root = sys_get_temp_dir() . '/grav-scheduler-env-' . bin2hex(random_bytes(4));
        Folder::create($this->root);
    }

    protected function _after(): void
    {
        Setup::$environment = $this->savedEnvironment;
        Folder::delete($this->root);

        [$env, $server, $envArray] = $this->savedVariable;
        if (false !== $env) {
            putenv('GRAV_ENVIRONMENT=' . $env);
        }
        if (null !== $server) {
            $_SERVER['GRAV_ENVIRONMENT'] = $server;
        }
        if (null !== $envArray) {
            $_ENV['GRAV_ENVIRONMENT'] = $envArray;
        }

        // The environment:// stream was pointed at a temporary folder; start the next test clean.
        $grav = Fixtures::get('grav');
        $grav();
    }

    public function testCommandJobGetsTheOverrideEnvironment(): void
    {
        $this->useEnvironment(self::ENVIRONMENT, true);

        $job = $this->printEnvironmentJob();

        $this->assertSame(['GRAV_ENVIRONMENT' => self::ENVIRONMENT], $this->processEnvironment($job));
        $this->assertTrue($job->inForeground()->run());
        $this->assertSame(self::ENVIRONMENT, $job->getOutput());
    }

    public function testNothingIsSetForTheBareCli(): void
    {
        $this->useEnvironment('cli', true);

        $this->assertNothingSet();
    }

    public function testNothingIsSetForAnUnknownEnvironment(): void
    {
        $this->useEnvironment('unknown', true);

        $this->assertNothingSet();
    }

    public function testNothingIsSetForAnEnvironmentWithoutItsOwnConfig(): void
    {
        $this->useEnvironment(self::ENVIRONMENT, false);

        $this->assertNothingSet();
    }

    public function testTheRuleMatchesTheCronLine(): void
    {
        $this->useEnvironment(self::ENVIRONMENT, true);
        $scheduler = $this->grav['scheduler'];

        $this->assertSame(self::ENVIRONMENT, $scheduler->getOverrideEnvironment());
        $this->assertStringEndsWith(' --env=' . self::ENVIRONMENT, $scheduler->getSchedulerCommand('php'));
        $this->assertSame(Scheduler::resolveOverrideEnvironment(), $scheduler->getOverrideEnvironment());
    }

    public function testGravConsoleInTheChildUsesTheVariable(): void
    {
        $this->useEnvironment(self::ENVIRONMENT, true);

        $job = new Job(PHP_BINARY, [$this->consoleScript(), 'list']);

        $this->assertTrue($job->inForeground()->run());
        $this->assertTrue($job->isSuccessful(), (string) $job->getOutput());
        $this->assertSame(self::ENVIRONMENT, $job->getOutput());
    }

    public function testAnExplicitEnvInTheJobArgumentsWins(): void
    {
        $this->useEnvironment(self::ENVIRONMENT, true);

        $job = new Job(PHP_BINARY, [$this->consoleScript(), '--env=explicit.local', 'list']);

        $this->assertTrue($job->inForeground()->run());
        $this->assertTrue($job->isSuccessful(), (string) $job->getOutput());
        $this->assertSame('explicit.local', $job->getOutput());
    }

    public function testClosureJobIsUnchanged(): void
    {
        $this->useEnvironment(self::ENVIRONMENT, true);

        $job = new Job(static fn () => var_export(getenv('GRAV_ENVIRONMENT'), true), [], 'closure-env');

        $this->assertTrue($job->run());
        $this->assertSame('false', $job->getOutput());
        $this->assertNull($job->getProcess());
    }

    private function assertNothingSet(): void
    {
        $job = $this->printEnvironmentJob();

        $this->assertNull($this->processEnvironment($job));
        $this->assertTrue($job->inForeground()->run());
        $this->assertSame('none', $job->getOutput());
    }

    /**
     * Pretend the process booted in $environment, optionally with a user/env/<name>/config
     * folder of its own.
     */
    private function useEnvironment(string $environment, bool $withConfig): void
    {
        Setup::$environment = $environment;

        if ($withConfig) {
            Folder::create($this->root . '/env/config');
            $this->grav['locator']->addPath('environment', '', $this->root . '/env');
        }
    }

    private function printEnvironmentJob(): Job
    {
        return new Job(PHP_BINARY, ['-r', 'echo getenv("GRAV_ENVIRONMENT") ?: "none";'], 'print-env');
    }

    /**
     * A child that boots the Grav console the way bin/grav does and prints the environment
     * Setup resolved.
     */
    private function consoleScript(): string
    {
        $script = $this->root . '/console.php';
        $autoload = var_export(GRAV_ROOT . '/vendor/autoload.php', true);

        file_put_contents($script, <<<PHP
<?php
define('GRAV_CLI', true);
define('GRAV_REQUEST_TIME', microtime(true));
\$autoload = require {$autoload};
\Grav\Common\Grav::instance(['loader' => \$autoload]);
\$app = new \Grav\Console\Application\GravApplication('test', 'test');
\$app->getCommandName(new \Symfony\Component\Console\Input\ArgvInput(\$argv));
echo \Grav\Common\Config\Setup::\$environment;
PHP);

        return $script;
    }

    private function processEnvironment(Job $job): ?array
    {
        return (new ReflectionMethod($job, 'processEnvironment'))->invoke($job);
    }
}
