<?php

use Codeception\Util\Fixtures;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use Grav\Common\Page\Media;
use Grav\Common\Page\Medium\Medium;

/**
 * Class MediaSidecarReservedKeysTest
 *
 * A `.meta.yaml` sidecar adds descriptive fields to a medium, but the fields
 * core derives from the file on disk (path, name, type, URL) always win, and
 * only `<file>.meta.yaml` is treated as a sidecar.
 */
class MediaSidecarReservedKeysTest extends \Codeception\Test\Unit
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

        $this->folder = sys_get_temp_dir() . '/grav-sidecar-' . bin2hex(random_bytes(4));
        Folder::create($this->folder);
    }

    protected function tearDown(): void
    {
        Folder::delete($this->folder);
        parent::tearDown();
    }

    public function testSidecarCannotMoveTheMedium(): void
    {
        file_put_contents($this->folder . '/song.mp3', 'ID3');
        $plain = (new Media($this->folder))['song.mp3'];

        file_put_contents(
            $this->folder . '/song.mp3.meta.yaml',
            "filepath: user/accounts/admin.yaml\npath: user/accounts\nfilename: admin.yaml\nbasename: admin\nextension: yaml\n"
            . "url: 'javascript:alert(1)'\ntype: image\nmime: text/html\nsize: 1\nmodified: 1\nthumb: x.png\n"
            . "thumbnails: { page: /etc/passwd }\nmedia: Grav\\Common\\Page\\Media\ntitle: Song\n"
        );
        $medium = (new Media($this->folder))['song.mp3'];

        foreach (Medium::RESERVED_META_KEYS as $key) {
            $this->assertSame($plain->get($key), $medium->get($key), $key);
        }
        $this->assertSame($this->folder . '/song.mp3', $medium->path());
        $this->assertStringNotContainsString('javascript:', $medium->url());
    }

    public function testDescriptiveFieldsAreStillMerged(): void
    {
        file_put_contents($this->folder . '/song.mp3', 'ID3');
        file_put_contents($this->folder . '/song.mp3.meta.yaml', "filepath: user/accounts/admin.yaml\ntitle: Song\nrating: 4\n");

        $medium = (new Media($this->folder))['song.mp3'];

        $this->assertSame('Song', $medium->get('title'));
        $this->assertSame(4, $medium->get('rating'));
        $this->assertSame(['title' => 'Song', 'rating' => 4], $medium->metadata());
        $this->assertSame(['title', 'rating'], $medium->metaKeys());
    }

    public function testOnlyMetaYamlIsASidecar(): void
    {
        // Any `.meta.<ext>` other than `.meta.yaml` is an ordinary medium.
        file_put_contents($this->folder . '/song.mp3', 'ID3');
        file_put_contents($this->folder . '/song.mp3.meta.txt', "title: Song\n");
        file_put_contents($this->folder . '/song.mp3.meta.yaml.txt', "title: Song\n");

        $media = new Media($this->folder);
        $medium = $media['song.mp3'];

        $this->assertFalse($medium->hasMeta());
        $this->assertNull($medium->get('title'));
        $this->assertSame(['song.mp3', 'song.mp3.meta.txt', 'song.mp3.meta.yaml.txt'], array_keys($media->all()));
    }

    public function testImageFiltersInSidecarStillApply(): void
    {
        copy(GRAV_ROOT . '/tests/fake/media-metadata/photo4.jpg', $this->folder . '/photo.jpg');
        file_put_contents($this->folder . '/photo.jpg.meta.yaml', "image: { filters: { default: [[negate]] } }\n");

        $medium = (new Media($this->folder))['photo.jpg'];

        $this->assertSame([['negate']], $medium->get('image.filters.default'));
    }
}
