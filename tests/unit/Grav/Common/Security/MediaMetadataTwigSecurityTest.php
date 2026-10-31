<?php

use Codeception\Util\Fixtures;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use Grav\Common\Page\Interfaces\PageInterface;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

/**
 * Class MediaMetadataTwigSecurityTest
 *
 * The `<img>` Markdown renders for a medium takes its title, alt, class and id
 * from the medium's `.meta.yaml` sidecar (MediaObjectTrait::parsedownElement),
 * and the content Twig pass compiles the Markdown output next. A sidecar is
 * written by whoever may manage media (the API's `api.media.write`, or the Exif
 * importer), not by the page author, so a `{{ ... }}` in it must come out as
 * text and never run as Twig of the page that embeds the file.
 *
 * ParsedownGravTrait::inlineImage() renders the medium's element with the Twig
 * openers written as entities; the author's own Twig tags in the image are
 * placeholders during the Markdown pass and still run.
 */
class MediaMetadataTwigSecurityTest extends \PHPUnit\Framework\TestCase
{
    /** @var Grav */
    protected $grav;

    /** @var string */
    protected $folder;

    /** @var bool|null */
    protected $previousGate;

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $this->grav = $grav();

        $this->previousGate = $this->grav['config']->get('security.twig_content.process_enabled');
        $this->grav['config']->set('security.twig_content.process_enabled', true);

        $this->folder = 'tmp/media-meta-' . bin2hex(random_bytes(4));
        Folder::create(GRAV_ROOT . '/' . $this->folder . '/01.about');
        copy(GRAV_ROOT . '/system/images/media/thumb.png', GRAV_ROOT . '/' . $this->folder . '/01.about/team.png');
    }

    protected function tearDown(): void
    {
        $this->grav['config']->set('security.twig_content.process_enabled', $this->previousGate);
        Folder::delete(GRAV_ROOT . '/' . $this->folder);
        parent::tearDown();
    }

    public static function providerEmbeds(): array
    {
        return [
            'image' => ['![](team.png)'],
            'image in a link' => ['[![](team.png)](https://example.com)'],
            'text' => ['![](team.png?display=text)'],
            'reference' => ["![][team]\n\n[team]: team.png"],
        ];
    }

    /**
     * @dataProvider providerEmbeds
     */
    public function testSidecarMetadataIsNotCompiledAsTwig(string $embed): void
    {
        $content = $this->page($embed, [
            'title' => 't{{ 7*7 }}',
            'alt' => 'a{% if 1 %}49{% endif %}',
            'class' => 'c{# #}49',
            'id' => 'i{{{ 7*7 }}}',
        ])->content();

        // Nothing left that opens a Twig tag, and the browser still reads the text as written.
        self::assertDoesNotMatchRegularExpression('/{[{%#]/', $content);
        self::assertStringContainsString('t{{ 7*7 }}', html_entity_decode($content));
        self::assertStringContainsString('a{% if 1 %}49{% endif %}', html_entity_decode($content));
        self::assertStringContainsString('c{# #}49', html_entity_decode($content));
        self::assertStringContainsString('i{{{ 7*7 }}}', html_entity_decode($content));
        self::assertStringNotContainsString('t49', html_entity_decode($content));
    }

    public function testAltTextIsNotCompiledAsTwig(): void
    {
        $content = $this->page('![](team.png)', ['alt_text' => 'a{{ 7*7 }}'])->content();

        self::assertStringContainsString('alt="a&#123;{ 7*7 }}"', $content);
    }

    public function testTwigOfThePageAuthorStillRuns(): void
    {
        $content = $this->page('![{{ 6*7 }}](team.png?classes={{ 5*7 }} "{{ page.title }}") {{ 7*7 }}', [])->content();

        self::assertStringContainsString('alt="42"', $content);
        self::assertStringContainsString('class="35"', $content);
        self::assertStringContainsString('title="About"', $content);
        self::assertStringContainsString('49', $content);
    }

    public function testOrdinaryMetadataIsUnchanged(): void
    {
        $content = $this->page('![](team.png)', ['title' => 'The team {2026}', 'alt' => '100% of {us}'])->content();

        self::assertStringContainsString('title="The team {2026}"', $content);
        self::assertStringContainsString('alt="100% of {us}"', $content);
    }

    protected function page(string $content, array $metadata): PageInterface
    {
        $folder = GRAV_ROOT . '/' . $this->folder . '/01.about';
        file_put_contents($folder . '/default.md', "---\ntitle: About\ncache_enable: false\nprocess:\n  twig: true\n---\n" . $content);
        file_put_contents($folder . '/team.png.meta.yaml', json_encode($metadata, JSON_FORCE_OBJECT));

        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];
        $locator->addPath('theme', '', 'tests/fake/twig-first-site/user/themes/testing', false);
        $locator->addPath('page', '', $this->folder, false);
        $this->grav['pages']->init();
        $this->grav['twig']->init();

        $page = $this->grav['pages']->find('/about');
        self::assertInstanceOf(PageInterface::class, $page);

        return $page;
    }
}
