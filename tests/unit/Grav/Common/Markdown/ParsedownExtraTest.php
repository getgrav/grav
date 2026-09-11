<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Common\Page\Markdown\Excerpts;
use Grav\Common\Config\Config;
use Grav\Common\Page\Pages;
use Grav\Common\Markdown\ParsedownExtra;
use Grav\Common\Language\Language;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

/**
 * Class ParsedownExtraTest
 */
class ParsedownExtraTest extends \PHPUnit\Framework\TestCase
{
    /** @var ParsedownExtra $parsedown */
    protected $parsedown;

    /** @var Grav $grav */
    protected $grav;

    /** @var Pages $pages */
    protected $pages;

    /** @var Config $config */
    protected $config;

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $this->grav = $grav();
        $this->pages = $this->grav['pages'];
        $this->config = $this->grav['config'];
        $this->config->set('system.home.alias', '/item1');
        $this->config->set('system.absolute_urls', false);
        $this->config->set('system.languages.supported', []);

        unset($this->grav['language']);
        $this->grav['language'] = new Language($this->grav);

        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];
        $locator->addPath('page', '', 'tests/fake/nested-site/user/pages', false);
        $this->pages->init();

        $defaults = [
            'markdown' => [
                'extra'            => true,
                'auto_line_breaks' => false,
                'auto_url_links'   => false,
                'escape_markup'    => false,
                'special_chars'    => ['>' => 'gt', '<' => 'lt'],
            ],
            'images' => $this->config->get('system.images', [])
        ];
        $page = $this->pages->find('/item2/item2-2');

        $excerpts = new Excerpts($page, $defaults);
        $this->parsedown = new ParsedownExtra($excerpts);
    }

    /**
     * Fenced code with no info string is unaffected.
     */
    public function testFencedCodePlain(): void
    {
        self::assertSame(
            '<pre><code>code</code></pre>',
            $this->parsedown->text("```\ncode\n```")
        );
    }

    /**
     * A bare language token still becomes the language-* class.
     */
    public function testFencedCodeLanguageOnly(): void
    {
        self::assertSame(
            '<pre><code class="language-python">code</code></pre>',
            $this->parsedown->text("```python\ncode\n```")
        );
    }

    /**
     * Language plus a trailing {#id .class} block: the language becomes the
     * language-* class and the attribute block adds id + classes. Previously
     * this produced a broken class="language-{.foo".
     */
    public function testFencedCodeLanguageWithAttributes(): void
    {
        self::assertSame(
            '<pre><code id="c" class="language-python foo">code</code></pre>',
            $this->parsedown->text("```python {#c .foo}\ncode\n```")
        );
    }

    /**
     * Tilde fence with only an attribute block: `.python` is a literal class,
     * not a language.
     */
    public function testFencedCodeTildeAttributesOnly(): void
    {
        self::assertSame(
            '<pre><code id="c" class="python">code</code></pre>',
            $this->parsedown->text("~~~ {.python #c}\ncode\n~~~")
        );
    }

    /**
     * Multiple classes alongside a language.
     */
    public function testFencedCodeLanguageWithMultipleClasses(): void
    {
        self::assertSame(
            '<pre><code class="language-js a b">code</code></pre>',
            $this->parsedown->text("```js {.a .b}\ncode\n```")
        );
    }

    /**
     * Regression guard: header {#id .class} attributes still work in Extra.
     */
    public function testHeaderAttributesStillWork(): void
    {
        self::assertSame(
            '<h1 id="myid" class="a b">Title</h1>',
            $this->parsedown->text('# Title {#myid .a .b}')
        );
    }

    /**
     * Raw HTML blocks without `markdown="1"` come out exactly as written, the
     * same as with Markdown Extra off. They used to be reserialized through
     * DOMDocument, which dropped everything after a block's first element
     * (#4291), URL-encoded Twig in href/src, rewrote SVG, voids and entities,
     * and threw a TypeError on `<html>` markup.
     *
     * @dataProvider rawHtmlBlockProvider
     */
    public function testRawHtmlBlockIsPassedThroughAsWritten(string $markdown): void
    {
        self::assertSame($markdown, $this->parsedown->text($markdown));
    }

    public static function rawHtmlBlockProvider(): array
    {
        return [
            'nested close tags share a line (#4291)' => ["<div class=\"box\">\n<div>inner\n</div></div>\n\n<p>After the box</p>\n\nA **markdown** paragraph."],
            'elements on one line' => ['<div>1</div><p>2</p>'],
            'text after the closing tag' => ['<div></div> text after'],
            'twig in href and src' => ["<div class=\"gallery\">\n<a href=\"{{ page.url }}\"><img src=\"{{ page.media['a b.jpg'].url }}\" /></a>\n</div>"],
            'self-closing voids and boolean attributes' => ["<div>\n<img src=\"a.png\" alt=\"x\" />\n<input type=\"checkbox\" disabled=\"\" checked />\n</div>"],
            'svg attribute and element case' => ["<svg viewBox=\"0 0 24 24\">\n<linearGradient id=\"g\"><stop offset=\"0\"/></linearGradient>\n</svg>"],
            'entities' => ["<div>\n&copy; &nbsp; &#8217; &hellip;\n</div>"],
            'source inside video' => ["<video controls>\n<source src=\"a.webm\" type=\"video/webm\">\nSorry.\n</video>"],
            'backslash in src' => ["<figure>\n<img src=\"images\\totalen.png\">\n</figure>"],
            'data-markdown is not the markdown attribute' => ["<div data-markdown=\"1\">\n**x**\n</div>"],
            'html document markup' => ["<html lang=\"en\">\n<head>\n<meta charset=\"utf-8\" />\n</head>\n<body>x</body>\n</html>"],
            'markdown attribute on a text-level element inside a block' => ["<div class=\"x\"><span markdown=\"1\">**a**</span></div>"],
        ];
    }

    /**
     * `markdown="1"` still renders the Markdown inside the block, and an
     * element or text after the block's closing tag is no longer dropped.
     *
     * @dataProvider markdownAttributeProvider
     */
    public function testMarkdownAttributeRendersMarkdown(string $markdown, string $expected): void
    {
        self::assertSame($expected, $this->parsedown->text($markdown));
    }

    public static function markdownAttributeProvider(): array
    {
        return [
            'quoted' => ["<div markdown=\"1\">\n**bold**\n</div>", "<div>\n<p><strong>bold</strong></p>\n</div>"],
            'unquoted' => ["<div markdown=1>\n**bold**\n</div>", "<div>\n<p><strong>bold</strong></p>\n</div>"],
            'nested in a plain block' => ["<div class=\"outer\">\n<div markdown=\"1\">\n**inner**\n</div>\n</div>", "<div class=\"outer\">\n<div>\n<p><strong>inner</strong></p>\n</div>\n</div>"],
            'element after the closing tag' => ["<div markdown=\"1\">**a**</div><p>b</p>", "<div>\n<p><strong>a</strong></p>\n</div><p>b</p>"],
            'text after the closing tag' => ["<div markdown=\"1\">\n**a**\n</div> tail text", "<div>\n<p><strong>a</strong></p>\n</div> tail text"],
        ];
    }

    /**
     * The Markdown inside a `markdown="1"` element is rendered as the author
     * wrote it. It used to be rebuilt from a DOM first, which lowercased tags
     * in fenced code (#1840), turned `>` into `&gt;` (#3204), escaped `&` a
     * second time (#764, #2590), broke autolinks (#287), made `<source>` wrap
     * what follows it (#1168), dropped text after a `<`, URL-encoded Twig in
     * attributes and lowercased SVG.
     *
     * @dataProvider markdownAsWrittenProvider
     */
    public function testMarkdownAttributeRendersTheMarkdownAsWritten(string $markdown, string $expected): void
    {
        self::assertSame($expected, $this->parsedown->text($markdown));
    }

    public static function markdownAsWrittenProvider(): array
    {
        return [
            'fenced code keeps its case and tags (#1840)' => ["<div markdown='1'>\n```\n  <Foo>\n     <Bar />\n  </Foo>\n```\n</div>", "<div>\n<pre><code>  &lt;Foo&gt;\n     &lt;Bar /&gt;\n  &lt;/Foo&gt;</code></pre>\n</div>"],
            'blockquote (#3204)' => ["<div class=\"accordion-body\" markdown=\"1\">\n> This should be a blockquote\n</div>", "<div class=\"accordion-body\">\n<blockquote>\n<p>This should be a blockquote</p>\n</blockquote>\n</div>"],
            'ampersands in a code span are escaped once (#764, #2590)' => ["<div markdown=\"1\">\n`a && b`\n</div>", "<div>\n<p><code>a &amp;&amp; b</code></p>\n</div>"],
            'entities are kept as written (#764, #2590)' => ["<div markdown=\"1\">\nliam&commat;example&period;com &copy;\n</div>", "<div>\n<p>liam&commat;example&period;com &copy;</p>\n</div>"],
            'autolinks (#287)' => ["<div markdown=\"1\">\n<http://example.com/> and <address@example.com>\n</div>", "<div>\n<p><a href=\"http://example.com/\">http://example.com/</a> and <a href=\"mailto:address@example.com\">address@example.com</a></p>\n</div>"],
            'source stays empty inside picture (#1168)' => ["<figure><picture markdown=\"1\">\n<source srcset=\"image.webp\" type=\"image/webp\">\n![image-title](/link/image.jpg) {.class}\n</picture></figure>", "<figure><picture>\n<source srcset=\"image.webp\" type=\"image/webp\">\n<p><img src=\"/link/image.jpg\" alt=\"image-title\" class=\"class\" /></p>\n</picture></figure>"],
            'a less-than sign in text is kept' => ["<div markdown=\"1\">\nif a < b\n</div>", "<div>\n<p>if a &lt; b</p>\n</div>"],
            'a tag in a code span does not nest' => ["<div markdown=\"1\">\nUse `<div>` to wrap.\n</div>\n\nAfter **md**.", "<div>\n<p>Use <code>&lt;div&gt;</code> to wrap.</p>\n</div>\n<p>After <strong>md</strong>.</p>"],
            'an end tag in a script does not end the element' => ["<div markdown=\"1\">\n<script>var s = '</div>';</script>\n**a**\n</div>", "<div>\n&lt;script>var s = '</div>';&lt;/script>\n<p><strong>a</strong></p>\n</div>"],
            'twig in an element after the block' => ["<div markdown=\"1\">**a**</div><a href=\"{{ page.url }}\">x</a>", "<div>\n<p><strong>a</strong></p>\n</div><a href=\"{{ page.url }}\">x</a>"],
            'svg keeps its case' => ["<div markdown=\"1\">\n<svg viewBox=\"0 0 24 24\"><linearGradient id=\"g\"/></svg>\n\n**a**\n</div>", "<div>\n<svg viewBox=\"0 0 24 24\"><linearGradient id=\"g\"/></svg>\n<p><strong>a</strong></p>\n</div>"],
            'opening tag as written apart from the attribute' => ["<div class=\"{{ page.header.cls }}\" data-x='1' markdown=\"1\" id=a>\n**a**\n</div>", "<div class=\"{{ page.header.cls }}\" data-x='1' id=a>\n<p><strong>a</strong></p>\n</div>"],
            'script content is left alone' => ["<script markdown=\"1\">\nvar a = 1;\n</script>", "&lt;script>\nvar a = 1;\n&lt;/script>"],
            'nested markdown elements' => ["<div markdown=\"1\">\n**a**\n<div class=\"plain\">\n<div markdown=\"1\">\n**b**\n</div>\n</div>\n</div>", "<div>\n<p><strong>a</strong></p>\n<div class=\"plain\">\n<div>\n<p><strong>b</strong></p>\n</div>\n</div>\n</div>"],
            'an element with no end tag falls back to the DOM' => ["<div markdown=\"1\">\n**a**\n\nMore **text**.", "<div>\n<p><strong>a</strong></p>\n<p>More <strong>text</strong>.</p>\n</div>"],
        ];
    }

    /**
     * Image actions and classes come out the same inside a `markdown="1"`
     * element as outside it (#764, #2590).
     *
     * @dataProvider imageActionProvider
     */
    public function testImageActionsInsideMarkdownAttributeMatchOutside(string $image): void
    {
        $html = $this->parsedown->text("{$image}\n\n<div markdown=\"1\">\n{$image}\n</div>");

        self::assertSame(1, preg_match('#^<p>(.+?)</p>\n<div>\n<p>(.+?)</p>\n</div>$#s', $html, $matches), $html);
        self::assertSame($matches[1], $matches[2]);
    }

    public static function imageActionProvider(): array
    {
        return [
            'resize and classes (#2590)' => ['![Life buoy](sample-image.jpg?resize=250,250&classes=content-intro-img,u-center)'],
            'lightbox and crop (#764)' => ['![Sample Image](sample-image.jpg?lightbox=1024&crop=100,100,600,600)'],
        ];
    }

    /**
     * A `markdown="1"` block no longer clears the page's reference, footnote
     * and abbreviation definitions. Its content was rendered through text(),
     * which starts by clearing them, so a definition written above the block
     * stopped working for the whole page, and a link inside the block found
     * none of the page's definitions. A definition list item with two
     * paragraphs went through text() too, and had the same problem.
     *
     * @dataProvider definitionsAcrossMarkdownBlockProvider
     */
    public function testDefinitionsWorkAcrossAMarkdownBlock(string $markdown, string $expected): void
    {
        self::assertSame($expected, $this->parsedown->text($markdown));
    }

    public static function definitionsAcrossMarkdownBlockProvider(): array
    {
        return [
            'definition above the block, link below it' => ["[r]: https://example.com/r\n\n<div markdown=\"1\">\n**x**\n</div>\n\nAfter [link][r].", "<div>\n<p><strong>x</strong></p>\n</div>\n<p>After <a href=\"https://example.com/r\">link</a>.</p>"],
            'link inside the block, definition below it' => ["<div markdown=\"1\">\nInside [link][r].\n</div>\n\n[r]: https://example.com/r", "<div>\n<p>Inside <a href=\"https://example.com/r\">link</a>.</p>\n</div>"],
            'link inside the block, definition above it' => ["[r]: https://example.com/r\n\n<div markdown=\"1\">\nInside [link][r] and [r][].\n</div>", "<div>\n<p>Inside <a href=\"https://example.com/r\">link</a> and <a href=\"https://example.com/r\">r</a>.</p>\n</div>"],
            'definition inside the block, links above and below it' => ["Above [link][r].\n\n<div markdown=\"1\">\n[r]: https://example.com/r\n\nInside.\n</div>\n\nBelow [link][r].", "<p>Above <a href=\"https://example.com/r\">link</a>.</p>\n<div>\n<p>Inside.</p>\n</div>\n<p>Below <a href=\"https://example.com/r\">link</a>.</p>"],
            'nested markdown elements' => ["<div markdown=\"1\">\nA [one][r].\n<div class=\"x\">\n<div markdown=\"1\">\nB [two][r].\n</div>\n</div>\n</div>\n\n[r]: https://example.com/r", "<div>\n<p>A <a href=\"https://example.com/r\">one</a>.</p>\n<div class=\"x\">\n<div>\n<p>B <a href=\"https://example.com/r\">two</a>.</p>\n</div>\n</div>\n</div>"],
            'element with no end tag goes through the DOM' => ["[r]: https://example.com/r\n\n<div markdown=\"1\">\nInside [link][r].", "<div>\n<p>Inside <a href=\"https://example.com/r\">link</a>.</p>\n</div>"],
            'reference image' => ["<div markdown=\"1\">\n![Alt][img]\n</div>\n\n[img]: /a.png", "<div>\n<p><img src=\"/a.png\" alt=\"Alt\" /></p>\n</div>"],
            'abbreviation defined outside the block' => ["*[HTML]: Hyper Text Markup Language\n\nHTML outside.\n\n<div markdown=\"1\">\nHTML inside.\n</div>", "<p><abbr title=\"Hyper Text Markup Language\">HTML</abbr> outside.</p>\n<div>\n<p><abbr title=\"Hyper Text Markup Language\">HTML</abbr> inside.</p>\n</div>"],
            'abbreviation defined inside the block' => ["HTML above.\n\n<div markdown=\"1\">\n*[HTML]: Hyper Text Markup Language\n\nHTML inside.\n</div>", "<p><abbr title=\"Hyper Text Markup Language\">HTML</abbr> above.</p>\n<div>\n<p><abbr title=\"Hyper Text Markup Language\">HTML</abbr> inside.</p>\n</div>"],
            'definition list item with two paragraphs' => ["[r]: https://example.com/r\n\nTerm\n: First [link][r].\n\n    Second [link][r].\n\nAfter [link][r].", "<dl>\n<dt>Term</dt>\n<dd><p>First <a href=\"https://example.com/r\">link</a>.</p>\n<p>Second <a href=\"https://example.com/r\">link</a>.</p></dd>\n</dl>\n<p>After <a href=\"https://example.com/r\">link</a>.</p>"],
        ];
    }

    /**
     * Footnotes inside and around a `markdown="1"` block share the page's one
     * footnote list and are numbered in reading order. The block used to get
     * a footnote list of its own, and a marker in it could not find a
     * definition written below it (erusev/parsedown-extra#72).
     *
     * @dataProvider footnotesAcrossMarkdownBlockProvider
     */
    public function testFootnotesAcrossAMarkdownBlock(string $markdown, string $expected): void
    {
        self::assertSame($expected, $this->parsedown->text($markdown));
    }

    public static function footnotesAcrossMarkdownBlockProvider(): array
    {
        return [
            'marker inside the block, definition below it' => ["<div markdown=\"1\">\nInside.[^a]\n</div>\n\n[^a]: Note A.", "<div>\n<p>Inside.<sup id=\"fnref1:a\"><a href=\"#fn:a\" class=\"footnote-ref\">1</a></sup></p>\n</div>\n<div class=\"footnotes\">\n<hr />\n<ol>\n<li id=\"fn:a\">\n<p>Note A.&#160;<a href=\"#fnref1:a\" rev=\"footnote\" class=\"footnote-backref\">&#8617;</a></p>\n</li>\n</ol>\n</div>"],
            'marker above the block, definition inside it' => ["Above.[^a]\n\n<div markdown=\"1\">\n[^a]: Note A.\n\nInside.\n</div>", "<p>Above.<sup id=\"fnref1:a\"><a href=\"#fn:a\" class=\"footnote-ref\">1</a></sup></p>\n<div>\n<p>Inside.</p>\n</div>\n<div class=\"footnotes\">\n<hr />\n<ol>\n<li id=\"fn:a\">\n<p>Note A.&#160;<a href=\"#fnref1:a\" rev=\"footnote\" class=\"footnote-backref\">&#8617;</a></p>\n</li>\n</ol>\n</div>"],
            'numbered in reading order across the block' => ["One[^1].\n\n<div markdown=\"1\">\nTwo[^2].\n</div>\n\nThree[^3].\n\n[^1]: N1\n[^2]: N2\n[^3]: N3", "<p>One<sup id=\"fnref1:1\"><a href=\"#fn:1\" class=\"footnote-ref\">1</a></sup>.</p>\n<div>\n<p>Two<sup id=\"fnref1:2\"><a href=\"#fn:2\" class=\"footnote-ref\">2</a></sup>.</p>\n</div>\n<p>Three<sup id=\"fnref1:3\"><a href=\"#fn:3\" class=\"footnote-ref\">3</a></sup>.</p>\n<div class=\"footnotes\">\n<hr />\n<ol>\n<li id=\"fn:1\">\n<p>N1&#160;<a href=\"#fnref1:1\" rev=\"footnote\" class=\"footnote-backref\">&#8617;</a></p>\n</li>\n<li id=\"fn:2\">\n<p>N2&#160;<a href=\"#fnref1:2\" rev=\"footnote\" class=\"footnote-backref\">&#8617;</a></p>\n</li>\n<li id=\"fn:3\">\n<p>N3&#160;<a href=\"#fnref1:3\" rev=\"footnote\" class=\"footnote-backref\">&#8617;</a></p>\n</li>\n</ol>\n</div>"],
            'marker and definition inside the block' => ["<div markdown=\"1\">\nInside.[^x]\n\n[^x]: Inner note.\n</div>\n\nAfter.", "<div>\n<p>Inside.<sup id=\"fnref1:x\"><a href=\"#fn:x\" class=\"footnote-ref\">1</a></sup></p>\n</div>\n<p>After.</p>\n<div class=\"footnotes\">\n<hr />\n<ol>\n<li id=\"fn:x\">\n<p>Inner note.&#160;<a href=\"#fnref1:x\" rev=\"footnote\" class=\"footnote-backref\">&#8617;</a></p>\n</li>\n</ol>\n</div>"],
        ];
    }

    /**
     * Markup that already holds the character used to mark held elements is
     * rendered where it stands: the character is kept, and the page's
     * definitions still apply.
     */
    public function testHoldCharacterInAMarkdownBlockIsKept(): void
    {
        self::assertSame(
            "<div>\n<p>\x1A0\x1A <a href=\"https://example.com/r\">link</a></p>\n</div>",
            $this->parsedown->text("[r]: https://example.com/r\n\n<div markdown=\"1\">\n\x1A0\x1A [link][r]\n</div>")
        );
    }

    /**
     * Each page still starts with no definitions: a second page rendered on
     * the same parser does not see the first page's.
     */
    public function testDefinitionsDoNotCarryOverToTheNextPage(): void
    {
        self::assertSame(
            "<div>\n<p><a href=\"https://example.com/r\">link</a></p>\n</div>",
            $this->parsedown->text("[r]: https://example.com/r\n\n<div markdown=\"1\">\n[link][r]\n</div>")
        );
        self::assertSame('<p>[link][r]</p>', $this->parsedown->text('[link][r]'));
    }
}
