<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Common\Markdown\Parsedown;
use Grav\Common\Page\Markdown\Excerpts;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

/**
 * Class GfmFeaturesTest
 *
 * Covers the GitHub Flavored Markdown built-ins (default on): highlight/sub/
 * superscript marks, task lists, and the tagfilter denylist.
 */
class GfmFeaturesTest extends \PHPUnit\Framework\TestCase
{
    /** @var Grav $grav */
    protected $grav;

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $this->grav = $grav();
        $this->grav['config']->set('system.languages.supported', []);

        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];
        $locator->addPath('page', '', 'tests/fake/nested-site/user/pages', false);
        $this->grav['pages']->init();
    }

    protected function parser(array $gfm = []): Parsedown
    {
        $markdown = ['extra' => false];
        if ($gfm !== []) {
            $markdown['gfm'] = $gfm;
        }
        $page = $this->grav['pages']->find('/item2/item2-2');

        return new Parsedown(new Excerpts($page, ['markdown' => $markdown, 'images' => []]));
    }

    public function testHighlight(): void
    {
        self::assertSame('<p>a <mark>b c</mark> d</p>', $this->parser()->text('a ==b c== d'));
    }

    public function testSubscript(): void
    {
        self::assertSame('<p>H<sub>2</sub>O</p>', $this->parser()->text('H~2~O'));
    }

    public function testSuperscript(): void
    {
        self::assertSame('<p>X<sup>2</sup></p>', $this->parser()->text('X^2^'));
    }

    /**
     * Strikethrough (double tilde) must still win over subscript (single tilde).
     */
    public function testStrikethroughStillWorks(): void
    {
        self::assertSame('<p><del>struck</del></p>', $this->parser()->text('~~struck~~'));
    }

    public function testTaskListUnchecked(): void
    {
        $html = $this->parser()->text("- [ ] todo");
        self::assertStringContainsString('<li class="task-list-item"><input type="checkbox" disabled="" /> todo</li>', $html);
    }

    public function testTaskListChecked(): void
    {
        $html = $this->parser()->text("- [x] done");
        self::assertStringContainsString('<input type="checkbox" disabled="" checked="" /> done', $html);
    }

    public function testTagfilterEscapesScript(): void
    {
        $html = $this->parser()->text('ok <script>alert(1)</script> end');
        self::assertStringContainsString('&lt;script>', $html);
        self::assertStringNotContainsString('<script>', $html);
    }

    /**
     * Marks can be disabled via config; the markers then render literally.
     */
    public function testMarksCanBeDisabled(): void
    {
        self::assertSame('<p>a ==b== d</p>', $this->parser(['marks' => false])->text('a ==b== d'));
    }

    /**
     * Task lists can be disabled via config.
     */
    public function testTaskListsCanBeDisabled(): void
    {
        $html = $this->parser(['task_lists' => false])->text('- [ ] todo');
        self::assertStringContainsString('<li>[ ] todo</li>', $html);
    }

    public function testWwwAutolink(): void
    {
        self::assertSame(
            '<p>see <a href="http://www.example.com">www.example.com</a> now</p>',
            $this->parser()->text('see www.example.com now')
        );
    }

    public function testEmailAutolink(): void
    {
        self::assertSame(
            '<p>mail <a href="mailto:foo@example.com">foo@example.com</a> please</p>',
            $this->parser()->text('mail foo@example.com please')
        );
    }

    /**
     * Trailing sentence punctuation stays outside the link (GFM behavior).
     */
    public function testAutolinkTrimsTrailingPunctuation(): void
    {
        self::assertSame(
            '<p>visit www.example.com.</p>',
            str_replace(
                '<a href="http://www.example.com">www.example.com</a>',
                'www.example.com',
                $this->parser()->text('visit www.example.com.')
            )
        );
        // and the link itself excludes the trailing dot
        self::assertStringContainsString('<a href="http://www.example.com">www.example.com</a>.', $this->parser()->text('visit www.example.com.'));
    }

    /**
     * Autolinking must not touch URLs already inside an explicit markdown link.
     */
    public function testExplicitLinkNotDoubleLinked(): void
    {
        self::assertSame(
            '<p><a href="http://www.example.com">site</a></p>',
            $this->parser()->text('[site](http://www.example.com)')
        );
    }

    /**
     * Autolinks can be disabled via config.
     */
    public function testAutolinksCanBeDisabled(): void
    {
        self::assertSame('<p>see www.example.com now</p>', $this->parser(['autolinks' => false])->text('see www.example.com now'));
    }
}
