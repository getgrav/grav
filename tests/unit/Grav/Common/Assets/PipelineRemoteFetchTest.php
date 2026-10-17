<?php

use Codeception\Util\Fixtures;
use Grav\Common\Assets;
use Grav\Common\Grav;

/**
 * A remote asset in the pipeline whose server accepts the connection but never answers used to
 * hold the request until max_execution_time ended it. The download now gives up after a short
 * timeout, and the bundle missing that asset is not cached.
 */
class PipelineRemoteFetchTest extends \Codeception\Test\Unit
{
    /** @var Grav */
    protected $grav;

    /** @var Assets */
    protected $assets;

    /** @var resource|null */
    private $server;

    /** @var int|float|null */
    private $originalTimeout;

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $this->grav = $grav();
        $this->assets = $this->grav['assets'];
        $this->assets->reset();

        $config = $this->grav['config'];
        $this->originalTimeout = $config->get('system.assets.pipeline_remote_timeout');
        $config->set('system.assets.pipeline_remote_timeout', 1);

        // Listens, but never accepts or answers: the connection is made and the read stalls.
        $this->server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
    }

    protected function tearDown(): void
    {
        $this->grav['config']->set('system.assets.pipeline_remote_timeout', $this->originalTimeout);

        if ($this->server) {
            fclose($this->server);
        }
    }

    private function stalledUrl(string $file): string
    {
        return 'http://' . stream_socket_get_name($this->server, false) . '/' . $file;
    }

    private function bundleFiles(string $extension): array
    {
        return glob($this->grav['locator']->findResource('asset://') . '/*.' . $extension) ?: [];
    }

    public function testStalledRemoteCssTimesOutAndFallsBackToSeparateTags(): void
    {
        $remote = $this->stalledUrl('stalled-' . uniqid() . '.css');
        $this->assets->setCssPipeline(true);
        $this->assets->addCss('/tests/unit/data/assets/valid.css');
        $this->assets->addCss($remote);

        $before = $this->bundleFiles('css');
        $start = microtime(true);
        $css = $this->assets->css();
        $elapsed = microtime(true) - $start;

        self::assertLessThan(10, $elapsed);
        self::assertStringContainsString('<link href="/tests/unit/data/assets/valid.css"', $css);
        self::assertStringContainsString('<link href="' . $remote . '"', $css);
        self::assertDoesNotMatchRegularExpression('#/assets/[a-f0-9]{32}\.css#', $css);
        self::assertSame($before, $this->bundleFiles('css'));
    }

    public function testStalledRemoteJsTimesOutAndFallsBackToSeparateTags(): void
    {
        $remote = $this->stalledUrl('stalled-' . uniqid() . '.js');
        $this->assets->setJsPipeline(true);
        $this->assets->addJs('/system/assets/jquery/jquery-3.x.min.js');
        $this->assets->addJs($remote);

        $before = $this->bundleFiles('js');
        $start = microtime(true);
        $js = $this->assets->js();
        $elapsed = microtime(true) - $start;

        self::assertLessThan(10, $elapsed);
        self::assertStringContainsString('<script src="/system/assets/jquery/jquery-3.x.min.js"', $js);
        self::assertStringContainsString('<script src="' . $remote . '"', $js);
        self::assertSame($before, $this->bundleFiles('js'));
    }

    public function testStalledRemoteInInlineGroupStillRendersTheRest(): void
    {
        $this->assets->setCssPipeline(true);
        $this->assets->addCss('/tests/unit/data/assets/valid.css');
        $this->assets->addCss($this->stalledUrl('stalled-' . uniqid() . '.css'));

        $before = $this->bundleFiles('css');
        $css = $this->assets->css('head', ['loading' => 'inline']);

        self::assertStringStartsWith('<style>', $css);
        self::assertMatchesRegularExpression('#\.valid\s*\{\s*color:\s*blue#', $css);
        self::assertSame($before, $this->bundleFiles('css'));
    }
}
