<?php declare(strict_types=1);

namespace Grav\Framework\Image;

use Grav\Framework\Compat\Serializable;
use Grav\Framework\Contracts\Image\ImageOperationsInterface;
use Grav\Framework\Image\Traits\ImageOperationsTrait;
use JsonSerializable;
use RuntimeException;

/**
 * Image class.
 */
class Image implements ImageOperationsInterface, JsonSerializable
{
    use ImageOperationsTrait;
    use Serializable;

    /** @var int */
    protected $origWidth;
    /** @var int */
    protected $origHeight;
    /** @var string */
    protected $filepath;
    /** @var int */
    protected $modified;
    /** @var int */
    protected $size;
    /** @var array */
    public $extra = [];

    /**
     * @param string $filepath
     * @param array $info
     */
    public function __construct(string $filepath, array $info)
    {
        $this->filepath = $filepath;
        $this->modified = $info['modified'] ?? 0;
        $this->size = $info['size'] ?? 0;
        $this->origWidth = $this->width = $info['width'] ?? 0;
        $this->origHeight = $this->height = $info['height'] ?? 0;
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
            'modified' => $this->modified,
            'size' => $this->size,
            'orientation' => $this->orientation,
            'orig_width' => $this->origWidth,
            'orig_height' => $this->origHeight,
            'width' => $this->width,
            'height' => $this->height,
            'dependencies' => $this->dependencies,
            'operations' => $this->operations,
            'extra' => $this->extra
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
        $this->modified = $data['modified'];
        $this->size = $data['size'];
        $this->origWidth = $data['orig_width'];
        $this->origHeight = $data['orig_height'];
        $this->orientation = $data['orientation'];
        $this->width = $data['width'];
        $this->height = $data['height'];
        $this->dependencies = $data['dependencies'];
        $this->operations = $data['operations'];
        $this->extra = $data['extra'];
    }

    /**
     * @return array
     */
    public function jsonSerialize(): array
    {
        return ['hash' => $this->generateHash()] + $this->__serialize();
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
