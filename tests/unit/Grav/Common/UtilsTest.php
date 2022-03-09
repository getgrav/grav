<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Common\Uri;
use Grav\Common\Utils;

/**
 * Class UtilsTest
 */
class UtilsTest extends \Codeception\TestCase\Test
{
    /** @var Grav $grav */
    protected $grav;

    /** @var Uri $uri */
    protected $uri;

    protected function _before(): void
    {
        $grav = Fixtures::get('grav');
        $this->grav = $grav();
        $this->uri = $this->grav['uri'];
    }

    protected function _after(): void
    {
    }

    public function testStartsWith(): void
    {
        self::assertTrue(Utils::startsWith('english', 'en'));
        self::assertTrue(Utils::startsWith('English', 'En'));
        self::assertTrue(Utils::startsWith('ENGLISH', 'EN'));
        self::assertTrue(Utils::startsWith(
            'ENGLISHENGLISHENGLISHENGLISHENGLISHENGLISHENGLISHENGLISHENGLISHENGLISH',
            'EN'
        ));

        self::assertFalse(Utils::startsWith('english', 'En'));
        self::assertFalse(Utils::startsWith('English', 'EN'));
        self::assertFalse(Utils::startsWith('ENGLISH', 'en'));
        self::assertFalse(Utils::startsWith(
            'ENGLISHENGLISHENGLISHENGLISHENGLISHENGLISHENGLISHENGLISHENGLISHENGLISH',
            'e'
        ));

        self::assertTrue(Utils::startsWith('english', 'En', false));
        self::assertTrue(Utils::startsWith('English', 'EN', false));
        self::assertTrue(Utils::startsWith('ENGLISH', 'en', false));
        self::assertTrue(Utils::startsWith(
            'ENGLISHENGLISHENGLISHENGLISHENGLISHENGLISHENGLISHENGLISHENGLISHENGLISH',
            'e',
            false
        ));
    }

    public function testEndsWith(): void
    {
        self::assertTrue(Utils::endsWith('english', 'sh'));
        self::assertTrue(Utils::endsWith('EngliSh', 'Sh'));
        self::assertTrue(Utils::endsWith('ENGLISH', 'SH'));
        self::assertTrue(Utils::endsWith(
            'ENGLISHENGLISHENGLISHENGLISHENGLISHENGLISHENGLISHENGLISHENGLISHENGLISH',
            'ENGLISH'
        ));

        self::assertFalse(Utils::endsWith('english', 'de'));
        self::assertFalse(Utils::endsWith('EngliSh', 'sh'));
        self::assertFalse(Utils::endsWith('ENGLISH', 'Sh'));
        self::assertFalse(Utils::endsWith(
            'ENGLISHENGLISHENGLISHENGLISHENGLISHENGLISHENGLISHENGLISHENGLISHENGLISH',
            'DEUSTCH'
        ));

        self::assertTrue(Utils::endsWith('english', 'SH', false));
        self::assertTrue(Utils::endsWith('EngliSh', 'sH', false));
        self::assertTrue(Utils::endsWith('ENGLISH', 'sh', false));
        self::assertTrue(Utils::endsWith(
            'ENGLISHENGLISHENGLISHENGLISHENGLISHENGLISHENGLISHENGLISHENGLISHENGLISH',
            'english',
            false
        ));
    }

