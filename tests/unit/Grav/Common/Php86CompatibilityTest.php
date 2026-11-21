<?php

use Codeception\Util\Fixtures;
use Grav\Common\Debugger;
use Grav\Common\Grav;
use Grav\Common\Page\Page;
use Grav\Common\Page\Pages;
use Grav\Common\Scheduler\Job;
use Grav\Common\Service\RequestServiceProvider;
use Grav\Common\Twig\Extension\GravExtension;
use Grav\Common\Uri;
use Grav\Common\Utils;
use Grav\Framework\Object\Access\NestedPropertyTrait;
use Grav\Framework\Object\Base\ObjectTrait;
use Grav\Framework\Uri\UriFactory;
use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Stream;
use Pimple\Container;

/**
 * Regression tests for PHP 8.6 compatibility fixes (while remaining 8.3+ compatible).
 */
class Php86CompatibilityTest extends \Codeception\Test\Unit
{
    /**
     * Run a callback and fail if any E_DEPRECATED / E_USER_DEPRECATED is triggered.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    private function assertNoDeprecation(callable $callback): mixed
    {
        $deprecations = [];
        set_error_handler(static function (int $errno, string $errstr) use (&$deprecations): bool {
            if ($errno === E_DEPRECATED || $errno === E_USER_DEPRECATED) {
                $deprecations[] = $errstr;
                return true;
            }
            return false;
        });

        try {
            $result = $callback();
        } finally {
            restore_error_handler();
        }

        $this->assertSame([], $deprecations, 'Unexpected deprecation(s): ' . implode('; ', $deprecations));

        return $result;
    }

    public function testNestedPropertyTraitHandlesStringIntermediateWithoutIsADeprecation(): void
    {
        $obj = new class {
            use NestedPropertyTrait;

            private array $props = [
                'scalar' => 'hello',
                'nested' => ['key' => 'value'],
                'arrayAccess' => null,
            ];

            public function __construct()
            {
                $this->props['arrayAccess'] = new ArrayObject(['a' => 'b']);
            }

            public function hasProperty($property)
            {
                return array_key_exists($property, $this->props);
            }

            public function getProperty($property, $default = null)
            {
                return $this->props[$property] ?? $default;
            }

            public function setProperty($property, $value)
            {
                $this->props[$property] = $value;
                return $this;
            }

            public function unsetProperty($property)
            {
                unset($this->props[$property]);
                return $this;
            }

            public function defProperty($property, $default)
            {
                return $this;
            }
        };

        $this->assertNoDeprecation(function () use ($obj): void {
            $this->assertSame('fallback', $obj->getNestedProperty('scalar.child', 'fallback'));
            $this->assertSame('value', $obj->getNestedProperty('nested.key'));
            $this->assertSame('b', $obj->getNestedProperty('arrayAccess.a'));
        });
    }

    public function testClosureJobIdUsesNonNumericSplObjectIdWithoutDeprecation(): void
    {
        $closure = static fn () => null;
        $job = $this->assertNoDeprecation(static fn () => new Job($closure));

        $id = $job->getId();
        $this->assertSame('closure-' . spl_object_id($closure), $id);
        $this->assertFalse(is_numeric($id), 'Job id must not be a numeric string key that array_merge() would renumber');
    }

    public function testObjectTraitDefaultKeyUsesSplObjectIdWithoutDeprecation(): void
    {
        $obj = new class {
            use ObjectTrait;
        };

        $key = $this->assertNoDeprecation(static fn () => $obj->getKey());
        $this->assertSame($obj->getType() . '@@' . spl_object_id($obj), $key);
    }

    public function testDebuggerCredentialsIgnoresNulBytesInRawBody(): void
    {
        $request = (new ServerRequest('POST', '/__clockwork/auth'))
            ->withBody(Stream::create("username=admin\0&password=secret"));

        $method = new ReflectionMethod(Debugger::class, 'debuggerCredentials');

        $this->assertSame(['username' => '', 'password' => ''], $method->invoke(new Debugger(), $request));
    }

    public function testUriFactoryParseQueryHandlesNulBytesSafely(): void
    {
        $this->assertSame([], UriFactory::parseQuery("foo=bar\0baz=1"));
        $this->assertSame(['foo' => 'bar'], UriFactory::parseQuery('foo=bar'));
    }

    public function testUriInitHandlesNulBytesInQuerySafely(): void
    {
        $uri = new Uri();
        $uri->initializeWithUrl('http://example.com/page?_url=/test&a=1' . "\0" . 'b=2');

        $this->assertIsArray($uri->query(null, true));
    }

    public function testUriPathExistsAndConvertUrlHandleNulBytesSafely(): void
    {
        $pathExists = new ReflectionMethod(Uri::class, 'pathExists');
        $this->assertFalse($pathExists->invoke(null, "/tmp/foo\0bar.md"));

        $grav = Fixtures::get('grav');
        $grav();
        $page = new Page();
        $page->filePath(__FILE__);
        $page->route('/test');

        $converted = Uri::convertUrl($page, '../foo%00.md');
        $this->assertIsString($converted);
    }

    public function testRequestServiceProviderHandlesNulBytesInQueryString(): void
    {
        $savedServer = $_SERVER;
        $savedGet = $_GET;
        $savedPost = $_POST;
        $savedCookie = $_COOKIE;
        $savedFiles = $_FILES;

        try {
            $_SERVER = [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/page?_url=/page&a=1' . "\0" . 'b=2',
                'QUERY_STRING' => '_url=/page&a=1' . "\0" . 'b=2',
                'HTTP_HOST' => 'example.com',
                'SERVER_PORT' => '80',
            ];
            $_GET = [];
            $_POST = [];
            $_COOKIE = [];
            $_FILES = [];

            $container = new Container();
            (new RequestServiceProvider())->register($container);

            $request = $container['request'];
            $this->assertSame('/page', $request->getUri()->getPath());
        } finally {
            $_SERVER = $savedServer;
            $_GET = $savedGet;
            $_POST = $savedPost;
            $_COOKIE = $savedCookie;
            $_FILES = $savedFiles;
        }
    }

    public function testPageTaxonomyNormalizesObjectsAndNonArraysWithoutDeprecation(): void
    {
        $page = new Page();

        $this->assertNoDeprecation(function () use ($page): void {
            $page->taxonomy(['tag' => 'news']);
            $this->assertSame(['tag' => ['news']], $page->taxonomy());

            $obj = (object) ['tag' => ['news', 42]];
            $this->assertSame(['tag' => ['news', '42']], $page->taxonomy($obj));

            $this->assertSame([], $page->taxonomy('invalid'));
        });
    }

    public function testRegexFilterReturnsEmptyArrayOnPcreError(): void
    {
        $ext = new GravExtension();
        // Invalid UTF-8 subject with /u triggers a PCRE execution error (preg_grep returns false in PHP 8.6)
        $result = @$ext->regexFilter(["\x80\x81"], '/.*/u');

