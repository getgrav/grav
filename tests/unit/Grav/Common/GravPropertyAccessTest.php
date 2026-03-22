<?php

namespace Grav\Common;

use Codeception\Util\Fixtures;
use Grav\Common\Assets;
use Grav\Common\Cache;
use Grav\Common\Config\Config;
use Grav\Common\Config\Languages;
use Grav\Common\Inflector;
use Grav\Common\Language\Language;
use Grav\Common\Page\Pages;
use Grav\Common\Uri;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Tests for Grav container property access via @property-read annotations
 * and __get() magic method.
 *
 * These tests verify that the @property-read annotations on the Grav class
 * correctly document the types returned by property access.
 */
class GravPropertyAccessTest extends TestCase
{
    /** @var Grav */
    protected $grav;

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $this->grav = $grav();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    // ============================================================
    // Core Service Property Access Tests
    // ============================================================

    public function testConfigPropertyAccess(): void
    {
        $config = $this->grav->config;

        self::assertInstanceOf(Config::class, $config);
        self::assertTrue(is_array($config->get('system')) || $config->get('system') === null);
    }

    public function testConfigArrayAccessEquivalence(): void
    {
        $viaProperty = $this->grav->config;
        $viaArray = $this->grav['config'];

        self::assertSame($viaProperty, $viaArray);
    }

    public function testPagesPropertyAccess(): void
    {
        $pages = $this->grav->pages;

        self::assertInstanceOf(Pages::class, $pages);
    }

    public function testPagesArrayAccessEquivalence(): void
    {
        $viaProperty = $this->grav->pages;
        $viaArray = $this->grav['pages'];

        self::assertSame($viaProperty, $viaArray);
    }

    public function testAssetsPropertyAccess(): void
    {
        $assets = $this->grav->assets;

        self::assertInstanceOf(Assets::class, $assets);
    }

    public function testAssetsArrayAccessEquivalence(): void
    {
        $viaProperty = $this->grav->assets;
        $viaArray = $this->grav['assets'];

        self::assertSame($viaProperty, $viaArray);
    }

    public function testUriPropertyAccess(): void
    {
        $uri = $this->grav->uri;

        self::assertInstanceOf(Uri::class, $uri);
    }

    public function testUriArrayAccessEquivalence(): void
    {
        $viaProperty = $this->grav->uri;
        $viaArray = $this->grav['uri'];

        self::assertSame($viaProperty, $viaArray);
    }

    public function testLogPropertyAccess(): void
    {
        $log = $this->grav->log;

        self::assertInstanceOf(Logger::class, $log);
    }

    public function testLogArrayAccessEquivalence(): void
    {
        $viaProperty = $this->grav->log;
        $viaArray = $this->grav['log'];

        self::assertSame($viaProperty, $viaArray);
    }

    public function testEventsPropertyAccess(): void
    {
        $events = $this->grav->events;

        self::assertInstanceOf(EventDispatcher::class, $events);
    }

    public function testEventsArrayAccessEquivalence(): void
    {
        $viaProperty = $this->grav->events;
        $viaArray = $this->grav['events'];

        self::assertSame($viaProperty, $viaArray);
    }

    public function testCachePropertyAccess(): void
    {
        $cache = $this->grav->cache;

        self::assertInstanceOf(Cache::class, $cache);
    }

    public function testCacheArrayAccessEquivalence(): void
    {
        $viaProperty = $this->grav->cache;
        $viaArray = $this->grav['cache'];

        self::assertSame($viaProperty, $viaArray);
    }

    public function testInflectorPropertyAccess(): void
    {
        $inflector = $this->grav->inflector;

        self::assertInstanceOf(Inflector::class, $inflector);
    }

    public function testInflectorArrayAccessEquivalence(): void
    {
        $viaProperty = $this->grav->inflector;
        $viaArray = $this->grav['inflector'];

        self::assertSame($viaProperty, $viaArray);
    }

