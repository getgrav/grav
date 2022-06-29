<?php declare(strict_types=1);

namespace Grav\Framework\Media;

use Grav\Framework\Contracts\Media\MediaObjectInterface;
use Grav\Framework\Flex\FlexFormFlash;
use Grav\Framework\Form\Interfaces\FormFlashInterface;
use Grav\Framework\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UploadedFileInterface;

/**
 * Class UploadedMediaObject
 */
class UploadedMediaObject implements MediaObjectInterface
{
    /** @var string */
    static public $placeholderImage = 'image://media/thumb.png';

    /** @var FormFlashInterface */
    public $object;

    /** @var string */
    private $id;
    /** @var string|null */
    private $field;
    /** @var string */
    private $filename;
    /** @var array */
    private $meta;
    /** @var UploadedFileInterface|null */
    private $uploadedFile;

    /**
     * @param FlexFormFlash $flash
     * @param string|null $field
     * @param string $filename
     * @param UploadedFileInterface|null $uploadedFile
     * @return static
     */
    public static function createFromFlash(FlexFormFlash $flash, ?string $field, string $filename, ?UploadedFileInterface $uploadedFile = null)
    {
        $id = $flash->getId();

        return new static($id, $field, $filename, $uploadedFile);
    }

    /**
     * @param string $id
     * @param string|null $field
     * @param string $filename
     * @param UploadedFileInterface|null $uploadedFile
     */
    public function __construct(string $id, ?string $field, string $filename, ?UploadedFileInterface $uploadedFile = null)
    {
        $this->id = $id;
        $this->field = $field;
        $this->filename = $filename;
        $this->uploadedFile = $uploadedFile;
        if ($uploadedFile) {
            $this->meta = [
                'filename' => $uploadedFile->getClientFilename(),
                'mime' => $uploadedFile->getClientMediaType(),
                'size' => $uploadedFile->getSize()
            ];
        } else {
            $this->meta = [];
        }
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return 'media';
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        $id = $this->id;
        $field = $this->field;
        $path = $field ? "/{$field}/" : '';

        return 'uploads/' . $id . $path . basename($this->filename);
    }

    /**
     * @return bool
     */
    public function exists(): bool
    {
        //return $this->uploadedFile !== null;
        return false;
    }

    /**
     * @return array
     */
    public function getMeta(): array
    {
        return $this->meta;
    }

    /**
     * @param string $field
     * @return mixed|null
     */
    public function get(string $field)
    {
        return $this->meta[$field] ?? null;
    }

    /**
     * @return string
     */
    public function getUrl(): string
    {
        return '';
    }

    /**
     * @return UploadedFileInterface|null
     */
    public function getUploadedFile(): ?UploadedFileInterface
    {
        return $this->uploadedFile;
    }

    /**
     * @param array $actions
     * @return Response
     */
    public function createResponse(array $actions): ResponseInterface
    {
        // Display placeholder image.
        $filename = static::$placeholderImage;

        $time = filemtime($filename);
        $size = filesize($filename);
        $body = fopen($filename, 'rb');
        $headers = [
            'Content-Type' => 'image/svg',
            'Last-Modified' => gmdate('D, d M Y H:i:s', $time) . ' GMT',
            'ETag' => sprintf('%x-%x', $size, $time)
        ];

        return new Response(404, $headers, $body);
    }

    /**
     * @return array
     */
    public function jsonSerialize(): array
    {
        return [
            'type' => $this->getType(),
            'id' => $this->getId()
        ];
    }

    /**
     * @return string[]
     */
    public function __debugInfo(): array
    {
        return $this->jsonSerialize();
    }
}
