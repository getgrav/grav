<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Common\Page\Medium\ImageMedium;
use Grav\Common\Page\Medium\MediumFactory;
use RocketTheme\Toolbox\Event\Event;

/**
 * `onImageMediumReset` fires once an image is back to its original, so a plugin can queue
 * actions on every image, for example from the image's own metadata.
 */
class ImageMediumResetEventTest extends \Codeception\Test\Unit
{
    /** @var Grav */
    protected $grav;

    /** @var string */
    protected $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $this->grav = $grav();

        $this->dir = sys_get_temp_dir() . '/grav-reset-event-' . uniqid('', true);
        mkdir($this->dir);
        $image = imagecreatetruecolor(400, 200);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        imagepng($image, $this->dir . '/source.png');
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir . '/*'));
        rmdir($this->dir);
        parent::tearDown();
    }

    public function testEventFiresWithTheMedium(): void
    {
        $seen = [];
        $listener = static function (Event $event) use (&$seen) {
            $seen[] = $event['medium'];
        };
        $this->grav['events']->addListener('onImageMediumReset', $listener);

        try {
            $medium = MediumFactory::fromFile($this->dir . '/source.png');
        } finally {
            $this->grav['events']->removeListener('onImageMediumReset', $listener);
        }

        $this->assertInstanceOf(ImageMedium::class, $medium);
        $this->assertContains($medium, $seen);
    }

    public function testListenerCanQueueAnAction(): void
    {
        $plain = MediumFactory::fromFile($this->dir . '/source.png')->resize(100, 50)->url();

        $listener = static function (Event $event) {
            $event['medium']->resize(100, 50);
        };
        $this->grav['events']->addListener('onImageMediumReset', $listener);

        try {
            $queued = MediumFactory::fromFile($this->dir . '/source.png')->url();
        } finally {
            $this->grav['events']->removeListener('onImageMediumReset', $listener);
        }

        $this->assertSame($plain, $queued);
    }

    public function testNoListenerChangesNothing(): void
    {
        $medium = MediumFactory::fromFile($this->dir . '/source.png');

        $this->assertInstanceOf(ImageMedium::class, $medium);
        $this->assertStringNotContainsString('?', $medium->url());
    }
}
