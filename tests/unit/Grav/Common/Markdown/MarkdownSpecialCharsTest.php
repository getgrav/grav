<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Common\Markdown\Parsedown;
use Grav\Common\Markdown\ParsedownExtra;
use Grav\Common\Page\Markdown\Excerpts;

/**
 * Class MarkdownSpecialCharsTest
 *
 * `markdown.special_chars` can be overridden from page frontmatter and its values
 * are written to the output as `&value;` by `inlineSpecialCharacter()`, so each
 * value has to name a character reference: a named one (`gt`), a decimal one
 * (`#62`) or a hex one (`#x3E`). Anything else is ignored and the character is
 * left as it is.
 */
class MarkdownSpecialCharsTest extends \PHPUnit\Framework\TestCase
{
    /** @var Grav $grav */
    protected $grav;

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $this->grav = $grav();
    }

    protected function render(string $text, array $markdown, bool $extra = false): string
    {
        $excerpts = new Excerpts(null, ['markdown' => $markdown + ['extra' => $extra], 'images' => []]);
        $parsedown = $extra ? new ParsedownExtra($excerpts) : new Parsedown($excerpts);

        return $parsedown->text($text);
    }

    public function testEntityNamesAndNumbersAreKept(): void
    {
        self::assertSame('<p>a &gt; b &lt; c</p>', $this->render('a > b < c', ['special_chars' => ['>' => 'gt', '<' => 'lt']]));
        self::assertSame('<p>a &quot;b&quot;</p>', $this->render('a "b"', ['special_chars' => ['"' => 'quot']]));
        self::assertSame('<p>a &#62; b</p>', $this->render('a > b', ['special_chars' => ['>' => '#62']]));
        self::assertSame('<p>a &#x3E; b</p>', $this->render('a > b', ['special_chars' => ['>' => '#x3E']]));
    }

    /**
     * @dataProvider providerInvalidEntities
     */
    public function testValueThatIsNotAnEntityIsIgnored($entity): void
    {
        self::assertSame('<p>a > b</p>', $this->render('a > b', ['special_chars' => ['>' => $entity]]));
        self::assertSame('<p>a > b</p>', $this->render('a > b', ['special_chars' => ['>' => $entity]], true));
    }

    public static function providerInvalidEntities(): array
    {
        return [
            'markup after entity' => ['gt;<b>x</b>'],
            'markup only' => ['<b>x</b>'],
            'terminated entity' => ['gt;'],
            'trailing newline' => ["gt\n"],
            'bad number' => ['#x3G'],
            'empty' => [''],
            'array' => [['gt']],
            'null' => [null],
        ];
    }

    public function testSetSpecialCharsFiltersTheMap(): void
    {
        $excerpts = new Excerpts(null, ['markdown' => [], 'images' => []]);
        $parsedown = new Parsedown($excerpts);
        $parsedown->setSpecialChars(['>' => 'gt', '<' => '<b>', '"' => 'quot;']);

        $property = new ReflectionProperty($parsedown, 'special_chars');
        $property->setAccessible(true);

        self::assertSame(['>' => 'gt'], $property->getValue($parsedown));
    }
}
