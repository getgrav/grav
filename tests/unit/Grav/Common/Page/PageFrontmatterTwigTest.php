<?php

use Codeception\Util\Fixtures;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use Grav\Common\Page\Page;

/**
 * Class PageFrontmatterTwigTest
 *
 * With `system.pages.frontmatter.process_twig` on, every frontmatter value is
 * rendered on its own, so what Twig prints is the text of that value. The JSON
 * of the whole header used to be rendered as one template and decoded again: a
 * value that printed a double quote made the header undecodable, which stopped
 * the pages build (and with it every request) with a TypeError, and a value
 * that printed `","key":"value` added keys to the header.
 */
class PageFrontmatterTwigTest extends \PHPUnit\Framework\TestCase
{
    /** @var Grav */
    protected $grav;

    /** @var string */
    protected $folder;

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $this->grav = $grav();
        $this->grav['config']->set('system.pages.frontmatter.process_twig', true);
        $this->grav['config']->set('system.pages.frontmatter.ignore_fields', ['form', 'forms']);
        $this->grav['config']->set('security.twig_content.process_enabled', true);
        $this->grav['locator']->addPath('theme', '', 'tests/fake/twig-first-site/user/themes/testing', false);
        $this->grav['twig']->init();

        $this->folder = sys_get_temp_dir() . '/grav-frontmatter-twig-' . bin2hex(random_bytes(6));
        Folder::create($this->folder);
    }

    protected function tearDown(): void
    {
        Folder::delete($this->folder);
        parent::tearDown();
    }

    public function testValuesAreRendered(): void
    {
        $header = $this->header(<<<'YAML'
            title: Sum
            note: "{{ 7 * 6 }} and {{ page.header.title }}"
            nested:
              list: ['{{ "a/b"|upper }}', plain]
              shown: "{% if page.header.count > 2 %}yes{% else %}no{% endif %}"
            count: 3
            flag: true
            empty: ~
            form:
              message: '{{ form.value.name }}'
            YAML);

        self::assertSame('42 and Sum', $header->note);
        self::assertSame(['list' => ['A/B', 'plain'], 'shown' => 'yes'], $header->nested);
        self::assertSame(3, $header->count);
        self::assertTrue($header->flag);
        self::assertNull($header->empty);
        self::assertSame(['message' => '{{ form.value.name }}'], $header->form);
    }

    public function testPrintedQuoteDoesNotBreakTheHeader(): void
    {
        $header = $this->header(<<<'YAML'
            title: Quote
            quote: "{{ '\"' }}"
            text: "one\ntwo"
            lines: "{{ page.header.text }}"
            YAML);

        self::assertSame('Quote', $header->title);
        self::assertSame('"', $header->quote);
        self::assertSame("one\ntwo", $header->lines);
    }

    public function testOutputIsTheTextOfItsValue(): void
    {
        $header = $this->header(<<<'YAML'
            title: Keys
            keys: "{{ '\",\"title\":\"INJECTED\",\"published\":false,\"x\":\"' }}"
            escapes: "{{ 'a\\\\u0041\\\\n' }}"
            YAML);

        self::assertSame('Keys', $header->title);
        self::assertSame('","title":"INJECTED","published":false,"x":"', $header->keys);
        self::assertSame('a\\u0041\\n', $header->escapes);
        self::assertSame(['title', 'keys', 'escapes'], array_keys((array)$header));
    }

    public function testHeaderWithoutOutputTagIsLeftAlone(): void
    {
        $header = $this->header(<<<'YAML'
            title: Plain
            note: "{% if true %}kept{% endif %}"
            YAML);

        self::assertSame('{% if true %}kept{% endif %}', $header->note);
    }

    protected function header(string $frontmatter): object
    {
        $name = 'page-' . count(glob($this->folder . '/*'));
        Folder::create($this->folder . '/' . $name);
        $file = $this->folder . '/' . $name . '/default.md';
        file_put_contents($file, "---\n" . $frontmatter . "\n---\nbody");

        $page = new Page();
        $page->init(new \SplFileInfo($file));

        return $page->header();
    }
}
