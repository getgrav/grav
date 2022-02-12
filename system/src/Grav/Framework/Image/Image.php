<?php declare(strict_types=1);

namespace Grav\Framework\Image;

use Grav\Framework\Compat\Serializable;
use Grav\Framework\Contracts\Image\ImageOperationsInterface;
use Grav\Framework\Image\Traits\ImageOperationsTrait;
use RuntimeException;

/**
 * Image class.
 */
class Image implements ImageOperationsInterface
{
    use ImageOperationsTrait;
    use Serializable;

    /** @var string */
    protected $filepath;
    /** @var array */
    protected $info;

    /**
     * @param string $filepath
     * @param array $info
     */
    public function __construct(string $filepath, array $info)
    {
        $this->filepath = $filepath;
        $this->width = $info['width'] ?? 0;
        $this->height = $info['height'] ?? 0;
        $this->orientation = isset($info['exif']['Orientation']) ? (int)$info['exif']['Orientation'] : null;
    }

    /**
     * @return array
     */
    public function __serialize(): array
    {
        return [
            'image' => 1,
            'filepath' => $this->filepath,
            'info' => $this->info,
            'width' => $this->width,
            'height' => $this->height,
            'orientation' => $this->orientation,
            'operations' => $this->operations,
        ];
    }

    /**
     * @param array $data
     * @return void
     */
    public function __unserialize(array $data): void
    {
        $image = $data['image'] ?? null;
        if ($image !== 1) {
            throw new RuntimeException('Cannot unserialize image: Version mismatch');
        }

        $this->filepath = $data['filepath'];
        $this->info = $data['info'];
        $this->width = $data['width'];
        $this->height = $data['height'];
        $this->orientation = $data['orientation'];
        $this->operations = $data['operations'];
    }

    /**
     * Generates the hash for the image.
     *
     * @return string
     */
    public function generateHash(): string
    {
        return sha1(serialize($this));
    }
}
