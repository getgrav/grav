<?php

use Grav\Common\Grav;
use Grav\Common\Service\TaskServiceProvider;
use Grav\Common\Uri;
use Nyholm\Psr7\ServerRequest;
use Pimple\Container;

/**
 * Class TaskServiceProviderTest
 *
 * `task` and `action` come from the request body or the URL params; only a string names one.
 */
class TaskServiceProviderTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Resolve the task and action services for a request with the given parsed body.
     *
     * @param array $body
     * @return array{0: mixed, 1: mixed}
     */
    private function resolve(array $body): array
    {
        $container = new Container();
        (new TaskServiceProvider())->register($container);

        // A bare Grav container holding only what the two services read.
        $grav = (new ReflectionClass(Grav::class))->newInstanceWithoutConstructor();
        (new ReflectionMethod(Container::class, '__construct'))->invoke($grav, [
            'request' => (new ServerRequest('POST', '/'))->withParsedBody($body),
            'uri' => new Uri(),
        ]);

        return [$container->raw('task')($grav), $container->raw('action')($grav)];
    }

    public function testStringTaskAndActionAreKept(): void
    {
        self::assertSame(['login.login', 'save'], $this->resolve(['task' => 'login.login', 'action' => 'save']));
        self::assertSame(['ac', null], $this->resolve(['task' => 'a<b>c', 'action' => '']));
    }

    public function testNonStringTaskAndActionAreIgnored(): void
    {
        self::assertSame([null, null], $this->resolve(['task' => ['x'], 'action' => ['y' => 'z']]));
        self::assertSame([null, null], $this->resolve([]));
    }
}
