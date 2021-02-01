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

    /** @var Inflector $uri */
    protected $inflector;

    protected function _before(): void
    {
        $grav = Fixtures::get('grav');
        $this->grav = $grav();
        $this->inflector = $this->grav['inflector'];
    }

    protected function _after(): void
    {
    }

    public function testPluralize(): void
    {
        self::assertSame('words', $this->inflector->pluralize('word'));
        self::assertSame('kisses', $this->inflector->pluralize('kiss'));
        self::assertSame('volcanoes', $this->inflector->pluralize('volcanoe'));
        self::assertSame('cherries', $this->inflector->pluralize('cherry'));
        self::assertSame('days', $this->inflector->pluralize('day'));
        self::assertSame('knives', $this->inflector->pluralize('knife'));
    }

    public function testSingularize(): void
    {
        self::assertSame('word', $this->inflector->singularize('words'));
        self::assertSame('kiss', $this->inflector->singularize('kisses'));
        self::assertSame('volcanoe', $this->inflector->singularize('volcanoe'));
        self::assertSame('cherry', $this->inflector->singularize('cherries'));
        self::assertSame('day', $this->inflector->singularize('days'));
        self::assertSame('knife', $this->inflector->singularize('knives'));
    }

    public function testTitleize(): void
    {
        self::assertSame('This String Is Titleized', $this->inflector->titleize('ThisStringIsTitleized'));
        self::assertSame('This String Is Titleized', $this->inflector->titleize('this string is titleized'));
        self::assertSame('This String Is Titleized', $this->inflector->titleize('this_string_is_titleized'));
        self::assertSame('This String Is Titleized', $this->inflector->titleize('this-string-is-titleized'));

        self::assertSame('This string is titleized', $this->inflector->titleize('ThisStringIsTitleized', 'first'));
        self::assertSame('This string is titleized', $this->inflector->titleize('this string is titleized', 'first'));
        self::assertSame('This string is titleized', $this->inflector->titleize('this_string_is_titleized', 'first'));
        self::assertSame('This string is titleized', $this->inflector->titleize('this-string-is-titleized', 'first'));
    }

    public function testCamelize(): void
    {
        self::assertSame('ThisStringIsCamelized', $this->inflector->camelize('This String Is Camelized'));
        self::assertSame('ThisStringIsCamelized', $this->inflector->camelize('thisStringIsCamelized'));
        self::assertSame('ThisStringIsCamelized', $this->inflector->camelize('This_String_Is_Camelized'));
        self::assertSame('ThisStringIsCamelized', $this->inflector->camelize('this string is camelized'));
        self::assertSame('GravSPrettyCoolMy1', $this->inflector->camelize("Grav's Pretty Cool. My #1!"));
    }

    public function testUnderscorize(): void
    {
        self::assertSame('this_string_is_underscorized', $this->inflector->underscorize('This String Is Underscorized'));
        self::assertSame('this_string_is_underscorized', $this->inflector->underscorize('ThisStringIsUnderscorized'));
        self::assertSame('this_string_is_underscorized', $this->inflector->underscorize('This_String_Is_Underscorized'));
        self::assertSame('this_string_is_underscorized', $this->inflector->underscorize('This-String-Is-Underscorized'));
    }

    public function testHyphenize(): void
    {
        self::assertSame('this-string-is-hyphenized', $this->inflector->hyphenize('This String Is Hyphenized'));
        self::assertSame('this-string-is-hyphenized', $this->inflector->hyphenize('ThisStringIsHyphenized'));
        self::assertSame('this-string-is-hyphenized', $this->inflector->hyphenize('This-String-Is-Hyphenized'));
        self::assertSame('this-string-is-hyphenized', $this->inflector->hyphenize('This_String_Is_Hyphenized'));
    }

    public function testHumanize(): void
    {
        //self::assertSame('This string is humanized',   $this->inflector->humanize('ThisStringIsHumanized'));
        self::assertSame('This string is humanized', $this->inflector->humanize('this_string_is_humanized'));
        //self::assertSame('This string is humanized',   $this->inflector->humanize('this-string-is-humanized'));

        self::assertSame('This String Is Humanized', $this->inflector->humanize('this_string_is_humanized', 'all'));
        //self::assertSame('This String Is Humanized',   $this->inflector->humanize('this-string-is-humanized'), 'all');
    }

    public function testVariablize(): void
    {
        self::assertSame('thisStringIsVariablized', $this->inflector->variablize('This String Is Variablized'));
        self::assertSame('thisStringIsVariablized', $this->inflector->variablize('ThisStringIsVariablized'));
        self::assertSame('thisStringIsVariablized', $this->inflector->variablize('This_String_Is_Variablized'));
        self::assertSame('thisStringIsVariablized', $this->inflector->variablize('this string is variablized'));
        self::assertSame('gravSPrettyCoolMy1', $this->inflector->variablize("Grav's Pretty Cool. My #1!"));
    }

    public function testTableize(): void
    {
        self::assertSame('people', $this->inflector->tableize('Person'));
        self::assertSame('pages', $this->inflector->tableize('Page'));
        self::assertSame('blog_pages', $this->inflector->tableize('BlogPage'));
        self::assertSame('admin_dependencies', $this->inflector->tableize('adminDependency'));
        self::assertSame('admin_dependencies', $this->inflector->tableize('admin-dependency'));
        self::assertSame('admin_dependencies', $this->inflector->tableize('admin_dependency'));
    }

    public function testClassify(): void
    {
        self::assertSame('Person', $this->inflector->classify('people'));
        self::assertSame('Page', $this->inflector->classify('pages'));
        self::assertSame('BlogPage', $this->inflector->classify('blog_pages'));
        self::assertSame('AdminDependency', $this->inflector->classify('admin_dependencies'));
    }

    public function testOrdinalize(): void
    {
        self::assertSame('1st', $this->inflector->ordinalize(1));
        self::assertSame('2nd', $this->inflector->ordinalize(2));
        self::assertSame('3rd', $this->inflector->ordinalize(3));
        self::assertSame('4th', $this->inflector->ordinalize(4));
        self::assertSame('5th', $this->inflector->ordinalize(5));
        self::assertSame('16th', $this->inflector->ordinalize(16));
        self::assertSame('51st', $this->inflector->ordinalize(51));
        self::assertSame('111th', $this->inflector->ordinalize(111));
        self::assertSame('123rd', $this->inflector->ordinalize(123));
    }

    public function testMonthize(): void
    {
        self::assertSame(0, $this->inflector->monthize(10));
        self::assertSame(1, $this->inflector->monthize(33));
        self::assertSame(1, $this->inflector->monthize(41));
        self::assertSame(11, $this->inflector->monthize(364));
    }
}
