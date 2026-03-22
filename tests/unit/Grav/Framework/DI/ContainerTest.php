<?php

use Grav\Framework\DI\Container;
use Pimple\Exception\UnknownIdentifierException;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Grav\Framework\DI\Container magic methods.
 *
 * These tests verify that the __get() and __isset() magic methods
 * correctly bridge property access to ArrayAccess.
 */
class ContainerTest extends TestCase
{
    /** @var Container */
    protected $container;

    protected function setUp(): void
    {
        parent::setUp();
        $this->container = new Container();
    }

    protected function tearDown(): void
    {
        $this->container = null;
        parent::tearDown();
    }

    // ============================================================
    // __get() Magic Method Tests
    // ============================================================

    public function testGetReturnsStringParameter(): void
    {
        $this->container['name'] = 'test-value';

        self::assertEquals('test-value', $this->container->name);
    }

    public function testGetReturnsIntegerParameter(): void
    {
        $this->container['count'] = 42;

        self::assertEquals(42, $this->container->count);
    }

    public function testGetReturnsArrayParameter(): void
    {
        $this->container['data'] = ['key' => 'value', 'nested' => [1, 2, 3]];

        self::assertEquals(['key' => 'value', 'nested' => [1, 2, 3]], $this->container->data);
    }

    public function testGetReturnsBooleanParameter(): void
    {
        $this->container['enabled'] = true;
        $this->container['disabled'] = false;

        self::assertTrue($this->container->enabled);
        self::assertFalse($this->container->disabled);
    }

    public function testGetReturnsNullParameter(): void
    {
        $this->container['nullable'] = null;

        self::assertNull($this->container->nullable);
    }

    public function testGetReturnsObject(): void
    {
        $object = new \stdClass();
        $object->property = 'value';

        $this->container['obj'] = $object;

        self::assertSame($object, $this->container->obj);
        self::assertEquals('value', $this->container->obj->property);
    }

    public function testGetResolvesClosureAsService(): void
    {
        $this->container['service'] = function () {
            $obj = new \stdClass();
            $obj->name = 'resolved-service';
            return $obj;
        };

        self::assertInstanceOf(\stdClass::class, $this->container->service);
        self::assertEquals('resolved-service', $this->container->service->name);
    }

    public function testGetResolvesLazyService(): void
    {
        $callCount = 0;

        $this->container['lazy'] = function () use (&$callCount) {
            $callCount++;
            return new \stdClass();
        };

        // First access creates the service
        $service1 = $this->container->lazy;
        self::assertEquals(1, $callCount);

        // Second access returns cached service (not a factory)
        $service2 = $this->container->lazy;
        self::assertEquals(1, $callCount);
        self::assertSame($service1, $service2);
    }

    public function testGetResolvesFactoryService(): void
    {
        $callCount = 0;

        $this->container->factory(function () use (&$callCount) {
            $callCount++;
            return new \stdClass();
        });

        $this->container['factory'] = $this->container->factory(function () use (&$callCount) {
            $callCount++;
            $obj = new \stdClass();
            $obj->id = $callCount;
            return $obj;
        });

        // Each access creates a new instance
        $service1 = $this->container->factory;
        self::assertEquals(1, $callCount);

        $service2 = $this->container->factory;
        self::assertEquals(2, $callCount);

        self::assertNotSame($service1, $service2);
        self::assertEquals(1, $service1->id);
        self::assertEquals(2, $service2->id);
    }

    public function testGetThrowsExceptionForUnknownProperty(): void
    {
        $this->expectException(UnknownIdentifierException::class);

        $value = $this->container->nonexistent;
    }

    public function testGetEquivalentToArrayAccess(): void
    {
        $this->container['test'] = 'value';

        self::assertEquals($this->container['test'], $this->container->test);
    }

    public function testGetCallsOnInvoke(): void
    {
        $this->container['invokable'] = new class {
            public function __invoke(): string
            {
                return 'invoked';
            }
        };

        self::assertEquals('invoked', $this->container->invokable);
    }

    // ============================================================
    // __isset() Magic Method Tests
    // ============================================================

    public function testIssetReturnsTrueForExistingProperty(): void
    {
        $this->container['exists'] = 'value';

        self::assertTrue(isset($this->container->exists));
    }

    public function testIssetReturnsFalseForNonExistentProperty(): void
    {
        self::assertFalse(isset($this->container->nonexistent));
    }