    public function testContains(): void
    {
        self::assertTrue(Utils::contains('english', 'nglis'));
        self::assertTrue(Utils::contains('EngliSh', 'gliSh'));
        self::assertTrue(Utils::contains('ENGLISH', 'ENGLI'));
        self::assertTrue(Utils::contains(
            'ENGLISHENGLISHENGLISHENGLISHENGLISHENGLISHENGLISHENGLISHENGLISHENGLISH',
            'ENGLISH'
        ));

        self::assertFalse(Utils::contains('EngliSh', 'GLI'));
        self::assertFalse(Utils::contains('EngliSh', 'English'));
        self::assertFalse(Utils::contains('ENGLISH', 'SCH'));
        self::assertFalse(Utils::contains(
            'ENGLISHENGLISHENGLISHENGLISHENGLISHENGLISHENGLISHENGLISHENGLISHENGLISH',
            'DEUSTCH'
        ));

        self::assertTrue(Utils::contains('EngliSh', 'GLI', false));
        self::assertTrue(Utils::contains('EngliSh', 'ENGLISH', false));
        self::assertTrue(Utils::contains('ENGLISH', 'ish', false));
        self::assertTrue(Utils::contains(
            'ENGLISHENGLISHENGLISHENGLISHENGLISHENGLISHENGLISHENGLISHENGLISHENGLISH',
            'english',
            false
        ));
    }

    public function testSubstrToString(): void
    {
        self::assertEquals('en', Utils::substrToString('english', 'glish'));
        self::assertEquals('english', Utils::substrToString('english', 'test'));
        self::assertNotEquals('en', Utils::substrToString('english', 'lish'));

        self::assertEquals('en', Utils::substrToString('english', 'GLISH', false));
        self::assertEquals('english', Utils::substrToString('english', 'TEST', false));
        self::assertNotEquals('en', Utils::substrToString('english', 'LISH', false));
    }

    public function testMergeObjects(): void
    {
        $obj1 = new stdClass();
        $obj1->test1 = 'x';
        $obj2 = new stdClass();
        $obj2->test2 = 'y';

        $objMerged = Utils::mergeObjects($obj1, $obj2);

        self::assertObjectHasAttribute('test1', $objMerged);
        self::assertObjectHasAttribute('test2', $objMerged);
    }

    public function testDateFormats(): void
    {
        $dateFormats = Utils::dateFormats();
        self::assertIsArray($dateFormats);
        self::assertContainsOnly('string', $dateFormats);

        $default_format = $this->grav['config']->get('system.pages.dateformat.default');

        if ($default_format !== null) {
            self::assertArrayHasKey($default_format, $dateFormats);
        }
    }

    public function testTruncate(): void
    {
        self::assertEquals('engli' . '&hellip;', Utils::truncate('english', 5));
        self::assertEquals('english', Utils::truncate('english'));
        self::assertEquals('This is a string to truncate', Utils::truncate('This is a string to truncate'));
        self::assertEquals('Th' . '&hellip;', Utils::truncate('This is a string to truncate', 2));
        self::assertEquals('engli' . '...', Utils::truncate('english', 5, true, " ", "..."));
        self::assertEquals('english', Utils::truncate('english'));
        self::assertEquals('This is a string to truncate', Utils::truncate('This is a string to truncate'));
        self::assertEquals('This' . '&hellip;', Utils::truncate('This is a string to truncate', 3, true));
        self::assertEquals('<input' . '&hellip;', Utils::truncate('<input type="file" id="file" multiple />', 6, true));
    }

    public function testSafeTruncate(): void
    {
        self::assertEquals('This' . '&hellip;', Utils::safeTruncate('This is a string to truncate', 1));
        self::assertEquals('This' . '&hellip;', Utils::safeTruncate('This is a string to truncate', 4));
        self::assertEquals('This is' . '&hellip;', Utils::safeTruncate('This is a string to truncate', 5));
    }

