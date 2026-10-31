<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use RocketTheme\Toolbox\Event\Event;


class GravFallbackUrlTest extends \Codeception\Test\Unit
{
    /** @var Grav */
    protected $grav;

    /** @var string|null File handed to `Utils::download()` */
    protected $served;

    /** @var callable */
    protected $listener;

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $this->grav = $grav();
        $this->grav['config']->set('system.media.allowed_fallback_types', ['jpg']);
        $this->grav['locator']->addPath('page', '', 'tests/fake/nested-site/user/pages', false);
        $this->grav['pages']->init();

        // `Utils::download()` ends the request, so stop it before it sends anything.
        $this->served = null;
        $this->listener = function (Event $event): void {
            $this->served = basename((string)$event['file']);
            throw new \RuntimeException('download');
        };
        $this->grav['events']->addListener('onBeforeDownload', $this->listener);
    }

    protected function tearDown(): void
    {
        $this->grav['events']->removeListener('onBeforeDownload', $this->listener);
        $this->grav['config']->set('system.media.allowed_fallback_types', []);
        parent::tearDown();
    }

    private function request(string $path): ?string
    {
        $uri = $this->grav['uri'];
        $uri->initializeWithURL('http://testing.dev' . $path)->init();

        try {
            $this->assertFalse($this->grav->fallbackUrl($uri->path()));
        } catch (\RuntimeException) {
        }

        return $this->served;
    }

    public function testAllowedTypeIsServed(): void
    {
        $this->assertSame('sample-image.jpg', $this->request('/item2/item2-2/sample-image.jpg'));
    }

    /**
     * @dataProvider refusedProvider
     */
    public function testTypeOutsideTheWhitelistIsRefused(string $basename): void
    {
        $this->assertNull($this->request('/item2/item2-2/' . $basename));
    }

    public function refusedProvider(): array
    {
        return [
            'plain' => ['existing-file.zip'],
            'encoded query' => ['existing-file.zip%3Fx.jpg'],
            'encoded fragment' => ['existing-file.zip%23x.jpg'],
        ];
    }
}