        $this->assertSame([], $result);
    }

    public function testUtilsGetMimeByLocalFileDoesNotEmitDeprecation(): void
    {
        $mime = $this->assertNoDeprecation(static fn () => Utils::getMimeByLocalFile(__FILE__));
        $this->assertIsString($mime);
        $this->assertNotSame('', $mime);
    }

    public function testCollatorFallbackHandlesInvalidUtf8(): void
    {
        if (!extension_loaded('intl')) {
            $this->markTestSkipped('ext-intl is not loaded');
        }

        $grav = Fixtures::get('grav');
        $grav();
        $instance = Grav::instance();
        $prevIntl = $instance['config']->get('system.intl_enabled');
        $instance['config']->set('system.intl_enabled', true);

        try {
            $collection = new \Grav\Common\Page\Collection([
                '/tmp/p1' => ['title' => "b\x80invalid"],
                '/tmp/p2' => ['title' => "a\x80invalid"],
            ]);

            /** @var Pages $pages */
            $pages = $instance['pages'];
            $sorted = $pages->sortCollection($collection, 'title', 'asc');
            $this->assertSame(['/tmp/p2', '/tmp/p1'], array_keys($sorted));
        } finally {
            $instance['config']->set('system.intl_enabled', $prevIntl);
        }
    }
}