    public function testTruncateHtml(): void
    {
        self::assertEquals('T...', Utils::truncateHtml('This is a string to truncate', 1));
        self::assertEquals('This is...', Utils::truncateHtml('This is a string to truncate', 7));
        self::assertEquals('<p>T...</p>', Utils::truncateHtml('<p>This is a string to truncate</p>', 1));
        self::assertEquals('<p>This...</p>', Utils::truncateHtml('<p>This is a string to truncate</p>', 4));
        self::assertEquals('<p>This is a...</p>', Utils::truncateHtml('<p>This is a string to truncate</p>', 10));
        self::assertEquals('<p>This is a string to truncate</p>', Utils::truncateHtml('<p>This is a string to truncate</p>', 100));
        self::assertEquals('<input type="file" id="file" multiple />', Utils::truncateHtml('<input type="file" id="file" multiple />', 6));
        self::assertEquals('<ol><li>item 1 <i>so...</i></li></ol>', Utils::truncateHtml('<ol><li>item 1 <i>something</i></li><li>item 2 <strong>bold</strong></li></ol>', 10));
        self::assertEquals("<p>This is a string.</p>\n<p>It splits two lines.</p>", Utils::truncateHtml("<p>This is a string.</p>\n<p>It splits two lines.</p>", 100));
    }

    public function testSafeTruncateHtml(): void
    {
        self::assertEquals('This...', Utils::safeTruncateHtml('This is a string to truncate', 1));
        self::assertEquals('This is a...', Utils::safeTruncateHtml('This is a string to truncate', 3));
        self::assertEquals('<p>This...</p>', Utils::safeTruncateHtml('<p>This is a string to truncate</p>', 1));
        self::assertEquals('<p>This is...</p>', Utils::safeTruncateHtml('<p>This is a string to truncate</p>', 2));
        self::assertEquals('<p>This is a string to...</p>', Utils::safeTruncateHtml('<p>This is a string to truncate</p>', 5));
        self::assertEquals('<p>This is a string to truncate</p>', Utils::safeTruncateHtml('<p>This is a string to truncate</p>', 20));
        self::assertEquals('<input type="file" id="file" multiple />', Utils::safeTruncateHtml('<input type="file" id="file" multiple />', 6));
        self::assertEquals('<ol><li>item 1 <i>something</i></li><li>item 2...</li></ol>', Utils::safeTruncateHtml('<ol><li>item 1 <i>something</i></li><li>item 2 <strong>bold</strong></li></ol>', 5));
    }

    public function testGenerateRandomString(): void
    {
        self::assertNotEquals(Utils::generateRandomString(), Utils::generateRandomString());
        self::assertNotEquals(Utils::generateRandomString(20), Utils::generateRandomString(20));
    }

    public function download(): void
    {
    }

    public function testGetMimeByExtension(): void
    {
        self::assertEquals('application/octet-stream', Utils::getMimeByExtension(''));
        self::assertEquals('text/html', Utils::getMimeByExtension('html'));
        self::assertEquals('application/json', Utils::getMimeByExtension('json'));
        self::assertEquals('application/atom+xml', Utils::getMimeByExtension('atom'));
        self::assertEquals('application/rss+xml', Utils::getMimeByExtension('rss'));
        self::assertEquals('image/jpeg', Utils::getMimeByExtension('jpg'));
        self::assertEquals('image/png', Utils::getMimeByExtension('png'));
        self::assertEquals('text/plain', Utils::getMimeByExtension('txt'));
        self::assertEquals('application/msword', Utils::getMimeByExtension('doc'));
        self::assertEquals('application/octet-stream', Utils::getMimeByExtension('foo'));
        self::assertEquals('foo/bar', Utils::getMimeByExtension('foo', 'foo/bar'));
        self::assertEquals('text/html', Utils::getMimeByExtension('foo', 'text/html'));
    }

    public function testGetExtensionByMime(): void
    {
        self::assertEquals('html', Utils::getExtensionByMime('*/*'));
        self::assertEquals('html', Utils::getExtensionByMime('text/*'));
        self::assertEquals('html', Utils::getExtensionByMime('text/html'));
        self::assertEquals('json', Utils::getExtensionByMime('application/json'));
        self::assertEquals('atom', Utils::getExtensionByMime('application/atom+xml'));
        self::assertEquals('rss', Utils::getExtensionByMime('application/rss+xml'));
        self::assertEquals('jpg', Utils::getExtensionByMime('image/jpeg'));
        self::assertEquals('png', Utils::getExtensionByMime('image/png'));
        self::assertEquals('txt', Utils::getExtensionByMime('text/plain'));
        self::assertEquals('doc', Utils::getExtensionByMime('application/msword'));
        self::assertEquals('html', Utils::getExtensionByMime('foo/bar'));
        self::assertEquals('baz', Utils::getExtensionByMime('foo/bar', 'baz'));
    }