    public function testLocatorPropertyAccess(): void
    {
        $locator = $this->grav->locator;

        self::assertInstanceOf(UniformResourceLocator::class, $locator);
    }

    public function testLocatorArrayAccessEquivalence(): void
    {
        $viaProperty = $this->grav->locator;
        $viaArray = $this->grav['locator'];

        self::assertSame($viaProperty, $viaArray);
    }

    public function testLanguagePropertyAccess(): void
    {
        $language = $this->grav->language;

        self::assertInstanceOf(Language::class, $language);
    }

    public function testLanguageArrayAccessEquivalence(): void
    {
        $viaProperty = $this->grav->language;
        $viaArray = $this->grav['language'];

        self::assertSame($viaProperty, $viaArray);
    }

    public function testLanguagesPropertyAccess(): void
    {
        $languages = $this->grav->languages;

        self::assertInstanceOf(Languages::class, $languages);
    }

    public function testLanguagesArrayAccessEquivalence(): void
    {
        $viaProperty = $this->grav->languages;
        $viaArray = $this->grav['languages'];

        self::assertSame($viaProperty, $viaArray);
    }

    // ============================================================
    // __isset() Magic Method Tests
    // ============================================================

    public function testIssetReturnsTrueForExistingService(): void
    {
        self::assertTrue(isset($this->grav->config));
        self::assertTrue(isset($this->grav->pages));
        self::assertTrue(isset($this->grav->assets));
        self::assertTrue(isset($this->grav->uri));
        self::assertTrue(isset($this->grav->log));
        self::assertTrue(isset($this->grav->events));
        self::assertTrue(isset($this->grav->cache));
        self::assertTrue(isset($this->grav->locator));
        self::assertTrue(isset($this->grav->inflector));
        self::assertTrue(isset($this->grav->language));
        self::assertTrue(isset($this->grav->languages));
    }

    public function testIssetReturnsFalseForNonExistentService(): void
    {
        self::assertFalse(isset($this->grav->nonexistent_service));
        self::assertFalse(isset($this->grav->fake_service));
    }

    // ============================================================
    // Chained Access Tests
    // ============================================================

    public function testChainedConfigGet(): void
    {
        // Config should be accessible via property and have a get method
        $config = $this->grav->config;
        
        self::assertInstanceOf(Config::class, $config);
        self::assertTrue(method_exists($config, 'get'));
        
        // Config should be able to retrieve values (returns null if key doesn't exist)
        $value = $config->get('nonexistent.key', 'default');
        self::assertEquals('default', $value);
    }

    public function testChainedPagesMethod(): void
    {
        // Pages should be accessible via property
        $pages = $this->grav->pages;
        
        self::assertInstanceOf(Pages::class, $pages);
        self::assertTrue(method_exists($pages, 'root'));
        self::assertTrue(method_exists($pages, 'find'));
    }

    public function testChainedUriMethod(): void
    {
        $path = $this->grav->uri->path();

        // Path should be a string
        self::assertIsString($path);
    }

    public function testChainedLogMethod(): void
    {
        // Logger should have standard methods
        self::assertTrue(method_exists($this->grav->log, 'info'));
        self::assertTrue(method_exists($this->grav->log, 'error'));
        self::assertTrue(method_exists($this->grav->log, 'warning'));
        self::assertTrue(method_exists($this->grav->log, 'debug'));
    }

    public function testChainedAssetsMethod(): void
    {
        // Assets should have standard methods
        self::assertTrue(method_exists($this->grav->assets, 'addCss'));
        self::assertTrue(method_exists($this->grav->assets, 'addJs'));
        self::assertTrue(method_exists($this->grav->assets, 'addInlineCss'));
        self::assertTrue(method_exists($this->grav->assets, 'addInlineJs'));
    }

