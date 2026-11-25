<?php

use Codeception\Util\Fixtures;
use Grav\Common\Flex\Types\Pages\PageObject;
use Grav\Common\Grav;
use Grav\Framework\Flex\Flex;
use Grav\Framework\Flex\FlexDirectory;

/**
 * A Markdown-first Flex page hides its Twig tags from Markdown behind placeholders and puts them
 * back afterwards. A placeholder must decode to the tag it replaced and to nothing else: with
 * base64 between tokens that hold a `/`, the restore pattern ran from one placeholder through the
 * page text behind it into the next one and base64-decoded that text into raw HTML which neither
 * Markdown nor the save-time XSS checks had seen. Page::processMarkdown() uses hex, as does this.
 */
class FlexPageTwigPlaceholderTest extends \PHPUnit\Framework\TestCase
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

    /**
     * @dataProvider providePayloads
     */
    public function testTextBetweenTwigTagsIsNotDecoded(string $payload): void
    {
        // Neither the payload (a multiple of three bytes) nor `{{''}}` (six bytes) needs base64
        // padding, so nothing but the tokens stood between the placeholders and the text.
        self::assertSame(0, strlen($payload) % 3);
        $text = base64_encode($payload);

        // The tokens are random: render more than once.
        for ($i = 0; $i < 20; $i++) {
            $content = $this->markdown("{{''}}" . $text . "{{''}}");

            self::assertSame("<p>{{''}}" . $text . "{{''}}</p>", $content);
        }
    }

    public static function providePayloads(): array
    {
        return [
            'event handler' => ['<img src=x onerror=alert(document.domain)>'],
            'script element' => ['<script>alert(123)</script>'],
        ];
    }

    public function testHexTextBetweenTwigTagsIsNotDecoded(): void
    {
        $text = bin2hex('<img src=x onerror=alert(1)>');

        for ($i = 0; $i < 20; $i++) {
            $content = $this->markdown("{{''}}" . $text . "{{''}}");

            self::assertSame("<p>{{''}}" . $text . "{{''}}</p>", $content);
        }
    }

    public function testTwigTagsSurviveMarkdown(): void
    {
        $content = $this->markdown("**{{ 'a' }}**{{ 'b' }} {# *c* #} {% if x %}_y_{% endif %}");

        self::assertSame("<p><strong>{{ 'a' }}</strong>{{ 'b' }} {# *c* #} {% if x %}<em>y</em>{% endif %}</p>", $content);
    }

    public function testAdjacentTwigTagsAreNotReadAsMarkdown(): void
    {
        // Seven and thirteen bytes: as base64 each ended in `==`, and `==..==` is a highlight.
        $content = $this->markdown('{% if x %}{{ a }}{{ b }}{% endif %}');

        self::assertSame('<p>{% if x %}{{ a }}{{ b }}{% endif %}</p>', $content);
    }

    private function markdown(string $content): string
    {
        $page = new class(['header' => ['title' => 'Page'], 'markdown' => ''], 'placeholder', $this->directory) extends PageObject {
            public function keepTwigMarkdown(string $content): string
            {
                $options = [
                    'keep_twig' => true,
                    'markdown' => (array)Grav::instance()['config']->get('system.pages.markdown'),
                    'images' => [],
                ];

                return $this->processMarkdown($content, $options);
            }
        };

        return $page->keepTwigMarkdown($content);
    }
}
