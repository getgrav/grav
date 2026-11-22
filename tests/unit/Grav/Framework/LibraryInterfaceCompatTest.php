<?php

use Codeception\Util\Fixtures;
use Grav\Framework\Cache\Adapter\SessionCache;
use Grav\Framework\Logger\Processors\UserProcessor;
use Grav\Framework\Psr7\ServerRequest;
use Grav\Framework\Relationships\Relationships;
use Monolog\Level;
use Monolog\LogRecord;

/**
 * These classes implement interfaces from PSR-7 2.0, Monolog 3 and Grav's own cache base. Before
 * their signatures matched those interfaces, PHP refused to load them at all (a fatal error on the
 * first use), and the relationship classes logged a deprecation whenever they loaded.
 */
class LibraryInterfaceCompatTest extends \PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $grav();
    }

    public function testServerRequestDecoratesAPsr7Request(): void
    {
        $request = new ServerRequest('GET', 'https://example.com/blog?page=2', ['Accept' => 'text/html'], null, '1.1', ['REMOTE_ADDR' => '127.0.0.1']);
        $request = $request->withQueryParams(['page' => '2'])->withAttribute('route', '/blog')->withCookieParams(['a' => 'b']);

        self::assertInstanceOf(ServerRequest::class, $request);
        self::assertSame(['page' => '2'], $request->getQueryParams());
        self::assertSame('/blog', $request->getAttribute('route'));
        self::assertSame(['route' => '/blog'], $request->getAttributes());
        self::assertSame(['a' => 'b'], $request->getCookieParams());
        self::assertSame('127.0.0.1', $request->getServerParams()['REMOTE_ADDR']);
        self::assertSame([], $request->getUploadedFiles());
        self::assertSame([], $request->withoutAttribute('route')->getAttributes());
        self::assertSame(['x' => 1], $request->withParsedBody(['x' => 1])->getParsedBody());
    }

    public function testUserProcessorTakesAMonologRecord(): void
    {
        $record = new LogRecord(new DateTimeImmutable(), 'grav', Level::Info, 'message');

        $processed = (new UserProcessor())($record);

        self::assertInstanceOf(LogRecord::class, $processed);
        self::assertSame('message', $processed->message);
        self::assertArrayNotHasKey('user', $processed->extra, 'No account, nothing added');

        $grav = \Grav\Common\Grav::instance();
        $before = $grav['user'] ?? null;
        $user = new class (['username' => 'bob', 'email' => 'bob@example.com']) extends \Grav\Common\User\DataUser\User {
            public function exists(): bool
            {
                return true;
            }
        };
        unset($grav['user']);
        $grav['user'] = $user;
        try {
            $processed = (new UserProcessor())(new LogRecord(new DateTimeImmutable(), 'grav', Level::Info, 'signed in'));
        } finally {
            unset($grav['user']);
            if ($before) {
                $grav['user'] = $before;
            }
        }

        self::assertSame(['username' => 'bob', 'email' => 'bob@example.com'], $processed->extra['user']);
    }

    public function testSessionCacheStoresInTheSession(): void
    {
        $session = $_SESSION ?? null;
        $_SESSION = [];
        try {
            $cache = new SessionCache('test-ns');
            self::assertSame('cache-test-ns', $cache->getNamespace());
            self::assertTrue($cache->set('key', 'value'));
            self::assertSame('value', $cache->get('key'));
            self::assertArrayHasKey('cache-test-ns', $_SESSION);
        } finally {
            $_SESSION = $session;
        }
    }

    public function testRelationshipClassesLoadWithoutDeprecations(): void
    {
        $deprecations = [];
        set_error_handler(static function (int $_no, string $message) use (&$deprecations): bool {
            $deprecations[] = $message;

            return true;
        }, E_DEPRECATED);
        try {
            foreach ([Relationships::class, \Grav\Framework\Relationships\ToOneRelationship::class, \Grav\Framework\Relationships\ToManyRelationship::class] as $class) {
                self::assertTrue(class_exists($class));
            }
        } finally {
            restore_error_handler();
        }

        self::assertSame([], $deprecations);
    }
}
