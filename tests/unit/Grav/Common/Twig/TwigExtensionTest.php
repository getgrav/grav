<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Common\Twig\TwigExtension;

/**
 * Class TwigExtensionTest
 */
class TwigExtensionTest extends \Codeception\TestCase\Test
{
    /** @var Grav $grav */
    protected $grav;

    /** @var  TwigExtension $twig_ext */
    protected $twig_ext;

    protected function _before()
    {
        $this->grav = Fixtures::get('grav');
        $this->twig_ext = new TwigExtension();
    }

    public function testInflectorFilter()
    {
        $this->assertSame('people',                     $this->twig_ext->inflectorFilter('plural',       'person'));
        $this->assertSame('shoe',                       $this->twig_ext->inflectorFilter('singular',     'shoes'));
        $this->assertSame('Welcome Page',               $this->twig_ext->inflectorFilter('title',        'welcome page'));
        $this->assertSame('SendEmail',                  $this->twig_ext->inflectorFilter('camel',        'send_email'));
        $this->assertSame('camel_cased',                $this->twig_ext->inflectorFilter('underscor',    'CamelCased'));
        $this->assertSame('something-text',             $this->twig_ext->inflectorFilter('hyphen',       'Something Text'));
        $this->assertSame('Something text to read',     $this->twig_ext->inflectorFilter('human',        'something_text_to_read'));
        $this->assertSame(5,                            $this->twig_ext->inflectorFilter('month',        175));
        $this->assertSame('10th',                       $this->twig_ext->inflectorFilter('ordinal',      10));
    }

    public function testMd5Filter()
    {
        $this->assertSame(md5('grav'),              $this->twig_ext->md5Filter('grav'));
        $this->assertSame(md5('devs@getgrav.org'),  $this->twig_ext->md5Filter('devs@getgrav.org'));
    }

    public function testKsortFilter()
    {
        $object = array("name"=>"Bob","age"=>8,"colour"=>"red");
        $this->assertSame(array("age"=>8,"colour"=>"red","name"=>"Bob"), $this->twig_ext->ksortFilter($object));
    }

    public function testContainsFilter()
    {
        $this->assertTrue($this->twig_ext->containsFilter('grav','grav'));
        $this->assertTrue($this->twig_ext->containsFilter('So, I found this new cms, called grav, and it\'s pretty awesome guys','grav'));
    }

    public function testNicetimeFilter()
    {
        $now = time();
        $threeMinutes = time() - (60*3);
        $threeHours   = time() - (60*60*3);
        $threeDays    = time() - (60*60*24*3);
        $threeMonths  = time() - (60*60*24*30*3);
        $threeYears   = time() - (60*60*24*365*3);
        $measures = ['minutes','hours','days','months','years'];

        $this->assertSame('No date provided', $this->twig_ext->nicetimeFunc(null));

        for ($i=0; $i<count($measures); $i++) {
            $time = 'three' . ucfirst($measures[$i]);
            $this->assertSame('3 ' . $measures[$i] . ' ago', $this->twig_ext->nicetimeFunc($$time));
        }
    }

    public function testRandomizeFilter()
    {
        $array = [1,2,3,4,5];
        $this->assertContains(2,        $this->twig_ext->randomizeFilter($array));
        $this->assertSame($array,       $this->twig_ext->randomizeFilter($array, 5));
        $this->assertSame($array[0],    $this->twig_ext->randomizeFilter($array, 1)[0]);
        $this->assertSame($array[3],    $this->twig_ext->randomizeFilter($array, 4)[3]);
        $this->assertSame($array[1],    $this->twig_ext->randomizeFilter($array, 4)[1]);
    }

    public function testModulusFilter()
    {
        $this->assertSame(3,    $this->twig_ext->modulusFilter(3,4));
        $this->assertSame(1,    $this->twig_ext->modulusFilter(11,2));
        $this->assertSame(0,    $this->twig_ext->modulusFilter(10,2));
        $this->assertSame(2,    $this->twig_ext->modulusFilter(10,4));
    }

    public function testAbsoluteUrlFilter()
    {

    }

    public function testMarkdownFilter()
    {

    }

    public function testStartsWithFilter()
    {

    }

    public function testEndsWithFilter()
    {

    }

    public function testDefinedDefaultFilter()
    {

    }

    public function testRtrimFilter()
    {

    }

    public function testLtrimFilter()
    {

    }

    public function testRepeatFunc()
    {

    }

    public function testRegexReplace()
    {

    }

    public function testUrlFunc()
    {

    }

    public function testEvaluateFunc()
    {

    }

    public function testDump()
    {

    }

    public function testGistFunc()
    {

    }

    public function testRandomStringFunc()
    {

    }

    public function testPadFilter()
    {

    }

    public function testArrayFunc()
    {
        $this->assertSame('this is my text',
            $this->twig_ext->regexReplace('<p>this is my text</p>', '(<\/?p>)', ''));
        $this->assertSame('<i>this is my text</i>',
            $this->twig_ext->regexReplace('<p>this is my text</p>', ['(<p>)','(<\/p>)'], ['<i>','</i>']));
    }

    public function testArrayKeyValue()
    {
        $this->assertSame(['meat' => 'steak'],
            $this->twig_ext->arrayKeyValueFunc('meat', 'steak'));
        $this->assertSame(['fruit' => 'apple', 'meat' => 'steak'],
            $this->twig_ext->arrayKeyValueFunc('meat', 'steak', ['fruit' => 'apple']));
    }

    public function stringFunc()
    {

    }

    public function testRangeFunc()
    {
        $hundred = [];
        for($i = 0; $i <= 100; $i++) { $hundred[] = $i; }


        $this->assertSame([0], $this->twig_ext->rangeFunc(0, 0));
        $this->assertSame([0, 1, 2], $this->twig_ext->rangeFunc(0, 2));

        $this->assertSame([0, 5, 10, 15], $this->twig_ext->rangeFunc(0, 16, 5));

        // default (min 0, max 100, step 1)
        $this->assertSame($hundred, $this->twig_ext->rangeFunc());

        // 95 items, starting from 5, (min 5, max 100, step 1)
        $this->assertSame(array_slice($hundred, 5), $this->twig_ext->rangeFunc(5));

        // reversed range
        $this->assertSame(array_reverse($hundred), $this->twig_ext->rangeFunc(100, 0));
        $this->assertSame([4, 2, 0], $this->twig_ext->rangeFunc(4, 0, 2));
    }
}
