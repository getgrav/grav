<?php

use Codeception\Util\Fixtures;
use Grav\Common\Cache;
use Grav\Common\Grav;
use Symfony\Component\Cache\Adapter\MemcachedAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;

/**
 * The Redis and Memcached drivers open one connection per request and share it between the
 * main, shared and Flex caches; their namespaces stay apart through key prefixes.
 *
 * Needs the extension and a server: GRAV_TEST_REDIS_HOST / GRAV_TEST_MEMCACHED_HOST (default
 * 127.0.0.1). Skipped when either is missing.
 */
class CacheSharedClientTest extends \PHPUnit\Framework\TestCase
{
    /** @var Grav */
    protected $grav;

    /** @var array<string,mixed> */
    protected $configBefore = [];

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $this->grav = $grav();
        foreach (['system.cache.driver', 'system.cache.redis', 'system.cache.memcached'] as $key) {
            $this->configBefore[$key] = $this->grav['config']->get($key);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->configBefore as $key => $value) {
            $this->grav['config']->set($key, $value);
        }
        parent::tearDown();
    }

    public function testRedisAdaptersShareOneConnection(): void
    {
        if (!extension_loaded('redis')) {
            self::markTestSkipped('Needs the redis extension.');
        }
        $host = getenv('GRAV_TEST_REDIS_HOST') ?: '127.0.0.1';
        try {
            (new \Redis())->connect($host, 6379, 0.5);
        } catch (\Throwable) {
            self::markTestSkipped("No Redis server on {$host}.");
        }

        $config = $this->grav['config'];
        $config->set('system.cache.driver', 'redis');
        $config->set('system.cache.redis', ['server' => $host, 'port' => 6379]);
        $cache = new Cache($this->grav);

        $first = $cache->getCacheAdapter('grav-test-a');
        $second = $cache->getCacheAdapter('grav-test-b');
        self::assertInstanceOf(RedisAdapter::class, $first);
        self::assertInstanceOf(RedisAdapter::class, $second);
        self::assertSame($this->client($first, 'redis'), $this->client($second, 'redis'), 'One connection for every namespace');

        $this->assertNamespacesStayApart($first, $second);

        $config->set('system.cache.redis', ['server' => $host, 'port' => 6379, 'database' => 1]);
        $other = $cache->getCacheAdapter('grav-test-a');
        self::assertNotSame($this->client($first, 'redis'), $this->client($other, 'redis'), 'Different settings get their own connection');
    }

    public function testMemcachedAdaptersShareOneClient(): void
    {
        if (!extension_loaded('memcached')) {
            self::markTestSkipped('Needs the memcached extension.');
        }
        $host = getenv('GRAV_TEST_MEMCACHED_HOST') ?: '127.0.0.1';
        $probe = new \Memcached();
        $probe->addServer($host, 11211);
        if ($probe->getVersion() === false) {
            self::markTestSkipped("No Memcached server on {$host}.");
        }

        $config = $this->grav['config'];
        $config->set('system.cache.driver', 'memcached');
        $config->set('system.cache.memcached', ['server' => $host, 'port' => 11211]);
        $cache = new Cache($this->grav);

        $first = $cache->getCacheAdapter('grav-test-a');
        $second = $cache->getCacheAdapter('grav-test-b');
        self::assertInstanceOf(MemcachedAdapter::class, $first);
        self::assertSame($this->client($first, 'client'), $this->client($second, 'client'));

        $this->assertNamespacesStayApart($first, $second);
    }

    private function assertNamespacesStayApart($first, $second): void
    {
        $key = 'item-' . bin2hex(random_bytes(4));
        self::assertTrue($first->save($first->getItem($key)->set('from a')));
        self::assertTrue($second->save($second->getItem($key)->set('from b')));
        self::assertSame('from a', $first->getItem($key)->get());
        self::assertSame('from b', $second->getItem($key)->get());
        $first->deleteItem($key);
        $second->deleteItem($key);
    }

    private function client(object $adapter, string $property): object
    {
        $reflection = new ReflectionObject($adapter);
        while (!$reflection->hasProperty($property)) {
            $reflection = $reflection->getParentClass();
        }

        return $reflection->getProperty($property)->getValue($adapter);
    }
}
