<?php

use Codeception\Util\Fixtures;
use Grav\Common\Markdown\Parsedown;
use Grav\Common\Markdown\ParsedownExtra;
use Grav\Common\Page\Markdown\Excerpts;

/**
 * Class MarkdownEscapeMarkupTest
 *
 * `markdown.escape_markup: true` shows raw HTML as text. Parsedown's own raw
 * paths (blockMarkup(), blockComment(), inlineMarkup()) check the flag; the two
 * places where Grav's trait emits markup of its own have to honour it as well:
 *
 *  - blockTwigTag() passes a line that starts with a Twig tag through as
 *    written, including any HTML that shares the line with the tag.
 *  - inlineSpecialCharacter() escapes `<` / `>` through `markdown.special_chars`,
 *    a map that a page header can replace.
 */
class MarkdownEscapeMarkupTest extends \PHPUnit\Framework\TestCase
{
    private const HTML = '<img src=x title=y>';
    private const ESCAPED = '&lt;img src=x title=y&gt;';

    private function render(string $text, array $markdown, bool $extra = false): string
    {
        $grav = Fixtures::get('grav');
        $grav();

        $excerpts = new Excerpts(null, ['markdown' => $markdown + ['extra' => $extra], 'images' => []]);

        return ($extra ? new ParsedownExtra($excerpts) : new Parsedown($excerpts))->text($text);
    }

    public function testTwigTagLineIsStillARawBlockByDefault(): void
    {
        $line = '{% if x %}<b>y</b>{% endif %}';

        self::assertSame($line, $this->render($line, ['escape_markup' => false]));
        self::assertSame($line, $this->render($line, []));
    }

    /**
     * @dataProvider providerTwigTags
     */
    public function testHtmlOnATwigTagLineIsEscaped(string $tag): void
    {
        foreach ([false, true] as $extra) {
            self::assertSame(
                '<p>' . $tag . self::ESCAPED . '</p>',
                $this->render($tag . self::HTML, ['escape_markup' => true], $extra)
            );
        }
    }

    public static function providerTwigTags(): array
    {
        return [
            'comment' => ['{# #}'],
            'output' => ['{{ x }}'],
            'statement' => ['{% x %}'],
            'mismatched' => ['{{ x #}'],
        ];
    }

    public function testTwigTagLinesDoNotLeaveATagOpenAcrossBlocks(): void
    {
        $html = $this->render("{# #}<img src=x title='\n\n[a](/x'>y)\n\n{# #}' alt=z>", ['escape_markup' => true]);

        self::assertStringNotContainsString('<img', $html);
    }

    /**
     * @dataProvider providerSpecialChars
     */
    public function testSpecialCharsMapDoesNotChangeTheEscaping(array $special_chars, string $text, string $expected): void
    {
        foreach ([false, true] as $extra) {
            self::assertSame(
                '<p>' . $expected . '</p>',
                $this->render($text, ['escape_markup' => true, 'special_chars' => $special_chars], $extra)
            );
        }
    }

    public static function providerSpecialChars(): array
    {
        return [
            'empty map' => [[], self::HTML, self::ESCAPED],
            'map without < and >' => [['"' => 'quot'], self::HTML, self::ESCAPED],
            'map with other values' => [['>' => '#62', '<' => '#60'], 'a > b', 'a &gt; b'],
        ];
    }

    public function testSpecialCharsMapIsStillUsedByDefault(): void
    {
        self::assertSame('<p>a &#62; "b"</p>', $this->render('a > "b"', ['special_chars' => ['>' => '#62']]));
    }
}
