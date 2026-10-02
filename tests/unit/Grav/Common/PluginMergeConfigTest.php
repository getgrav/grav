<?php

use Codeception\Util\Fixtures;
use Grav\Common\Config\Config;
use Grav\Common\Data\Data;
use Grav\Common\Grav;
use Grav\Common\Page\Interfaces\PageInterface;
use Grav\Common\Page\Page;
use Grav\Common\Plugin;

/**
 * Class PluginMergeConfigTest
 *
 * Plugin::mergeConfig() returns a Data object, and plugins have long stored
 * that straight back in the config:
 *
 *     $this->config->set('plugins.archives', $this->mergeConfig($page));
 *
 * A handler that does so and runs twice in one request (onTwigSiteVariables
 * fires once for a `.md` page and again for the HTML render behind it) reads
 * that Data object back as its defaults on the second run.
 */
class PluginMergeConfigTest extends \PHPUnit\Framework\TestCase
{
    protected const NAME = 'merge-config-test';

    /** @var Grav */
    protected $grav;

    /** @var Config */
    protected $config;

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $this->grav = $grav();
        $this->config = $this->grav['config'];
        $this->config->set('plugins.' . self::NAME, [
            'enabled' => true,
            'limit' => 12,
            'order' => ['by' => 'date', 'dir' => 'desc'],
        ]);
    }

    protected function tearDown(): void
    {
        $this->config->set('plugins.' . self::NAME, null);
        parent::tearDown();
    }

    /**
     * @return array<string, array{0: mixed, 1: array, 2: array}>
     */
    public static function providerMergeModes(): array
    {
        $defaults = ['enabled' => true, 'limit' => 12, 'order' => ['by' => 'date', 'dir' => 'desc']];

        return [
            'shallow, no page settings' => [false, [], $defaults],
            'recursive, no page settings' => [true, [], $defaults],
            'recursive unique, no page settings' => ['merge', [], $defaults],
            'shallow, page settings' => [false, ['limit' => 5], ['limit' => 5] + $defaults],
            'recursive, page settings' => [true, ['order' => ['dir' => 'asc']], ['order' => ['by' => 'date', 'dir' => 'asc']] + $defaults],
            'recursive unique, page settings' => ['merge', ['order' => ['dir' => 'asc']], ['order' => ['by' => 'date', 'dir' => 'asc']] + $defaults],
        ];
    }

    /**
     * @param mixed $deep
     * @param array $pageSettings
     * @param array $expected
     * @dataProvider providerMergeModes
     */
    public function testMergedConfigStoredInTheConfigCanBeMergedAgain($deep, array $pageSettings, array $expected): void
    {
        $plugin = $this->plugin();
        $page = $this->page($pageSettings);

        $first = $plugin->merged($page, $deep);
        self::assertInstanceOf(Data::class, $first);
        self::assertEquals($expected, $first->toArray());

        // What the archives, simplesearch, tntsearch and star-ratings plugins do.
        $this->config->set('plugins.' . self::NAME, $first);

        $second = $plugin->merged($page, $deep);
        self::assertEquals($expected, $second->toArray());
    }

    /**
     * @param mixed $deep
     * @param array $pageSettings
     * @param array $expected
     * @dataProvider providerMergeModes
     */
    public function testMergedConfigStoredInTheConfigServesAnotherPage($deep, array $pageSettings, array $expected): void
    {
        $plugin = $this->plugin();

        // A first page with no settings of its own leaves the defaults behind as a Data object.
        $this->config->set('plugins.' . self::NAME, $plugin->merged($this->page([]), $deep));

        $merged = $plugin->merged($this->page($pageSettings), $deep, ['extra' => 1]);
        self::assertEquals($expected + ['extra' => 1], $merged->toArray());
    }

    /**
     * @return Plugin
     */
    protected function plugin(): Plugin
    {
        return new class(self::NAME, $this->grav, $this->config) extends Plugin {
            public function merged(PageInterface $page, $deep = false, array $params = []): Data
            {
                return $this->mergeConfig($page, $deep, $params);
            }
        };
    }

    /**
     * @param array $settings The page's own settings for the plugin, if any
     * @return PageInterface
     */
    protected function page(array $settings): PageInterface
    {
        $header = ['title' => 'Merge Config'];
        if ($settings) {
            $header[self::NAME] = $settings;
        }

        $page = new Page();
        $page->header($header);

        return $page;
    }
}