    public function testIssetReturnsTrueForNullValue(): void
    {
        $this->container['nullable'] = null;

        // Pimple's offsetExists() returns true for keys that are set (even to null)
        // This is different from PHP's native isset() behavior
        self::assertTrue(isset($this->container->nullable));
    }

    public function testIssetReturnsTrueForFalseValue(): void
    {
        $this->container['false'] = false;

        self::assertTrue(isset($this->container->false));
    }

    public function testIssetReturnsTrueForZeroValue(): void
    {
        $this->container['zero'] = 0;

        self::assertTrue(isset($this->container->zero));
    }

    public function testIssetReturnsTrueForEmptyString(): void
    {
        $this->container['empty'] = '';

        self::assertTrue(isset($this->container->empty));
    }

    public function testIssetReturnsTrueForEmptyArray(): void
    {
        $this->container['empty_array'] = [];

        self::assertTrue(isset($this->container->empty_array));
    }

    // ============================================================
    // Property Access vs Array Access Equivalence Tests
    // ============================================================

    public function testPropertyAccessMatchesArrayAccessForService(): void
    {
        $this->container['service'] = function () {
            return new \DateTime('2024-01-01');
        };

        $viaProperty = $this->container->service;
        $viaArray = $this->container['service'];

        self::assertSame($viaProperty, $viaArray);
        self::assertEquals('2024-01-01', $viaProperty->format('Y-m-d'));
    }

    public function testPropertyAccessAndArrayAccessShareSameCache(): void
    {
        $callCount = 0;

        $this->container['counter'] = function () use (&$callCount) {
            $callCount++;
            return new \stdClass();
        };

        // Access via property
        $viaProperty = $this->container->counter;
        self::assertEquals(1, $callCount);

        // Access via array - should return same cached instance
        $viaArray = $this->container['counter'];
        self::assertEquals(1, $callCount);

        self::assertSame($viaProperty, $viaArray);
    }

    public function testIssetMatchesArrayAccess(): void
    {
        $this->container['test'] = 'value';

        self::assertEquals(
            isset($this->container['test']),
            isset($this->container->test)
        );

        self::assertEquals(
            isset($this->container['nonexistent']),
            isset($this->container->nonexistent)
        );
    }

    // ============================================================
    // Chained Access Tests
    // ============================================================

    public function testChainedPropertyAccess(): void
    {
        $this->container['config'] = function () {
            return new class {
                public function get(string $key): string
                {
                    return "value-for-{$key}";
                }
            };
        };

        self::assertEquals('value-for-test', $this->container->config->get('test'));
    }

    public function testChainedPropertyAccessWithNestedService(): void
    {
        $this->container['outer'] = function ($c) {
            return new class($c) {
                private $container;

                public function __construct($container)
                {
                    $this->container = $container;
                }

                public function getInner()
                {
                    return $this->container->inner;
                }
            };
        };

        $this->container['inner'] = function () {
            return 'inner-value';
        };

        self::assertEquals('inner-value', $this->container->outer->getInner());
    }

    // ============================================================
    // Edge Cases
    // ============================================================

    public function testPropertyAccessWithNumericKey(): void
    {
        $this->container['123'] = 'numeric-key';

        self::assertEquals('numeric-key', $this->container->{123});
    }

    public function testPropertyAccessWithSpecialCharactersInKey(): void
    {
        // PHP property names can't have special characters, but the key in container can
        $this->container['with_underscore'] = 'underscore-value';

        self::assertEquals('underscore-value', $this->container->with_underscore);
    }

    public function testPropertyAccessAfterUnset(): void
    {
        $this->container['temp'] = 'temporary';

        self::assertTrue(isset($this->container->temp));

        unset($this->container['temp']);

        self::assertFalse(isset($this->container->temp));

        $this->expectException(UnknownIdentifierException::class);
        $value = $this->container->temp;
    }

    public function testPropertyAccessWithClosureReceivingContainer(): void
    {
        $this->container['dependency'] = 'dep-value';
        $this->container['service'] = function ($c) {
            return 'service-with-' . $c['dependency'];
        };

        self::assertEquals('service-with-dep-value', $this->container->service);
    }

    public function testPropertyAccessWithExtend(): void
    {
        $this->container['original'] = function () {
            $obj = new \stdClass();
            $obj->value = 'original';
            return $obj;
        };

        $this->container->extend('original', function ($obj) {
            $obj->value = 'extended';
            return $obj;
        });

        self::assertEquals('extended', $this->container->original->value);
    }
}