    public function testNormalizePath(): void
    {
        self::assertEquals('/test', Utils::normalizePath('/test'));
        self::assertEquals('test', Utils::normalizePath('test'));
        self::assertEquals('test', Utils::normalizePath('../test'));
        self::assertEquals('/test', Utils::normalizePath('/../test'));
        self::assertEquals('/test2', Utils::normalizePath('/test/../test2'));
        self::assertEquals('/test3', Utils::normalizePath('/test/../test2/../test3'));

        self::assertEquals('//cdnjs.cloudflare.com/ajax/libs/Leaflet.awesome-markers/2.0.2/leaflet.awesome-markers.css', Utils::normalizePath('//cdnjs.cloudflare.com/ajax/libs/Leaflet.awesome-markers/2.0.2/leaflet.awesome-markers.css'));
        self::assertEquals('//use.fontawesome.com/releases/v5.8.1/css/all.css', Utils::normalizePath('//use.fontawesome.com/releases/v5.8.1/css/all.css'));
        self::assertEquals('//use.fontawesome.com/releases/v5.8.1/webfonts/fa-brands-400.eot', Utils::normalizePath('//use.fontawesome.com/releases/v5.8.1/css/../webfonts/fa-brands-400.eot'));

        self::assertEquals('http://cdnjs.cloudflare.com/ajax/libs/Leaflet.awesome-markers/2.0.2/leaflet.awesome-markers.css', Utils::normalizePath('http://cdnjs.cloudflare.com/ajax/libs/Leaflet.awesome-markers/2.0.2/leaflet.awesome-markers.css'));
        self::assertEquals('http://use.fontawesome.com/releases/v5.8.1/css/all.css', Utils::normalizePath('http://use.fontawesome.com/releases/v5.8.1/css/all.css'));
        self::assertEquals('http://use.fontawesome.com/releases/v5.8.1/webfonts/fa-brands-400.eot', Utils::normalizePath('http://use.fontawesome.com/releases/v5.8.1/css/../webfonts/fa-brands-400.eot'));

        self::assertEquals('https://cdnjs.cloudflare.com/ajax/libs/Leaflet.awesome-markers/2.0.2/leaflet.awesome-markers.css', Utils::normalizePath('https://cdnjs.cloudflare.com/ajax/libs/Leaflet.awesome-markers/2.0.2/leaflet.awesome-markers.css'));
        self::assertEquals('https://use.fontawesome.com/releases/v5.8.1/css/all.css', Utils::normalizePath('https://use.fontawesome.com/releases/v5.8.1/css/all.css'));
        self::assertEquals('https://use.fontawesome.com/releases/v5.8.1/webfonts/fa-brands-400.eot', Utils::normalizePath('https://use.fontawesome.com/releases/v5.8.1/css/../webfonts/fa-brands-400.eot'));
    }

    public function testIsFunctionDisabled(): void
    {
        $disabledFunctions = explode(',', ini_get('disable_functions'));

        if ($disabledFunctions[0]) {
            self::assertEquals(Utils::isFunctionDisabled($disabledFunctions[0]), true);
        }
    }

    public function testTimezones(): void
    {
        $timezones = Utils::timezones();

        self::assertIsArray($timezones);
        self::assertContainsOnly('string', $timezones);
    }

