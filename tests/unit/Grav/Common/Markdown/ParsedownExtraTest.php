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
}
