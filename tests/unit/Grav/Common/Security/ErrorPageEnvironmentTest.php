<?php

use Grav\Common\Errors\Errors;
use Whoops\Handler\PrettyPageHandler;

/**
 * The full error page (`errors.display: 1`) is shown to whoever causes the error, so it must
 * not print what the site keeps in its environment: the variables of the server and of Grav's
 * `.env` files, which Whoops lists from $_SERVER and $_ENV, and the arguments of the stack
 * frames, one of which is the whole of $_SERVER (Uri::createFromEnvironment()).
 */
class ErrorPageEnvironmentTest extends \PHPUnit\Framework\TestCase
{
    /** @var array */
    protected $server;

    /** @var array */
    protected $env;

    /** @var string|false */
    protected $ignoreArgs;

    protected function setUp(): void
    {
        parent::setUp();
        $this->server = $_SERVER;
        $this->env = $_ENV;
        // PHP's default, and what a server without php.ini-production runs with.
        $this->ignoreArgs = ini_set('zend.exception_ignore_args', '0');
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
        $_ENV = $this->env;
        ini_set('zend.exception_ignore_args', (string) $this->ignoreArgs);
        parent::tearDown();
    }

    /**
     * Render the full error page for an exception, as Errors does for an uncaught one.
     */
    private function errorPage(Throwable $exception): string
    {
        $whoops = (new ReflectionMethod(Errors::class, 'createWhoops'))->invoke(new Errors(), 1, false, false);
        foreach ($whoops->getHandlers() as $handler) {
            if ($handler instanceof PrettyPageHandler) {
                // Whoops leaves the page out on the command line.
                $handler->handleUnconditionally(true);
            }
        }
        $whoops->allowQuit(false);
        $whoops->writeToOutput(false);

        return $whoops->handleException($exception);
    }

    /**
     * A random value: the page quotes the source of this file.
     */
    private function secret(): string
    {
        return 'secret-' . bin2hex(random_bytes(12));
    }

    /**
     * An exception whose first stack frame has $argument as its argument.
     */
    private function failWith(string $argument): Throwable
    {
        return new RuntimeException('failure');
    }

    public function testEnvironmentVariablesAreMasked(): void
    {
        $secret = $this->secret();
        // Where Symfony Dotenv puts a line of `.env`, and Apache a `SetEnv`.
        $_SERVER['GRAV_CONFIG__plugins__email__mailer__smtp__password'] = $secret;
        $_ENV['GRAV_CONFIG__plugins__email__mailer__smtp__password'] = $secret;
        $_SERVER['MY_API_KEY'] = $secret;

        $page = $this->errorPage(new RuntimeException('failure'));

        self::assertStringContainsString('GRAV_CONFIG__plugins__email__mailer__smtp__password', $page);
        self::assertStringContainsString('MY_API_KEY', $page);
        self::assertStringNotContainsString($secret, $page);
    }

    public function testRequestVariablesKeepTheirValue(): void
    {
        $uri = '/page-' . bin2hex(random_bytes(6));
        $agent = 'agent-' . bin2hex(random_bytes(6));
        $_SERVER['REQUEST_URI'] = $uri;
        $_SERVER['HTTP_USER_AGENT'] = $agent;

        $page = $this->errorPage(new RuntimeException('failure'));

        self::assertStringContainsString($uri, $page);
        self::assertStringContainsString($agent, $page);
    }

    public function testStackFrameArgumentsAreLeftOut(): void
    {
        $secret = $this->secret();
        $exception = $this->failWith($secret);
        self::assertSame([$secret], $exception->getTrace()[0]['args']);

        $page = $this->errorPage($exception);

        self::assertStringContainsString('failWith', $page);
        self::assertStringNotContainsString($secret, $page);
    }
}
