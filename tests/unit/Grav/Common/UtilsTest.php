<?php

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
    
    public function dateFormats()
    {

    }

    public function truncate()
    {

    }

    public function safeTruncate()
    {

    }

    public function truncateHtml()
    {

    }

    public function safeTruncateHtml()
    {

    }

    public function generateRandomString()
    {

    }

    public function download()
    {

    }

    public function getMimeType()
    {

    }

    public function normalizePath()
    {

    }

    public function isFunctionDisabled()
    {
        
    }

    public function timezones()
    {


    }

    public function arrayFilterRecursive()
    {

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