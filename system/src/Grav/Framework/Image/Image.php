<?php declare(strict_types=1);

namespace Grav\Framework\Image;

use Exception;
use Grav\Common\Filesystem\Folder;
use Grav\Framework\Compat\Serializable;
use Grav\Framework\Contracts\Image\ImageAdapterInterface;
use Grav\Framework\Contracts\Image\ImageOperationsInterface;
use Grav\Framework\Image\Traits\ImageOperationsTrait;
use InvalidArgumentException;
use JsonSerializable;
use RuntimeException;
use function array_slice;
use function dirname;
use function is_int;

/**
 * Image class.
 */
class Image implements ImageOperationsInterface, JsonSerializable
{
    use ImageOperationsTrait;
    use Serializable;

    /**
     * Supported types.
     * @var array
     */
    public static array $types = [
        'jpg'   => 'jpeg',
        'jpeg'  => 'jpeg',
        'webp'  => 'webp',
        'png'   => 'png',
        'gif'   => 'gif',
    ];

    public array $extra = [];

    protected ImageAdapterInterface $adapter;
    protected int $origWidth;
    protected int $origHeight;
    protected string $filepath;
    protected int $modified;
    protected int $size;
    protected int $operationsCursor = 0;

    protected int $retina;

    /**
     * @param array $data
     * @return static
     */
    public static function createFromArray(array $data)
    {
        $instance = new static($data['filepath'], []);
        $instance->__unserialize($data);

        return $instance;
    }

    /**
     * @param string $filepath
     * @param array $info
     */
    public function __construct(string $filepath, array $info)
    {
        $this->filepath = $filepath;
        $this->modified = (int)($info['modified'] ?? 0);
        $this->size = (int)($info['size'] ?? 0);
        $this->origWidth = $this->width = (int)($info['width'] ?? 0);
        $this->origHeight = $this->height = (int)($info['height'] ?? 0);
        $this->orientation = isset($info['exif']['Orientation']) ? (int)$info['exif']['Orientation'] : null;
        $this->retina = (int)($info['retina'] ?? 1);
    }

    public function getFilepath(): string
    {
        return $this->filepath;
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
            'retina' => $this->retina,
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
        $this->retina = $data['retina'] ?? 1;
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

    /**
     * Get image adapter.
     *
     * @return ImageAdapterInterface|null
     */
    public function getAdapter(): ?ImageAdapterInterface
    {
        return $this->adapter;
    }

    /**
     * Set image adapter.
     *
     * Note: You should always call $this->freeAdapter() as soon as you have generated the image!
     *
     * @param ImageAdapterInterface $adapter
     * @return $this
     * @throws RuntimeException
     */
    public function setAdapter(ImageAdapterInterface $adapter): Image
    {
        $this->adapter = $adapter;

        return $this;
    }

    /**
     * Free image adapter to free some memory.
     *
     * @return void
     */
    public function freeAdapter(): void
    {
        unset($this->adapter);
    }

    /**
     * @param string $type
     * @param int $quality
     * @param bool $actual
     * @return string
     * @throws InvalidArgumentException|RuntimeException
     */
    public function cacheFile(string $type = 'jpg', int $quality = 80, bool $actual = false): string
    {
        $filepath = $actual ? null : $this->filepath;
        if ($filepath && file_exists($filepath)) {
            return $filepath;
        }

        return $this->save($filepath, $type, $quality);
    }

    /**
     * @param int $quality
     * @return string
     * @throws InvalidArgumentException|RuntimeException
     */
    public function jpeg(int $quality = 80): string
    {
        return $this->cacheFile('jpg', $quality);
    }

    /**
     * @return string
     * @throws InvalidArgumentException|RuntimeException
     */
    public function gif(): string
    {
        return $this->cacheFile('gif');
    }

    /**
     * @return string
     * @throws InvalidArgumentException|RuntimeException
     */
    public function png(): string
    {
        return $this->cacheFile('png');
    }

    /**
     * @param int $quality
     * @return string
     * @throws InvalidArgumentException|RuntimeException
     */
    public function webp(int $quality = 80): string
    {
        return $this->cacheFile('webp', $quality);
    }

    /**
     * @param int $quality
     * @return string
     * @throws InvalidArgumentException|RuntimeException
     */
    public function guess(int $quality = 80): string
    {
        return $this->cacheFile('guess', $quality);
    }

    /**
     * @return string
     */
    public function guessType(): string
    {
        return pathinfo($this->filepath, PATHINFO_EXTENSION);
    }

    /**
     * Save the file to a given output.
     *
     * Note: to use this method, you need to call `setAdapter()` first.
     *
     * @param string|null $filepath
     * @param string|int $type
     * @param int $quality
     * @return string
     * @throws InvalidArgumentException|RuntimeException
     */
    public function save(?string $filepath, $type = 'guess', int $quality = 80): string
    {
        if (is_int($type)) {
            $quality = $type;
            $type = 'jpeg';
        }

        if ($type === 'guess') {
            $type = $this->guessType();
        }

        $type = self::$types[$type] ?? '';
        if ('' === $type) {
            throw new InvalidArgumentException(sprintf("Given image type '%s' is not valid", $type));
        }

        $adapter = $this->getAdapter();
        if (null === $adapter) {
            throw new RuntimeException('You need to set image adapter first!');
        }

        if ($filepath) {
            $this->mkdir(dirname($filepath));
        }

        try {
            $this->applyOperations();

            if (null === $filepath) {
                ob_start();
            }

            switch ($type) {
                case 'jpeg';
                    $adapter->saveJpeg($filepath, $quality);
                    break;
                case 'gif';
                    $adapter->saveGif($filepath);
                    break;
                case 'png':
                    $adapter->savePng($filepath);
                    break;
                case 'webp':
                    $adapter->saveWebP($filepath, $quality);
                    break;
            }

            return ($filepath ?? ob_get_clean()) ?: '';
        } catch (Exception $e) {
            throw new RuntimeException('', $e->getCode(), $e);
        }
    }

    /**
     * Apply image operations.
     *
     * Note: to use this method, you need to call `setAdapter()` first.
     *
     * @return $this
     */
    public function applyOperations(): Image
    {
        $operations = $this->operations;
        if (!$operations) {
            return $this;
        }

        $adapter = $this->adapter;

        // Only get the remaining operations.
        $cursor = $this->operationsCursor;
        if ($cursor) {
            $operations = array_slice($operations, $cursor, null, true);
        }

        foreach ($operations as $operation) {
            [$method, $params] = $operation;
            if ($method === 'merge') {
                $image = static::createFromArray($params[0]);

                // FIXME: Right now this only works for the local files.
                try {
                    $imgAdapter = $adapter::createFromFile($image->filepath);
                    $imgAdapter->setRetinaScale($image->retina);
                    $image->setAdapter($imgAdapter);
                } catch (\InvalidArgumentException $e) {
                    // TODO: log errors on missing files?
                    continue;
                }

                // Apply all operations to the image that is being merged and get the adapter.
                $params[0] = $image->applyOperations()->getAdapter();
            }

            $adapter->{$method}(...$params);
            $cursor++;
        }

        $this->operationsCursor = $cursor;

        return $this;
    }

    /**
     * @param string $directory
     * @return void
     */
    private function mkdir(string $directory): void
    {
        Folder::mkdir($directory);
    }
}
