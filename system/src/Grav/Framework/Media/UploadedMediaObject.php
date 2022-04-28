<?php declare(strict_types=1);

namespace Grav\Framework\Media;

use Grav\Framework\Contracts\Media\MediaObjectInterface;
use Grav\Framework\Flex\FlexFormFlash;
use Grav\Framework\Form\Interfaces\FormFlashInterface;
use Grav\Framework\Psr7\Response;
use Psr\Http\Message\UploadedFileInterface;

/**
 * Class UploadedMediaObject
 */
class UploadedMediaObject implements MediaObjectInterface
{
    // FIXME:
    static public string $placeholderImage = 'theme://img/revkit-temp.svg';

    public FormFlashInterface $object;

    private ?string $field;
    private string $filename;
    private array $meta;
    private ?UploadedFileInterface $uploadedFile;

    /**
     * UploadedMediaObject constructor.
     * @param string|null $field
     * @param string $filename
     * @param FormFlashInterface $object
     * @param UploadedFileInterface|null $uploadedFile
     */
    public function __construct(?string $field, string $filename, FormFlashInterface $object, ?UploadedFileInterface $uploadedFile = null)
    {
        $this->field = $field;
        $this->filename = $filename;
        $this->object = $object;
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
        $field = $this->field;
        $object = $this->object;
        if ($object instanceof FlexFormFlash) {
            $type = $object->getObject()->getFlexType();
        } else {
            $type = 'undefined';
        }

        $id = $type . '/' . $object->getUniqueId();
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
    public function createResponse(array $actions): Response
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
