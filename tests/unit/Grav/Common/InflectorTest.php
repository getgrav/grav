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
        $this->grav = Fixtures::get('grav');
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

    }

    public function testClassify()
    {

    }

    public function testOrdinalize()
    {

    }

    public function testMonthize()
    {

    }
}


