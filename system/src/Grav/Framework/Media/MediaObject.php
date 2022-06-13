<?php declare(strict_types=1);

namespace Grav\Framework\Media;

use Grav\Common\Page\Medium\ImageMedium;
use Grav\Framework\Contracts\Media\MediaObjectInterface;
use Grav\Framework\Flex\Interfaces\FlexObjectInterface;
use Grav\Framework\Media\Interfaces\MediaObjectInterface as GravMediaObjectInterface;
use Grav\Framework\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * Class MediaObject
 */
class MediaObject implements MediaObjectInterface
{
    /** @var string */
    static public $placeholderImage = 'image://media/thumb.png';

    /** @var FlexObjectInterface */
    public $object;
    /** @var GravMediaObjectInterface|null */
    public $media;

    /** @var string|null */
    private $field;
    /** @var string */
    private $filename;

    /**
     * MediaObject constructor.
     * @param string|null $field
     * @param string $filename
     * @param GravMediaObjectInterface|null $media
     * @param FlexObjectInterface $object
     */
    public function __construct(?string $field, string $filename, ?GravMediaObjectInterface $media, FlexObjectInterface $object)
    {
        $this->field = $field;
        $this->filename = $filename;
        $this->media = $media;
        $this->object = $object;
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
        $path = $field ? "/{$field}/" : '/media/';

        return $object->getType() . '/' . $object->getKey() . $path . basename($this->filename);
    }

    /**
     * @return bool
     */
    public function exists(): bool
    {
        return $this->media !== null;
    }

    /**
     * @return array
     */
    public function getMeta(): array
    {
        if (!isset($this->media)) {
            return [];
        }

        return $this->media->getMeta();
    }

    /**
     * @param string $field
     * @return mixed|null
     */
    public function get(string $field)
    {
        if (!isset($this->media)) {
            return null;
        }

        return $this->media->get($field);
    }

    /**
     * @return string
     */
    public function getUrl(): string
    {
        if (!isset($this->media)) {
            return '';
        }

        return $this->media->url();
    }

    /**
     * Create media response.
     *
     * @param array $actions
     * @return Response
     */
    public function createResponse(array $actions): ResponseInterface
    {
        if (!isset($this->media)) {
            return $this->create404Response($actions);
        }

        $media = $this->media;

        if ($actions) {
            $media = $this->processMediaActions($media, $actions);
        }

        // FIXME: This only works for images
        if (!$media instanceof ImageMedium) {
            throw new \RuntimeException('Not Implemented', 500);
        }

        $filename = $media->path(false);
        $time = filemtime($filename);
        $size = filesize($filename);
        $body = fopen($filename, 'rb');
        $headers = [
            'Content-Type' => $media->get('mime'),
            'Last-Modified' => gmdate('D, d M Y H:i:s', $time) . ' GMT',
            'ETag' => sprintf('%x-%x', $size, $time)
        ];

        return new Response(200, $headers, $body);
    }

    /**
     * Process media actions
     *
     * @param GravMediaObjectInterface $medium
     * @param array $actions
     * @return GravMediaObjectInterface
     */
    protected function processMediaActions(GravMediaObjectInterface $medium, array $actions): GravMediaObjectInterface
    {
        // loop through actions for the image and call them
        foreach ($actions as $method => $params) {
            $matches = [];

            if (preg_match('/\[(.*)]/', $params, $matches)) {
                $args = [explode(',', $matches[1])];
            } else {
                $args = explode(',', $params);
            }

            try {
                $medium->{$method}(...$args);
            } catch (Throwable $e) {
                // Ignore all errors for now and just skip the action.
            }
        }

        return $medium;
    }

    /**
     * @param array $actions
     * @return Response
     */
    protected function create404Response(array $actions): Response
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