    public function testArrayFilterRecursive(): void
    {
        $array = [
            'test'  => '',
            'test2' => 'test2'
        ];

        $array = Utils::arrayFilterRecursive($array, function ($k, $v) {
            return !(is_null($v) || $v === '');
        });

        self::assertContainsOnly('string', $array);
        self::assertArrayNotHasKey('test', $array);
        self::assertArrayHasKey('test2', $array);
        self::assertEquals('test2', $array['test2']);
    }

    public function testPathPrefixedByLangCode(): void
    {
        $languagesEnabled = $this->grav['config']->get('system.languages.supported', []);
        $arrayOfLanguages = ['en', 'de', 'it', 'es', 'dk', 'el'];
        $languagesNotEnabled = array_diff($arrayOfLanguages, $languagesEnabled);
        $oneLanguageNotEnabled = reset($languagesNotEnabled);

        if (count($languagesEnabled)) {
            $languageCodePathPrefix = Utils::pathPrefixedByLangCode('/' . $languagesEnabled[0] . '/test');
            $this->assertIsString($languageCodePathPrefix);
            $this->assertTrue(in_array($languageCodePathPrefix, $languagesEnabled));
        }

        self::assertFalse(Utils::pathPrefixedByLangCode('/' . $oneLanguageNotEnabled . '/test'));
        self::assertFalse(Utils::pathPrefixedByLangCode('/test'));
        self::assertFalse(Utils::pathPrefixedByLangCode('/xx'));
        self::assertFalse(Utils::pathPrefixedByLangCode('/xx/'));
        self::assertFalse(Utils::pathPrefixedByLangCode('/'));
    }

    public function testDate2timestamp(): void
    {
        $timestamp = strtotime('10 September 2000');
        self::assertSame($timestamp, Utils::date2timestamp('10 September 2000'));
        self::assertSame($timestamp, Utils::date2timestamp('2000-09-10 00:00:00'));
    }

    public function testResolve(): void
    {
        $array = [
            'test' => [
                'test2' => 'test2Value'
            ]
        ];

        self::assertEquals('test2Value', Utils::resolve($array, 'test.test2'));
    }

    public function testGetDotNotation(): void
    {
        $array = [
            'test' => [
                'test2' => 'test2Value',
                'test3' => [
                    'test4' => 'test4Value'
                ]
            ]
        ];

        self::assertEquals('test2Value', Utils::getDotNotation($array, 'test.test2'));
        self::assertEquals('test4Value', Utils::getDotNotation($array, 'test.test3.test4'));
        self::assertEquals('defaultValue', Utils::getDotNotation($array, 'test.non_existent', 'defaultValue'));
    }

    public function testSetDotNotation(): void
    {
        $array = [
            'test' => [
                'test2' => 'test2Value',
                'test3' => [
                    'test4' => 'test4Value'
                ]
            ]
        ];

        $new = [
            'test1' => 'test1Value'
        ];

        Utils::setDotNotation($array, 'test.test3.test4', $new);
        self::assertEquals('test1Value', $array['test']['test3']['test4']['test1']);
    }

    public function testIsPositive(): void
    {
        self::assertTrue(Utils::isPositive(true));
        self::assertTrue(Utils::isPositive(1));
        self::assertTrue(Utils::isPositive('1'));
        self::assertTrue(Utils::isPositive('yes'));
        self::assertTrue(Utils::isPositive('on'));
        self::assertTrue(Utils::isPositive('true'));
        self::assertFalse(Utils::isPositive(false));
        self::assertFalse(Utils::isPositive(0));
        self::assertFalse(Utils::isPositive('0'));
        self::assertFalse(Utils::isPositive('no'));
        self::assertFalse(Utils::isPositive('off'));
        self::assertFalse(Utils::isPositive('false'));
        self::assertFalse(Utils::isPositive('some'));
        self::assertFalse(Utils::isPositive(2));
    }

