<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Common\Inflector;
use Grav\Common\Utils;

/**
 * Class InflectorTest
 */
class InflectorTest extends \Codeception\TestCase\Test
{
    /** @var Grav $grav */
    protected $grav;

    /** @var Uri $uri */
    protected $inflector;

    protected function _before()
    {
        $grav = Fixtures::get('grav');
        $this->grav = $grav();
        $this->inflector = $this->grav['inflector'];
    }

    protected function _after()
    {
    }

    public function testPluralize()
    {
        $this->assertSame('words',      $this->inflector->pluralize('word'));
        $this->assertSame('kisses',     $this->inflector->pluralize('kiss'));
        $this->assertSame('volcanoes',  $this->inflector->pluralize('volcanoe'));
        $this->assertSame('cherries',   $this->inflector->pluralize('cherry'));
        $this->assertSame('days',       $this->inflector->pluralize('day'));
        $this->assertSame('knives',     $this->inflector->pluralize('knife'));
    }

    public function testSingularize()
    {
        $this->assertSame('word',       $this->inflector->singularize('words'));
        $this->assertSame('kiss',       $this->inflector->singularize('kisses'));
        $this->assertSame('volcanoe',   $this->inflector->singularize('volcanoe'));
        $this->assertSame('cherry',     $this->inflector->singularize('cherries'));
        $this->assertSame('day',        $this->inflector->singularize('days'));
        $this->assertSame('knife',      $this->inflector->singularize('knives'));
    }

    public function testTitleize()
    {
        $this->assertSame('This String Is Titleized',   $this->inflector->titleize('ThisStringIsTitleized'));
        $this->assertSame('This String Is Titleized',   $this->inflector->titleize('this string is titleized'));
        $this->assertSame('This String Is Titleized',   $this->inflector->titleize('this_string_is_titleized'));
        $this->assertSame('This String Is Titleized',   $this->inflector->titleize('this-string-is-titleized'));

        $this->assertSame('This string is titleized',   $this->inflector->titleize('ThisStringIsTitleized', 'first'));
        $this->assertSame('This string is titleized',   $this->inflector->titleize('this string is titleized', 'first'));
        $this->assertSame('This string is titleized',   $this->inflector->titleize('this_string_is_titleized', 'first'));
        $this->assertSame('This string is titleized',   $this->inflector->titleize('this-string-is-titleized', 'first'));
    }

    public function testCamelize()
    {
        $this->assertSame('ThisStringIsCamelized',      $this->inflector->camelize('This String Is Camelized'));
        $this->assertSame('ThisStringIsCamelized',      $this->inflector->camelize('thisStringIsCamelized'));
        $this->assertSame('ThisStringIsCamelized',      $this->inflector->camelize('This_String_Is_Camelized'));
        $this->assertSame('ThisStringIsCamelized',      $this->inflector->camelize('this string is camelized'));
        $this->assertSame('GravSPrettyCoolMy1',         $this->inflector->camelize("Grav's Pretty Cool. My #1!"));
    }

    public function testUnderscorize()
    {
        $this->assertSame('this_string_is_underscorized',   $this->inflector->underscorize('This String Is Underscorized'));
        $this->assertSame('this_string_is_underscorized',   $this->inflector->underscorize('ThisStringIsUnderscorized'));
        $this->assertSame('this_string_is_underscorized',   $this->inflector->underscorize('This_String_Is_Underscorized'));
        $this->assertSame('this_string_is_underscorized',   $this->inflector->underscorize('This-String-Is-Underscorized'));
    }

    public function testHyphenize()
    {
        $this->assertSame('this-string-is-hyphenized',     $this->inflector->hyphenize('This String Is Hyphenized'));
        $this->assertSame('this-string-is-hyphenized',     $this->inflector->hyphenize('ThisStringIsHyphenized'));
        $this->assertSame('this-string-is-hyphenized',     $this->inflector->hyphenize('This-String-Is-Hyphenized'));
        $this->assertSame('this-string-is-hyphenized',     $this->inflector->hyphenize('This_String_Is_Hyphenized'));
    }

    public function testHumanize()
    {
        //$this->assertSame('This string is humanized',   $this->inflector->humanize('ThisStringIsHumanized'));
        $this->assertSame('This string is humanized',   $this->inflector->humanize('this_string_is_humanized'));
        //$this->assertSame('This string is humanized',   $this->inflector->humanize('this-string-is-humanized'));

        $this->assertSame('This String Is Humanized',   $this->inflector->humanize('this_string_is_humanized', 'all'));
        //$this->assertSame('This String Is Humanized',   $this->inflector->humanize('this-string-is-humanized'), 'all');
    }

    public function testVariablize()
    {
        $this->assertSame('thisStringIsVariablized',      $this->inflector->variablize('This String Is Variablized'));
        $this->assertSame('thisStringIsVariablized',      $this->inflector->variablize('ThisStringIsVariablized'));
        $this->assertSame('thisStringIsVariablized',      $this->inflector->variablize('This_String_Is_Variablized'));
        $this->assertSame('thisStringIsVariablized',      $this->inflector->variablize('this string is variablized'));
        $this->assertSame('gravSPrettyCoolMy1',           $this->inflector->variablize("Grav's Pretty Cool. My #1!"));
    }

    public function testTableize()
    {
        $this->assertSame('people',                 $this->inflector->tableize('Person'));
        $this->assertSame('pages',                  $this->inflector->tableize('Page'));
        $this->assertSame('blog_pages',             $this->inflector->tableize('BlogPage'));
        $this->assertSame('admin_dependencies',     $this->inflector->tableize('adminDependency'));
        $this->assertSame('admin_dependencies',     $this->inflector->tableize('admin-dependency'));
        $this->assertSame('admin_dependencies',     $this->inflector->tableize('admin_dependency'));
    }

    public function testClassify()
    {
        $this->assertSame('Person',                 $this->inflector->classify('people'));
        $this->assertSame('Page',                  $this->inflector->classify('pages'));
        $this->assertSame('BlogPage',             $this->inflector->classify('blog_pages'));
        $this->assertSame('AdminDependency',     $this->inflector->classify('admin_dependencies'));
    }

    public function testOrdinalize()
    {
        $this->assertSame('1st',    $this->inflector->ordinalize(1));
        $this->assertSame('2nd',    $this->inflector->ordinalize(2));
        $this->assertSame('3rd',    $this->inflector->ordinalize(3));
        $this->assertSame('4th',    $this->inflector->ordinalize(4));
        $this->assertSame('5th',    $this->inflector->ordinalize(5));
        $this->assertSame('16th',   $this->inflector->ordinalize(16));
        $this->assertSame('51st',   $this->inflector->ordinalize(51));
        $this->assertSame('111th',  $this->inflector->ordinalize(111));
        $this->assertSame('123rd',  $this->inflector->ordinalize(123));
    }

    public function testMonthize()
    {
        $this->assertSame(0,    $this->inflector->monthize(10));
        $this->assertSame(1,    $this->inflector->monthize(33));
        $this->assertSame(1,    $this->inflector->monthize(41));
        $this->assertSame(11,   $this->inflector->monthize(364));
    }
}


