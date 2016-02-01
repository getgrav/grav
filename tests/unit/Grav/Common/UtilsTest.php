<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Common\Utils;

/**
 * Class UtilsTest
 */
class UtilsTest extends \Codeception\TestCase\Test
{
    /** @var Grav $grav */
    protected $grav;

    protected function _before()
    {
        $this->grav = Fixtures::get('grav');
    }

    protected function _after()
    {
    }

    public function testStartsWith()
    {
        $this->assertTrue(Utils::startsWith('english', 'en'));
        $this->assertTrue(Utils::startsWith('English', 'En'));
        $this->assertTrue(Utils::startsWith('ENGLISH', 'EN'));
        $this->assertTrue(Utils::startsWith('ENGLISHENGLISHENGLISHENGLISHENGLISHENGLISHENGLISHENGLISHENGLISHENGLISH',
            'EN'));

        $this->assertFalse(Utils::startsWith('english', 'En'));
        $this->assertFalse(Utils::startsWith('English', 'EN'));
        $this->assertFalse(Utils::startsWith('ENGLISH', 'en'));
        $this->assertFalse(Utils::startsWith('ENGLISHENGLISHENGLISHENGLISHENGLISHENGLISHENGLISHENGLISHENGLISHENGLISH',
            'e'));
    }

    public function testEndsWith()
    {
        $this->assertTrue(Utils::endsWith('english', 'sh'));
        $this->assertTrue(Utils::endsWith('EngliSh', 'Sh'));
        $this->assertTrue(Utils::endsWith('ENGLISH', 'SH'));
        $this->assertTrue(Utils::endsWith('ENGLISHENGLISHENGLISHENGLISHENGLISHENGLISHENGLISHENGLISHENGLISHENGLISH',
            'ENGLISH'));

        $this->assertFalse(Utils::endsWith('english', 'de'));
        $this->assertFalse(Utils::endsWith('EngliSh', 'sh'));
        $this->assertFalse(Utils::endsWith('ENGLISH', 'Sh'));
        $this->assertFalse(Utils::endsWith('ENGLISHENGLISHENGLISHENGLISHENGLISHENGLISHENGLISHENGLISHENGLISHENGLISH',
            'DEUSTCH'));
    }

    public function testContains()
    {
        $this->assertTrue(Utils::contains('english', 'nglis'));
        $this->assertTrue(Utils::contains('EngliSh', 'gliSh'));
        $this->assertTrue(Utils::contains('ENGLISH', 'ENGLI'));
        $this->assertTrue(Utils::contains('ENGLISHENGLISHENGLISHENGLISHENGLISHENGLISHENGLISHENGLISHENGLISHENGLISH',
            'ENGLISH'));

        $this->assertFalse(Utils::contains('EngliSh', 'GLI'));
        $this->assertFalse(Utils::contains('EngliSh', 'English'));
        $this->assertFalse(Utils::contains('ENGLISH', 'SCH'));
        $this->assertFalse(Utils::contains('ENGLISHENGLISHENGLISHENGLISHENGLISHENGLISHENGLISHENGLISHENGLISHENGLISH',
            'DEUSTCH'));
    }

    public function testSubstrToString()
    {
        $this->assertEquals(Utils::substrToString('english', 'glish'), 'en');
        $this->assertEquals(Utils::substrToString('english', 'test'), 'english');
        $this->assertNotEquals(Utils::substrToString('english', 'lish'), 'en');
    }

    public function testMergeObjects()
    {
        $obj1 = new stdClass();
        $obj1->test1 = 'x';
        $obj2 = new stdClass();
        $obj2->test2 = 'y';

        $objMerged = Utils::mergeObjects($obj1, $obj2);

        $this->assertObjectHasAttribute('test1', $objMerged);
        $this->assertObjectHasAttribute('test2', $objMerged);
    }

    public function testDateFormats()
    {
        $dateFormats = Utils::dateFormats();
        $this->assertTrue(is_array($dateFormats));
        $this->assertContainsOnly('string', $dateFormats);

        $default_format = $this->grav['config']->get('system.pages.dateformat.default');

        if ($default_format !== null) {
            $this->assertTrue(isset($dateFormats[$default_format]));
        }
    }

    public function testTruncate()
    {
        $this->assertEquals(Utils::truncate('english', 5), 'engli' . '&hellip;');
        $this->assertEquals(Utils::truncate('english'), 'english');
        $this->assertEquals(Utils::truncate('This is a string to truncate'), 'This is a string to truncate');
        $this->assertEquals(Utils::truncate('This is a string to truncate', 2), 'Th' . '&hellip;');
        $this->assertEquals(Utils::truncate('english', 5, true, " ", "..."), 'engli' . '...');
        $this->assertEquals(Utils::truncate('english'), 'english');
        $this->assertEquals(Utils::truncate('This is a string to truncate'), 'This is a string to truncate');
        $this->assertEquals(Utils::truncate('This is a string to truncate', 3, true), 'This ');
    }

    public function testSafeTruncate()
    {
        $this->assertEquals(Utils::safeTruncate('This is a string to truncate', 1), 'This ');
        $this->assertEquals(Utils::safeTruncate('This is a string to truncate', 4), 'This ');
        $this->assertEquals(Utils::safeTruncate('This is a string to truncate', 5), 'This is ');
    }

    public function testTruncateHtml()
    {
        $this->assertEquals(Utils::truncateHtml('<p>This is a string to truncate</p>', 1), '<p>T…</p>');
        $this->assertEquals(Utils::truncateHtml('<p>This is a string to truncate</p>', 4), '<p>This…</p>');
    }