    public function testGetNonce(): void
    {
        self::assertIsString(Utils::getNonce('test-action'));
        self::assertIsString(Utils::getNonce('test-action', true));
        self::assertSame(Utils::getNonce('test-action'), Utils::getNonce('test-action'));
        self::assertNotSame(Utils::getNonce('test-action'), Utils::getNonce('test-action2'));
    }

    public function testVerifyNonce(): void
    {
        self::assertTrue(Utils::verifyNonce(Utils::getNonce('test-action'), 'test-action'));
    }

    public function testGetPagePathFromToken(): void
    {
        self::assertEquals('', Utils::getPagePathFromToken(''));
        self::assertEquals('/test/path', Utils::getPagePathFromToken('/test/path'));
    }

    public function testUrl(): void
    {
        $this->uri->initializeWithUrl('http://testing.dev/path1/path2')->init();

        // Fail hard
        self::assertSame(false, Utils::url('', true));
        self::assertSame(false, Utils::url(''));
        self::assertSame(false, Utils::url(new stdClass()));
        self::assertSame(false, Utils::url(['foo','bar','baz']));
        self::assertSame(false, Utils::url('user://does/not/exist'));

        // Fail Gracefully
        self::assertSame('/', Utils::url('/', false, true));
        self::assertSame('/', Utils::url('', false, true));
        self::assertSame('/', Utils::url(new stdClass(), false, true));
        self::assertSame('/', Utils::url(['foo','bar','baz'], false, true));
        self::assertSame('/user/does/not/exist', Utils::url('user://does/not/exist', false, true));

        // Simple paths
        self::assertSame('/', Utils::url('/'));
        self::assertSame('/path1', Utils::url('/path1'));
        self::assertSame('/path1/path2', Utils::url('/path1/path2'));
        self::assertSame('/random/path1/path2', Utils::url('/random/path1/path2'));
        self::assertSame('/foobar.jpg', Utils::url('/foobar.jpg'));
        self::assertSame('/path1/foobar.jpg', Utils::url('/path1/foobar.jpg'));
        self::assertSame('/path1/path2/foobar.jpg', Utils::url('/path1/path2/foobar.jpg'));
        self::assertSame('/random/path1/path2/foobar.jpg', Utils::url('/random/path1/path2/foobar.jpg'));

        // Simple paths with domain
        self::assertSame('http://testing.dev/', Utils::url('/', true));
        self::assertSame('http://testing.dev/path1', Utils::url('/path1', true));
        self::assertSame('http://testing.dev/path1/path2', Utils::url('/path1/path2', true));
        self::assertSame('http://testing.dev/random/path1/path2', Utils::url('/random/path1/path2', true));
        self::assertSame('http://testing.dev/foobar.jpg', Utils::url('/foobar.jpg', true));
        self::assertSame('http://testing.dev/path1/foobar.jpg', Utils::url('/path1/foobar.jpg', true));
        self::assertSame('http://testing.dev/path1/path2/foobar.jpg', Utils::url('/path1/path2/foobar.jpg', true));
        self::assertSame('http://testing.dev/random/path1/path2/foobar.jpg', Utils::url('/random/path1/path2/foobar.jpg', true));

        // Relative paths from Grav root.
        self::assertSame('/subdir', Utils::url('subdir'));
        self::assertSame('/subdir/path1', Utils::url('subdir/path1'));
        self::assertSame('/subdir/path1/path2', Utils::url('subdir/path1/path2'));
        self::assertSame('/path1', Utils::url('path1'));
        self::assertSame('/path1/path2', Utils::url('path1/path2'));
        self::assertSame('/foobar.jpg', Utils::url('foobar.jpg'));
        self::assertSame('http://testing.dev/foobar.jpg', Utils::url('foobar.jpg', true));

        // Relative paths from Grav root with domain.
        self::assertSame('http://testing.dev/foobar.jpg', Utils::url('foobar.jpg', true));
        self::assertSame('http://testing.dev/foobar.jpg', Utils::url('/foobar.jpg', true));
        self::assertSame('http://testing.dev/path1/foobar.jpg', Utils::url('/path1/foobar.jpg', true));

        // All Non-existing streams should be treated as external URI / protocol.
        self::assertSame('http://domain.com/path', Utils::url('http://domain.com/path'));
        self::assertSame('ftp://domain.com/path', Utils::url('ftp://domain.com/path'));
        self::assertSame('sftp://domain.com/path', Utils::url('sftp://domain.com/path'));
        self::assertSame('ssh://domain.com', Utils::url('ssh://domain.com'));
        self::assertSame('pop://domain.com', Utils::url('pop://domain.com'));
        self::assertSame('foo://bar/baz', Utils::url('foo://bar/baz'));
        self::assertSame('foo://bar/baz', Utils::url('foo://bar/baz', true));
        // self::assertSame('mailto:joe@domain.com', Utils::url('mailto:joe@domain.com', true)); // FIXME <-
    }

