<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Common\Page\Medium\AudioMedium;
use Grav\Common\Page\Medium\VideoMedium;

class MediaPlayerAttributesTest extends \Codeception\Test\Unit
{
    /** @var Grav */
    protected $grav;

    /** @var string */
    protected $fixture = 'tests/fake/nested-site/user/pages/01.item1/home-sample-image.jpg';

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $this->grav = $grav();
    }

    public function testVideoMovesAlternativeTextToAriaLabel(): void
    {
        $element = $this->video()->parsedownElement(null, 'Video description');

        $this->assertSame('video', $element['name']);
        $this->assertArrayNotHasKey('alt', $element['attributes']);
        $this->assertSame('Video description', $element['attributes']['aria-label']);
        $this->assertSame('controls', $element['attributes']['controls']);
    }

    public function testAudioMovesAlternativeTextToAriaLabel(): void
    {
        $element = $this->audio()->parsedownElement(null, 'Audio description');

        $this->assertSame('audio', $element['name']);
        $this->assertArrayNotHasKey('alt', $element['attributes']);
        $this->assertSame('Audio description', $element['attributes']['aria-label']);
    }

    public function testEmptyAlternativeTextDoesNotCreateAriaLabel(): void
    {
        $element = $this->video()->parsedownElement();

        $this->assertArrayNotHasKey('alt', $element['attributes']);
        $this->assertArrayNotHasKey('aria-label', $element['attributes']);
    }

    public function testExplicitAriaLabelTakesPrecedenceOverAlternativeText(): void
    {
        $element = $this->video()
            ->attribute('aria-label', 'Explicit label')
            ->parsedownElement(null, 'Alternative text');

        $this->assertArrayNotHasKey('alt', $element['attributes']);
        $this->assertSame('Explicit label', $element['attributes']['aria-label']);
    }

    private function video(): VideoMedium
    {
        return new VideoMedium($this->mediaItems('test.mp4'));
    }

    private function audio(): AudioMedium
    {
        return new AudioMedium($this->mediaItems('test.mp3'));
    }

    private function mediaItems(string $filename): array
    {
        return [
            'filepath' => GRAV_ROOT . '/' . $this->fixture,
            'filename' => $filename,
            'basename' => $filename,
        ];
    }
}
