<?php

use Pimple\Container;

/**
 * #4300: `initialized()` is the test callers use to ask "is this service already
 * resolved, so I can read it without building it?". It only knew about services
 * built from their own factory closure, so a value assigned directly -- what core
 * and plugins do when they swap `page` for one they resolved themselves (the 404
 * fallback in PagesProcessor, Form's validation error handler) -- reported false
 * and the debugbar stopped rendering on those responses.
 */
class ContainerInitializedTest extends \Codeception\Test\Unit
{
    public function testUnresolvedServiceIsNotInitialized(): void
    {
        $container = new Container();
        $container['page'] = static fn() => new stdClass();

        $this->assertFalse($container->initialized('page'));
    }

    public function testResolvedServiceIsInitialized(): void
    {
        $container = new Container();
        $container['page'] = static fn() => new stdClass();
        $container['page'];

        $this->assertTrue($container->initialized('page'));
    }

    public function testDirectlyAssignedValueIsInitialized(): void
    {
        $container = new Container();
        $container['page'] = static fn() => new stdClass();
        $container['page'];

        // The unset/assign pair plugins use to replace an already resolved service.
        unset($container['page']);
        $container['page'] = new stdClass();

        $this->assertTrue($container->initialized('page'));
    }

    public function testDirectlyAssignedParameterIsInitialized(): void
    {
        $container = new Container();
        $container['answer'] = 42;

        $this->assertTrue($container->initialized('answer'));
    }

    public function testProtectedClosureIsInitialized(): void
    {
        $container = new Container();
        $container['callback'] = $container->protect(static fn() => 'hello');

        $this->assertTrue($container->initialized('callback'));
    }

    public function testFactoryIsNeverInitialized(): void
    {
        $container = new Container();
        $container['thing'] = $container->factory(static fn() => new stdClass());
        $container['thing'];

        $this->assertFalse($container->initialized('thing'), 'a factory builds a new value on every read');
    }

    public function testUnknownIdIsNotInitialized(): void
    {
        $container = new Container();

        $this->assertFalse($container->initialized('nope'));
    }
}