    public function testUrlWithRoot(): void
    {
        $this->uri->initializeWithUrlAndRootPath('http://testing.dev/subdir/path1/path2', '/subdir')->init();

        // Fail hard
        self::assertSame(false, Utils::url('', true));
        self::assertSame(false, Utils::url(''));
        self::assertSame(false, Utils::url(new stdClass()));
        self::assertSame(false, Utils::url(['foo','bar','baz']));
        self::assertSame(false, Utils::url('user://does/not/exist'));

        // Fail Gracefully
        self::assertSame('/subdir/', Utils::url('/', false, true));
        self::assertSame('/subdir/', Utils::url('', false, true));
        self::assertSame('/subdir/', Utils::url(new stdClass(), false, true));
        self::assertSame('/subdir/', Utils::url(['foo','bar','baz'], false, true));
        self::assertSame('/subdir/user/does/not/exist', Utils::url('user://does/not/exist', false, true));

        // Simple paths
        self::assertSame('/subdir/', Utils::url('/'));
        self::assertSame('/subdir/path1', Utils::url('/path1'));
        self::assertSame('/subdir/path1/path2', Utils::url('/path1/path2'));
        self::assertSame('/subdir/random/path1/path2', Utils::url('/random/path1/path2'));
        self::assertSame('/subdir/foobar.jpg', Utils::url('/foobar.jpg'));
        self::assertSame('/subdir/path1/foobar.jpg', Utils::url('/path1/foobar.jpg'));
        self::assertSame('/subdir/path1/path2/foobar.jpg', Utils::url('/path1/path2/foobar.jpg'));
        self::assertSame('/subdir/random/path1/path2/foobar.jpg', Utils::url('/random/path1/path2/foobar.jpg'));

        // Simple paths with domain
        self::assertSame('http://testing.dev/subdir/', Utils::url('/', true));
        self::assertSame('http://testing.dev/subdir/path1', Utils::url('/path1', true));
        self::assertSame('http://testing.dev/subdir/path1/path2', Utils::url('/path1/path2', true));
        self::assertSame('http://testing.dev/subdir/random/path1/path2', Utils::url('/random/path1/path2', true));
        self::assertSame('http://testing.dev/subdir/foobar.jpg', Utils::url('/foobar.jpg', true));
        self::assertSame('http://testing.dev/subdir/path1/foobar.jpg', Utils::url('/path1/foobar.jpg', true));
        self::assertSame('http://testing.dev/subdir/path1/path2/foobar.jpg', Utils::url('/path1/path2/foobar.jpg', true));
        self::assertSame('http://testing.dev/subdir/random/path1/path2/foobar.jpg', Utils::url('/random/path1/path2/foobar.jpg', true));

        // Absolute Paths including the grav base.
        self::assertSame('/subdir/', Utils::url('/subdir'));
        self::assertSame('/subdir/', Utils::url('/subdir/'));
        self::assertSame('/subdir/path1', Utils::url('/subdir/path1'));
        self::assertSame('/subdir/path1/path2', Utils::url('/subdir/path1/path2'));
        self::assertSame('/subdir/foobar.jpg', Utils::url('/subdir/foobar.jpg'));
        self::assertSame('/subdir/path1/foobar.jpg', Utils::url('/subdir/path1/foobar.jpg'));

        // Absolute paths from Grav root with domain.
        self::assertSame('http://testing.dev/subdir/', Utils::url('/subdir', true));
        self::assertSame('http://testing.dev/subdir/', Utils::url('/subdir/', true));
        self::assertSame('http://testing.dev/subdir/path1', Utils::url('/subdir/path1', true));
        self::assertSame('http://testing.dev/subdir/path1/path2', Utils::url('/subdir/path1/path2', true));
        self::assertSame('http://testing.dev/subdir/foobar.jpg', Utils::url('/subdir/foobar.jpg', true));
        self::assertSame('http://testing.dev/subdir/path1/foobar.jpg', Utils::url('/subdir/path1/foobar.jpg', true));

        // Relative paths from Grav root.
        self::assertSame('/subdir/sub', Utils::url('/sub'));
        self::assertSame('/subdir/subdir', Utils::url('subdir'));
        self::assertSame('/subdir/subdir2/sub', Utils::url('/subdir2/sub'));
        self::assertSame('/subdir/subdir/path1', Utils::url('subdir/path1'));
        self::assertSame('/subdir/subdir/path1/path2', Utils::url('subdir/path1/path2'));
        self::assertSame('/subdir/path1', Utils::url('path1'));
        self::assertSame('/subdir/path1/path2', Utils::url('path1/path2'));
        self::assertSame('/subdir/foobar.jpg', Utils::url('foobar.jpg'));
        self::assertSame('http://testing.dev/subdir/foobar.jpg', Utils::url('foobar.jpg', true));

        // All Non-existing streams should be treated as external URI / protocol.
        self::assertSame('http://domain.com/path', Utils::url('http://domain.com/path'));
        self::assertSame('ftp://domain.com/path', Utils::url('ftp://domain.com/path'));
        self::assertSame('sftp://domain.com/path', Utils::url('sftp://domain.com/path'));
        self::assertSame('ssh://domain.com', Utils::url('ssh://domain.com'));
        self::assertSame('pop://domain.com', Utils::url('pop://domain.com'));
        self::assertSame('foo://bar/baz', Utils::url('foo://bar/baz'));
        self::assertSame('foo://bar/baz', Utils::url('foo://bar/baz', true));
        // self::assertSame('mailto:joe@domain.com', Utils::url('mailto:joe@domain.com', true)); // FIXME <-
    }

    public function testUrlWithStreams(): void
    {
    }

    public function testUrlwithExternals(): void
    {
        $this->uri->initializeWithUrl('http://testing.dev/path1/path2')->init();
        self::assertSame('http://foo.com', Utils::url('http://foo.com'));
        self::assertSame('https://foo.com', Utils::url('https://foo.com'));
        self::assertSame('//foo.com', Utils::url('//foo.com'));
        self::assertSame('//foo.com?param=x', Utils::url('//foo.com?param=x'));
    }

    public function testCheckFilename(): void
    {
        // configure extension for consistent results
        /** @var \Grav\Common\Config\Config $config */
        $config = $this->grav['config'];
        $config->set('security.uploads_dangerous_extensions', ['php', 'html', 'htm', 'exe', 'js']);

        self::assertFalse(Utils::checkFilename('foo.php'));
        self::assertFalse(Utils::checkFilename('bar.js'));

        self::assertTrue(Utils::checkFilename('foo.json'));
        self::assertTrue(Utils::checkFilename('foo.xml'));
        self::assertTrue(Utils::checkFilename('foo.yaml'));
        self::assertTrue(Utils::checkFilename('foo.yml'));
    }
}
