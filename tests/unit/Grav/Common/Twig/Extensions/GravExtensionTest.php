<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Common\Twig\Extension\GravExtension;

/**
 * Class GravExtensionTest
 */
class GravExtensionTest extends \Codeception\TestCase\Test
{
    /** @var Grav $grav */
    protected $grav;

    /** @var  GravExtension $twig_ext */
    protected $twig_ext;

    protected function _before(): void
    {
        $this->grav = Fixtures::get('grav');
        $this->twig_ext = new GravExtension();
    }

    public function testInflectorFilter(): void
    {
        self::assertSame('people', $this->twig_ext->inflectorFilter('plural', 'person'));
        self::assertSame('shoe', $this->twig_ext->inflectorFilter('singular', 'shoes'));
        self::assertSame('Welcome Page', $this->twig_ext->inflectorFilter('title', 'welcome page'));
        self::assertSame('SendEmail', $this->twig_ext->inflectorFilter('camel', 'send_email'));
        self::assertSame('camel_cased', $this->twig_ext->inflectorFilter('underscor', 'CamelCased'));
        self::assertSame('something-text', $this->twig_ext->inflectorFilter('hyphen', 'Something Text'));
        self::assertSame('Something text to read', $this->twig_ext->inflectorFilter('human', 'something_text_to_read'));
        self::assertSame(5, $this->twig_ext->inflectorFilter('month', '175'));
        self::assertSame('10th', $this->twig_ext->inflectorFilter('ordinal', '10'));
    }

    public function testMd5Filter(): void
    {
        self::assertSame(md5('grav'), $this->twig_ext->md5Filter('grav'));
        self::assertSame(md5('devs@getgrav.org'), $this->twig_ext->md5Filter('devs@getgrav.org'));
    }

    public function testKsortFilter(): void
    {
        $object = array("name"=>"Bob","age"=>8,"colour"=>"red");
        self::assertSame(array("age"=>8,"colour"=>"red","name"=>"Bob"), $this->twig_ext->ksortFilter($object));
    }

    public function testContainsFilter(): void
    {
        self::assertTrue($this->twig_ext->containsFilter('grav', 'grav'));
        self::assertTrue($this->twig_ext->containsFilter('So, I found this new cms, called grav, and it\'s pretty awesome guys', 'grav'));
    }

    public function testNicetimeFilter(): void
    {
        $now = time();
        $threeMinutes = time() - (60*3);
        $threeHours   = time() - (60*60*3);
        $threeDays    = time() - (60*60*24*3);
        $threeMonths  = time() - (60*60*24*30*3);
        $threeYears   = time() - (60*60*24*365*3);
        $measures = ['minutes','hours','days','months','years'];

        self::assertSame('No date provided', $this->twig_ext->nicetimeFunc(null));

        for ($i=0; $i<count($measures); $i++) {
            $time = 'three' . ucfirst($measures[$i]);
            self::assertSame('3 ' . $measures[$i] . ' ago', $this->twig_ext->nicetimeFunc($$time));
        }
    }

    public function testRandomizeFilter(): void
    {
        $array = [1,2,3,4,5];
        self::assertContains(2, $this->twig_ext->randomizeFilter($array));
        self::assertSame($array, $this->twig_ext->randomizeFilter($array, 5));
        self::assertSame($array[0], $this->twig_ext->randomizeFilter($array, 1)[0]);
        self::assertSame($array[3], $this->twig_ext->randomizeFilter($array, 4)[3]);
        self::assertSame($array[1], $this->twig_ext->randomizeFilter($array, 4)[1]);
    }

    public function testModulusFilter(): void
    {
        self::assertSame(3, $this->twig_ext->modulusFilter(3, 4));
        self::assertSame(1, $this->twig_ext->modulusFilter(11, 2));
        self::assertSame(0, $this->twig_ext->modulusFilter(10, 2));
        self::assertSame(2, $this->twig_ext->modulusFilter(10, 4));
    }

    public function testAbsoluteUrlFilter(): void
    {
    }

    public function testMarkdownFilter(): void
    {
    }

    public function testStartsWithFilter(): void
    {
    }

    public function testEndsWithFilter(): void
    {
    }

    public function testDefinedDefaultFilter(): void
    {
    }

    public function testRtrimFilter(): void
    {
    }

    public function testLtrimFilter(): void
    {
    }

    public function testRepeatFunc(): void
    {
    }

    public function testRegexReplace(): void
    {
    }

    public function testUrlFunc(): void
    {
    }

    public function testEvaluateFunc(): void
    {
    }

    public function testDump(): void
    {
    }

    public function testGistFunc(): void
    {
    }

    public function testRandomStringFunc(): void
    {
    }

    public function testPadFilter(): void
    {
    }

    public function testArrayFunc(): void
    {
        self::assertSame(
            'this is my text',
            $this->twig_ext->regexReplace('<p>this is my text</p>', '(<\/?p>)', '')
        );
        self::assertSame(
            '<i>this is my text</i>',
            $this->twig_ext->regexReplace('<p>this is my text</p>', ['(<p>)','(<\/p>)'], ['<i>','</i>'])
        );
    }

    public function testArrayKeyValue(): void
    {
        self::assertSame(
            ['meat' => 'steak'],
            $this->twig_ext->arrayKeyValueFunc('meat', 'steak')
        );
        self::assertSame(
            ['fruit' => 'apple', 'meat' => 'steak'],
            $this->twig_ext->arrayKeyValueFunc('meat', 'steak', ['fruit' => 'apple'])
        );
    }

    public function stringFunc(): void
    {
    }

    public function testRangeFunc(): void
    {
        $hundred = [];
        for ($i = 0; $i <= 100; $i++) {
            $hundred[] = $i;
        }


        self::assertSame([0], $this->twig_ext->rangeFunc(0, 0));
        self::assertSame([0, 1, 2], $this->twig_ext->rangeFunc(0, 2));

        self::assertSame([0, 5, 10, 15], $this->twig_ext->rangeFunc(0, 16, 5));

        // default (min 0, max 100, step 1)
        self::assertSame($hundred, $this->twig_ext->rangeFunc());

        // 95 items, starting from 5, (min 5, max 100, step 1)
        self::assertSame(array_slice($hundred, 5), $this->twig_ext->rangeFunc(5));

        // reversed range
        self::assertSame(array_reverse($hundred), $this->twig_ext->rangeFunc(100, 0));
        self::assertSame([4, 2, 0], $this->twig_ext->rangeFunc(4, 0, 2));
    }
}