    public function testChainedEventsMethod(): void
    {
        // EventDispatcher should have standard methods
        self::assertTrue(method_exists($this->grav->events, 'dispatch'));
        self::assertTrue(method_exists($this->grav->events, 'addListener'));
        self::assertTrue(method_exists($this->grav->events, 'addSubscriber'));
    }

    // ============================================================
    // Lazy Service Resolution Tests
    // ============================================================

    public function testServiceIsResolvedLazily(): void
    {
        // Get fresh Grav instance
        Grav::resetInstance();
        $grav = Grav::instance();

        // Config is lazy - accessing it should resolve the closure
        $config1 = $grav->config;
        $config2 = $grav->config;

        // Should return the same instance (singleton behavior)
        self::assertSame($config1, $config2);
    }

    // ============================================================
    // Property Access vs Array Access Performance Tests
    // ============================================================

    public function testPropertyAccessDoesNotCreateDuplicateInstances(): void
    {
        // Access same service via property and array multiple times
        $viaProperty1 = $this->grav->config;
        $viaArray1 = $this->grav['config'];
        $viaProperty2 = $this->grav->config;
        $viaArray2 = $this->grav['config'];

        // All should be the same instance
        self::assertSame($viaProperty1, $viaArray1);
        self::assertSame($viaProperty1, $viaProperty2);
        self::assertSame($viaProperty1, $viaArray2);
    }

    // ============================================================
    // Edge Cases
    // ============================================================

    public function testExceptionForNonExistentService(): void
    {
        $this->expectException(\Pimple\Exception\UnknownIdentifierException::class);

        $value = $this->grav->completely_fake_service_that_does_not_exist;
    }

    public function testIssetReturnsTrueForAllAnnotatedProperties(): void
    {
        // All @property-read annotated services should exist
        $annotatedServices = [
            'config', 'pages', 'assets', 'uri', 'log', 'events',
            'cache', 'locator', 'inflector', 'language', 'languages'
        ];

        foreach ($annotatedServices as $service) {
            self::assertTrue(
                isset($this->grav->$service),
                "Service '{$service}' should exist (annotated with @property-read)"
            );
        }
    }

    public function testPropertyAccessReturnsCorrectTypes(): void
    {
        // Verify that the actual types match the @property-read annotations
        self::assertInstanceOf(Config::class, $this->grav->config);
        self::assertInstanceOf(Pages::class, $this->grav->pages);
        self::assertInstanceOf(Assets::class, $this->grav->assets);
        self::assertInstanceOf(Uri::class, $this->grav->uri);
        self::assertInstanceOf(Logger::class, $this->grav->log);
        self::assertInstanceOf(EventDispatcher::class, $this->grav->events);
        self::assertInstanceOf(Cache::class, $this->grav->cache);
        self::assertInstanceOf(UniformResourceLocator::class, $this->grav->locator);
        self::assertInstanceOf(Inflector::class, $this->grav->inflector);
        self::assertInstanceOf(Language::class, $this->grav->language);
        self::assertInstanceOf(Languages::class, $this->grav->languages);
    }

    public function testPropertyAccessWithDynamicService(): void
    {
        // Add a dynamic service and access it via property
        $this->grav['dynamic_test'] = function () {
            $obj = new \stdClass();
            $obj->name = 'dynamic';
            return $obj;
        };

        self::assertInstanceOf(\stdClass::class, $this->grav->dynamic_test);
        self::assertEquals('dynamic', $this->grav->dynamic_test->name);
    }

    public function testPropertyAccessWithClosureDependencyInjection(): void
    {
        // Service that depends on another service
        $this->grav['dependent_service'] = function ($c) {
            // Use a config key that returns a string (home.alias is always '/home')
            return 'depends-on-' . $c['config']->get('home.alias', '/home');
        };

        $result = $this->grav->dependent_service;

        self::assertIsString($result);
        self::assertStringStartsWith('depends-on-', $result);
        self::assertStringContainsString('/home', $result);
    }
}
