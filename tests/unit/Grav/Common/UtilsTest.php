<?php

use Codeception\Util\Fixtures;
use Grav\Common\Utils;

class UtilsTest extends \Codeception\TestCase\Test
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    protected function _before()
    {
    }

    protected function _after()
    {
    }

    public function grav()
    {
        $grav = Fixtures::get('grav');
        return $grav;
    }
    
    public function testStartsWith()
    {
        $this->assertTrue(Utils::startsWith('english', 'en'));        
        $this->assertTrue(Utils::startsWith('English', 'En'));        
        $this->assertTrue(Utils::startsWith('ENGLISH', 'EN'));        
        $this->assertTrue(Utils::startsWith('ENGLISHENGLISHENGLISHENGLISHENGLISHENGLISHENGLISHENGLISHENGLISHENGLISH', 'EN')); 

        $this->assertFalse(Utils::startsWith('english', 'En'));        
        $this->assertFalse(Utils::startsWith('English', 'EN'));        
        $this->assertFalse(Utils::startsWith('ENGLISH', 'en'));        
        $this->assertFalse(Utils::startsWith('ENGLISHENGLISHENGLISHENGLISHENGLISHENGLISHENGLISHENGLISHENGLISHENGLISH', 'e')); 
    }
    
    public function testEndsWith()
    {
        $this->assertTrue(Utils::endsWith('english', 'sh'));        
        $this->assertTrue(Utils::endsWith('EngliSh', 'Sh'));        
        $this->assertTrue(Utils::endsWith('ENGLISH', 'SH'));        
        $this->assertTrue(Utils::endsWith('ENGLISHENGLISHENGLISHENGLISHENGLISHENGLISHENGLISHENGLISHENGLISHENGLISH', 'ENGLISH')); 

        $this->assertFalse(Utils::endsWith('english', 'de'));    
        $this->assertFalse(Utils::endsWith('EngliSh', 'sh'));
        $this->assertFalse(Utils::endsWith('ENGLISH', 'Sh'));
        $this->assertFalse(Utils::endsWith('ENGLISHENGLISHENGLISHENGLISHENGLISHENGLISHENGLISHENGLISHENGLISHENGLISH', 'DEUSTCH')); 
    }

    public function testContains()
    {
        $this->assertTrue(Utils::contains('english', 'nglis'));        
        $this->assertTrue(Utils::contains('EngliSh', 'gliSh'));        
        $this->assertTrue(Utils::contains('ENGLISH', 'ENGLI'));        
        $this->assertTrue(Utils::contains('ENGLISHENGLISHENGLISHENGLISHENGLISHENGLISHENGLISHENGLISHENGLISHENGLISH', 'ENGLISH')); 
    
        $this->assertFalse(Utils::contains('EngliSh', 'GLI'));        $this->assertFalse(Utils::contains('EngliSh', 'English'));
        $this->assertFalse(Utils::contains('ENGLISH', 'SCH'));
        $this->assertFalse(Utils::contains('ENGLISHENGLISHENGLISHENGLISHENGLISHENGLISHENGLISHENGLISHENGLISHENGLISH', 'DEUSTCH')); 
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
        $grav = $this->grav();
        $dateFormats = Utils::dateFormats();
        $this->assertTrue(is_array($dateFormats));
        $this->assertContainsOnly('string', $dateFormats);
        
        $default_format = $grav['config']->get('system.pages.dateformat.default');
        
        if ($default_format !== null) {
            $this->assertContains($default_format, $dateFormats);    
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
            'test' => '',
            'test2' => 'test2'
        ];

        $array = Utils::arrayFilterRecursive($array, function($k, $v) {
                    return !(is_null($v) || $v === '');
                });

        $this->assertContainsOnly('string', $array);
        $this->assertFalse(isset($array['test']));
        $this->assertTrue(isset($array['test2']));
        $this->assertEquals($array['test2'], 'test2');
    }
    
    public function pathPrefixedByLangCode()
    {

    }

    public function date2timestamp()
    {

    }

    public function resolve()
    {

    }

    public function isPositive()
    {

    }

    public function getNonce()
    {
        
    }
    
    public function verifyNonce()
    {

    }
}