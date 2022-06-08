<?php declare(strict_types=1);

namespace Grav\Framework\Image;

use Exception;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use Grav\Framework\Compat\Serializable;
use Grav\Framework\Contracts\Image\ImageAdapterInterface;
use Grav\Framework\Contracts\Image\ImageOperationsInterface;
use Grav\Framework\Image\Traits\ImageOperationsTrait;
use InvalidArgumentException;
use JsonSerializable;
use Psr\Log\LoggerInterface;
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

    public static array $mimes = [
        'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        'png' => 'image/png',
        'gif' => 'image/gif',
    ];

    public array $extra = [];

    protected ImageAdapterInterface $adapter;
    protected int $imageWidth;
    protected int $imageHeight;
    protected int $scale;
    protected string $filepath;
    protected int $modified;
    protected int $size;
    protected int $operationsCursor;
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
     * @param string $extension
     * @return string|null
     */
    public static function getImageType(string $extension): ?string
    {
        return self::$types[$extension] ?? null;
    }

    /**
     * @param string $extension
     * @return string|null
     */
    public static function getMimeType(string $extension): ?string
    {
        $type = self::getImageType($extension) ?? '';

        return self::$mimes[$type] ?? null;
    }


    /**
     * @param string $filepath
     * @param array $info
     */
    public function __construct(string $filepath, array $info)
    {
        $width = (int)($info['width'] ?? 0);
        $height = (int)($info['height'] ?? 0);
        if ($width === 0 || $height === 0) {
            throw new InvalidArgumentException('Image needs to have width and height');
        }

        $this->filepath = $filepath;
        $this->modified = (int)($info['modified'] ?? 0);
        $this->size = (int)($info['size'] ?? 0);
        $this->imageWidth = $width;
        $this->imageHeight = $height;
        $this->orientation = isset($info['exif']['Orientation']) ? (int)$info['exif']['Orientation'] : null;
        $this->scale = (int)($info['scale'] ?? 1);
        $this->width = (int)($width / $this->scale);
        $this->height = (int)($height / $this->scale);
        $this->retina = $this->scale;
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
            'image_width' => $this->imageWidth,
            'image_height' => $this->imageHeight,
            'orientation' => $this->orientation,
            'width' => $this->width,
            'height' => $this->height,
            'scale' => $this->scale,
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
        $this->imageWidth = $data['image_width'];
        $this->imageHeight = $data['image_height'];
        $this->orientation = $data['orientation'];
        $this->width = $data['width'];
        $this->height = $data['height'];
        $this->scale = $data['scale'];
        $this->dependencies = $data['dependencies'];
        $this->operations = $data['operations'];
        $this->extra = $data['extra'];
        $this->retina = $this->scale;
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
     * @return int
     */
    public function getRetinaScale(): int
    {
        return $this->retina;
    }

    /**
     * @param int $scale
     * @return $this
     */
    public function setRetinaScale(int $scale)
    {
        if (isset($this->operationsCursor)) {
            throw new RuntimeException('You can set retina scale only before applying operations!');
        }

        $this->retina = $scale;

        return $this;
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
     * @param string|null $filepath
     * @return string|null
     */
    public function guessType(string $filepath = null): ?string
    {
        $type = (string)pathinfo($filepath ?? $this->filepath, PATHINFO_EXTENSION);

        return static::getImageType($type);
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
            $type = $this->guessType() ?? '';
        } else {
            $type = static::getImageType($type) ?? '';
        }

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
        $adapter = $this->adapter;
        $operations = $this->operations;

        // On first run we need to initialize the state of the image.
        if (!isset($this->operationsCursor)) {
            $this->operationsCursor = 0;

            // Apply retina scale and resize the image to have the correct size.
            if ($this->retina !== 1) {
                $width = (int)($this->imageWidth * $this->retina / $this->scale);
                $height = (int)($this->imageHeight * $this->retina / $this->scale);
            } else {
                $width = $this->width;
                $height = $this->height;
            }

            if ($width !== $this->imageWidth || $height !== $this->imageHeight) {
                $adapter->resize(null, $width, $height, $width, $height);
            }

            // Set retina scaling for the rest of the operations.
            $adapter->setRetinaScale($this->retina);
        }

        // Only get the remaining operations (this method can be called more than once).
        $cursor = $this->operationsCursor;
        if ($cursor) {
            $operations = array_slice($operations, $cursor, null, true);
        }

        // Apply all the remaining operations.
        foreach ($operations as $operation) {
            try {
                [$method, $params] = $operation;

                if ($method === 'merge') {
                    $params[0] = $this->getImageParameter($params[0]);
                }

                $adapter->{$method}(...$params);

            } catch (\InvalidArgumentException $e) {
                /** @var LoggerInterface $log */
                $log = Grav::instance()['log'];
                $log->warning(sprintf('Image operation %s failed: %s', $method, $e->getMessage()));

                continue;
            }

            $cursor++;
        }

        $this->operationsCursor = $cursor;

        return $this;
    }

    /**
     * @param array $param
     * @return ImageAdapterInterface|null
     */
    protected function getImageParameter(array $param): ?ImageAdapterInterface
    {
        $adapter = $this->adapter;
        $image = static::createFromArray($param);

        // TODO: Right now this only works for the local files.
        $imgAdapter = $adapter::createFromFile($image->filepath, $image->scale);
        $image->setAdapter($imgAdapter);

        // Apply all operations to the image that is being merged and get the adapter.
        return $image->applyOperations()->getAdapter();
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