    public function testSafeTruncateHtml()
    {
        $this->assertEquals(Utils::safeTruncateHtml('<p>This is a string to truncate</p>', 1), '<p>This…</p>');
        $this->assertEquals(Utils::safeTruncateHtml('<p>This is a string to truncate</p>', 4), '<p>This…</p>');
    }

    public function testGenerateRandomString()
    {
        $this->assertNotEquals(Utils::generateRandomString(), Utils::generateRandomString());
        $this->assertNotEquals(Utils::generateRandomString(20), Utils::generateRandomString(20));
    }

    public function download()
    {

    }

    public function testGetMimeType()
    {
        $this->assertEquals(Utils::getMimeType(''), 'application/octet-stream');
        $this->assertEquals(Utils::getMimeType('jpg'), 'image/jpeg');
        $this->assertEquals(Utils::getMimeType('png'), 'image/png');
        $this->assertEquals(Utils::getMimeType('txt'), 'text/plain');
        $this->assertEquals(Utils::getMimeType('doc'), 'application/msword');
    }

    public function testNormalizePath()
    {
        $this->assertEquals(Utils::normalizePath('/test'), '/test');
        $this->assertEquals(Utils::normalizePath('test'), 'test');
        $this->assertEquals(Utils::normalizePath('../test'), 'test');
        $this->assertEquals(Utils::normalizePath('/../test'), '/test');
        $this->assertEquals(Utils::normalizePath('/test/../test2'), '/test2');
        $this->assertEquals(Utils::normalizePath('/test/./test2'), '/test/test2');
    }

    public function testIsFunctionDisabled()
    {
        $disabledFunctions = explode(',', ini_get('disable_functions'));

        if ($disabledFunctions[0]) {
            $this->assertEquals(Utils::isFunctionDisabled($disabledFunctions[0]), true);
        }
    }

    public function testTimezones()
    {
        $timezones = Utils::timezones();

        $this->assertTrue(is_array($timezones));
        $this->assertContainsOnly('string', $timezones);
    }

    public function testArrayFilterRecursive()
    {
        $array = [
            'test'  => '',
            'test2' => 'test2'
        ];

        $array = Utils::arrayFilterRecursive($array, function ($k, $v) {
            return !(is_null($v) || $v === '');
        });

        $this->assertContainsOnly('string', $array);
        $this->assertFalse(isset($array['test']));
        $this->assertTrue(isset($array['test2']));
        $this->assertEquals($array['test2'], 'test2');
    }

    public function testPathPrefixedByLangCode()
    {
        $languagesEnabled = $this->grav['config']->get('system.languages.supported', []);
        $arrayOfLanguages = ['en', 'de', 'it', 'es', 'dk', 'el'];
        $languagesNotEnabled = array_diff($arrayOfLanguages, $languagesEnabled);
        $oneLanguageNotEnabled = reset($languagesNotEnabled);

        if (count($languagesEnabled)) {
            $this->assertTrue(Utils::pathPrefixedByLangCode('/' . $languagesEnabled[0] . '/test'));
        }

        $this->assertFalse(Utils::pathPrefixedByLangCode('/' . $oneLanguageNotEnabled . '/test'));
        $this->assertFalse(Utils::pathPrefixedByLangCode('/test'));
        $this->assertFalse(Utils::pathPrefixedByLangCode('/xx'));
        $this->assertFalse(Utils::pathPrefixedByLangCode('/xx/'));
        $this->assertFalse(Utils::pathPrefixedByLangCode('/'));
    }

    public function testDate2timestamp()
    {
        $timestamp = strtotime('10 September 2000');
        $this->assertSame($timestamp, Utils::date2timestamp('10 September 2000'));
        $this->assertSame($timestamp, Utils::date2timestamp('2000-09-10 00:00:00'));
    }

    public function testResolve()
    {
        $array = [
            'test' => [
                'test2' => 'test2Value'
            ]
        ];

        $this->assertEquals(Utils::resolve($array, 'test.test2'), 'test2Value');
    }

    public function testIsPositive()
    {
        $this->assertTrue(Utils::isPositive(true));
        $this->assertTrue(Utils::isPositive(1));
        $this->assertTrue(Utils::isPositive('1'));
        $this->assertTrue(Utils::isPositive('yes'));
        $this->assertTrue(Utils::isPositive('on'));
        $this->assertTrue(Utils::isPositive('true'));
        $this->assertFalse(Utils::isPositive(false));
        $this->assertFalse(Utils::isPositive(0));
        $this->assertFalse(Utils::isPositive('0'));
        $this->assertFalse(Utils::isPositive('no'));
        $this->assertFalse(Utils::isPositive('off'));
        $this->assertFalse(Utils::isPositive('false'));
        $this->assertFalse(Utils::isPositive('some'));
        $this->assertFalse(Utils::isPositive(2));
    }

    public function testGetNonce()
    {
        $this->assertTrue(is_string(Utils::getNonce('test-action')));
        $this->assertTrue(is_string(Utils::getNonce('test-action', true)));
        $this->assertSame(Utils::getNonce('test-action'), Utils::getNonce('test-action'));
        $this->assertNotSame(Utils::getNonce('test-action'), Utils::getNonce('test-action2'));
    }

    public function testVerifyNonce()
    {
        $this->assertTrue(Utils::verifyNonce(Utils::getNonce('test-action'), 'test-action'));
    }
}