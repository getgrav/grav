<?php

use Codeception\Util\Fixtures;
use Grav\Common\Flex\Types\Pages\Storage\PageStorage;
use Grav\Common\Grav;
use Grav\Common\Page\PageOrdering;

/**
 * Regression tests for the page-folder order-prefix machinery.
 *
 * Covers the bug reported in getgrav/grav-plugin-admin#2492: a page folder
 * named "005.test" (3-digit prefix) was being silently renamed to "05.test"
 * after editing+saving in admin, which under flex pages produced a duplicate
 * page rather than an in-place rename.
 *
 * @see \Grav\Common\Page\PageOrdering
 * @see \Grav\Common\Flex\Types\Pages\Storage\PageStorage
 */
class PageOrderingTest extends \PHPUnit\Framework\TestCase
{
    /** @var Grav */
    protected $grav;

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $this->grav = $grav();
        // Force the configured-default cache to re-resolve per test in case
        // an earlier test in the suite mutated `system.pages.order_digits`.
        PageOrdering::resetCache();
    }

    protected function tearDown(): void
    {
        $this->grav['config']->set('system.pages.order_digits', null);
        PageOrdering::resetCache();
    }

    // ---------------------------------------------------------------------
    // PageOrdering helper — formatter
    // ---------------------------------------------------------------------

    public function testPrefixDefaultIsTwoDigits(): void
    {
        self::assertSame('05.', PageOrdering::prefix(5));
        self::assertSame('99.', PageOrdering::prefix(99));
    }

    public function testPrefixWidthOverride(): void
    {
        self::assertSame('005.', PageOrdering::prefix(5, 3));
        self::assertSame('5.',   PageOrdering::prefix(5, 1));
        self::assertSame('0005.', PageOrdering::prefix(5, 4));
    }

    public function testPrefixAutoGrowsForLargerValues(): void
    {
        // A value wider than the requested width must never be truncated.
        self::assertSame('123.', PageOrdering::prefix(123, 2));
        self::assertSame('1000.', PageOrdering::prefix(1000, 3));
    }

    public function testPrefixShortCircuitsForEmptyValues(): void
    {
        self::assertSame('', PageOrdering::prefix(0));
        self::assertSame('', PageOrdering::prefix('0'));
        self::assertSame('', PageOrdering::prefix(null));
        self::assertSame('', PageOrdering::prefix(false));
        self::assertSame('', PageOrdering::prefix(''));
    }

    public function testPrefixClampsDigitsToValidRange(): void
    {
        self::assertSame('5.',     PageOrdering::prefix(5, 0));
        self::assertSame('5.',     PageOrdering::prefix(5, -3));
        self::assertSame('000005.', PageOrdering::prefix(5, 99));
    }

    public function testKeyComposesPrefixAndFolder(): void
    {
        self::assertSame('05.foo', PageOrdering::key(5, 'foo'));
        self::assertSame('005.foo', PageOrdering::key(5, 'foo', 3));
        self::assertSame('foo', PageOrdering::key(0, 'foo'));
        self::assertSame('foo', PageOrdering::key(null, 'foo'));
    }

    // ---------------------------------------------------------------------
    // PageOrdering helper — parser
    // ---------------------------------------------------------------------

    public function testParseExtractsOrderAndDigits(): void
    {
        self::assertSame([5, 'test', 3], PageOrdering::parse('005.test'));
        self::assertSame([5, 'test', 2], PageOrdering::parse('05.test'));
        self::assertSame([5, 'test', 1], PageOrdering::parse('5.test'));
        self::assertSame([1234, 'long', 4], PageOrdering::parse('1234.long'));
    }

    public function testParseReturnsNullsForUnprefixedFolders(): void
    {
        self::assertSame([null, 'home', null], PageOrdering::parse('home'));
        self::assertSame([null, '', null], PageOrdering::parse(''));
        self::assertSame([null, '.hidden', null], PageOrdering::parse('.hidden'));
    }

    public function testDigitsFromFolder(): void
    {
        self::assertSame(3, PageOrdering::digitsFromFolder('005.test'));
        self::assertSame(2, PageOrdering::digitsFromFolder('05.test'));
        self::assertNull(PageOrdering::digitsFromFolder('home'));
        self::assertNull(PageOrdering::digitsFromFolder(null));
        self::assertNull(PageOrdering::digitsFromFolder(''));
    }

    // ---------------------------------------------------------------------
    // Configured default — `system.pages.order_digits`
    // ---------------------------------------------------------------------

    public function testDefaultDigitsHonorsSystemConfig(): void
    {
        $this->grav['config']->set('system.pages.order_digits', 3);
        PageOrdering::resetCache();

        self::assertSame(3, PageOrdering::defaultDigits());
        self::assertSame('005.', PageOrdering::prefix(5));
        self::assertSame('005.test', PageOrdering::key(5, 'test'));
    }

    public function testDefaultDigitsClampsOutOfRangeConfig(): void
    {
        $this->grav['config']->set('system.pages.order_digits', 99);
        PageOrdering::resetCache();
        self::assertSame(PageOrdering::MAX_DIGITS, PageOrdering::defaultDigits());

        $this->grav['config']->set('system.pages.order_digits', 0);
        PageOrdering::resetCache();
        self::assertSame(PageOrdering::MIN_DIGITS, PageOrdering::defaultDigits());
    }

    // ---------------------------------------------------------------------
    // Regression — extractKeysFromStorageKey / buildStorageKey round-trip
    //
    // This is the actual bug from getgrav/grav-plugin-admin#2492:
    // load "005.test" → extract → rebuild → must remain "005.test".
    // ---------------------------------------------------------------------

    public function testStorageKeyRoundTripPreservesThreeDigitPrefix(): void
    {
        $storage = $this->makePageStorage();

        $keys = $storage->extractKeysFromStorageKey('005.test');
        self::assertSame(5, $keys['order']);
        self::assertSame(3, $keys['order_digits']);
        self::assertSame('test', $keys['folder']);

        // When rebuilding from scratch (no pre-built `key`), the digit width
        // recorded at extract time must round-trip.
        unset($keys['key']);
        $rebuilt = $storage->buildStorageKey($keys, false);
        self::assertSame('005.test', $rebuilt);
    }

    public function testStorageKeyRoundTripPreservesTwoDigitPrefix(): void
    {
        $storage = $this->makePageStorage();

        $keys = $storage->extractKeysFromStorageKey('05.test');
        self::assertSame(2, $keys['order_digits']);

        unset($keys['key']);
        self::assertSame('05.test', $storage->buildStorageKey($keys, false));
    }

    public function testStorageKeyRoundTripWithoutOrderPrefix(): void
    {
        $storage = $this->makePageStorage();

        $keys = $storage->extractKeysFromStorageKey('home');
        self::assertNull($keys['order']);
        self::assertNull($keys['order_digits']);

        unset($keys['key']);
        self::assertSame('home', $storage->buildStorageKey($keys, false));
    }

    public function testStorageKeyFallsBackToConfiguredDefaultWhenDigitsMissing(): void
    {
        $storage = $this->makePageStorage();

        // Caller knows order+folder but not the original digit width
        // (e.g. a freshly created page). Width follows config default.
        $rebuilt = $storage->buildStorageKey([
            'parent_key' => '',
            'order' => 5,
            'folder' => 'test',
            'template' => '',
            'lang' => '',
        ], false);
        self::assertSame('05.test', $rebuilt);

        $this->grav['config']->set('system.pages.order_digits', 3);
        PageOrdering::resetCache();

        $rebuilt = $storage->buildStorageKey([
            'parent_key' => '',
            'order' => 5,
            'folder' => 'test',
            'template' => '',
            'lang' => '',
        ], false);
        self::assertSame('005.test', $rebuilt);
    }

    /**
     * Construct a PageStorage with the minimum viable options. We never call
     * disk-touching methods in these tests; only key-string transforms.
     */
    private function makePageStorage(): PageStorage
    {
        return new PageStorage([
            'folder' => sys_get_temp_dir(),
            'formatter' => ['file_extension' => '.md'],
        ]);
    }
}
