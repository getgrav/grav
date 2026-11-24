<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Common\Page\Page;
use Grav\Framework\Flex\Flex;
use Grav\Framework\Flex\FlexDirectory;

/**
 * A page header can hold any type of value, but a getter returns the type it declares: a list or
 * an empty string in `taxonomy`, `lang` or `dateformat` is no taxonomy, language or format.
 */
class PageWrongTypedHeadersTest extends \PHPUnit\Framework\TestCase
{
    /** @var FlexDirectory */
    protected $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $grav = Fixtures::get('grav');
        /** @var Grav $grav */
        $grav = $grav();

        $flex = new Flex([], ['object' => $grav['config']->get('system.flex', [])]);
        $flex->addDirectoryType('pages', 'blueprints://flex/pages.yaml', ['enabled' => true]);

        $directory = $flex->getDirectory('pages');
        self::assertNotNull($directory);
        $this->directory = $directory;
    }

    public static function providerNotATaxonomy(): array
    {
        return [[''], [false], [0], ['0']];
    }

    /**
     * @dataProvider providerNotATaxonomy
     * @param mixed $value
     */
    public function testFlexTaxonomy_ValueThatIsNotAnArrayIsEmpty($value): void
    {
        self::assertSame([], $this->flexPage(['taxonomy' => $value])->taxonomy());
    }

    public function testFlexTaxonomy_ArrayIsKept(): void
    {
        self::assertSame(['tag' => ['grav', '5']], $this->flexPage(['taxonomy' => ['tag' => ['grav', 5]]])->taxonomy());
        self::assertSame(['tag' => ['grav']], $this->flexPage(['taxonomy' => (object)['tag' => 'grav']])->taxonomy());
    }

    public static function providerNotAString(): array
    {
        return [[[]], [['x']], [['a' => 'b']], [5], [true]];
    }

    /**
     * @dataProvider providerNotAString
     * @param mixed $value
     */
    public function testFlexDateformat_ValueThatIsNotAStringIsNull($value): void
    {
        self::assertNull($this->flexPage(['dateformat' => $value])->dateformat());
    }

    public function testFlexDateformat_StringIsKept(): void
    {
        self::assertSame('Y-m-d', $this->flexPage(['dateformat' => 'Y-m-d'])->dateformat());
    }

    /**
     * @dataProvider providerNotAString
     * @param mixed $value
     */
    public function testFlexLanguage_ValueThatIsNotAStringFallsBackToTheFile($value): void
    {
        $page = $this->flexPage(['lang' => $value]);

        self::assertNull($page->language());
    }

    public function testFlexLanguage_StringIsKept(): void
    {
        self::assertSame('fr', $this->flexPage(['lang' => ' fr '])->language());
    }

    /**
     * @dataProvider providerNotAString
     * @param mixed $value
     */
    public function testDateformat_ValueThatIsNotAStringIsNull($value): void
    {
        $page = new Page();
        $page->dateformat($value);

        self::assertNull($page->dateformat());
    }

    public function testDateformat_StringIsKept(): void
    {
        $page = new Page();
        $page->dateformat('Y-m-d');

        self::assertSame('Y-m-d', $page->dateformat());
    }

    protected function flexPage(array $header): \Grav\Common\Flex\Types\Pages\PageObject
    {
        $meta = ['key' => 'notes', 'storage_key' => 'notes', 'storage_timestamp' => 1];

        return $this->directory->createObject(['__META' => $meta, 'header' => ['title' => 'Notes'] + $header], 'notes');
    }
}
