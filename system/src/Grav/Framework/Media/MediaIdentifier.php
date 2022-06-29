<?php declare(strict_types=1);

namespace Grav\Framework\Media;

use Grav\Common\Grav;
use Grav\Common\User\Interfaces\UserInterface;
use Grav\Framework\Contracts\Media\MediaObjectInterface;
use Grav\Framework\Flex\Flex;
use Grav\Framework\Flex\FlexFormFlash;
use Grav\Framework\Flex\Interfaces\FlexObjectInterface;
use Grav\Framework\Object\Identifiers\Identifier;

/**
 * Interface IdentifierInterface
 *
 * @template T of MediaObjectInterface
 * @extends Identifier<T>
 */
class MediaIdentifier extends Identifier
{
    /** @var MediaObjectInterface|null */
    private $object = null;

    /**
     * @param MediaObjectInterface $object
     * @return MediaIdentifier<T>
     */
    public static function createFromObject(MediaObjectInterface $object): MediaIdentifier
    {
        $instance = new static($object->getId());
        $instance->setObject($object);

        return $instance;
    }

    /**
     * @param string $id
     */
    public function __construct(string $id)
    {
        parent::__construct($id, 'media');
    }

    /**
     * @return T
     */
    public function getObject(): ?MediaObjectInterface
    {
        if (!isset($this->object)) {
            $type = $this->getType();
            $id = $this->getId();

            $parts = explode('/', $id);
            if ($type === 'media' && str_starts_with($id, 'uploads/')) {
                array_shift($parts);
                [, $folder, $uniqueId, $field, $filename] = $this->findFlash($parts);

                $flash = $this->getFlash($folder, $uniqueId);
                if ($flash->exists()) {

                    $uploadedFile = $flash->getFilesByField($field)[$filename] ?? null;

                    $this->object = UploadedMediaObject::createFromFlash($flash, $field, $filename, $uploadedFile);
                }
            } else {
                $type = array_shift($parts);
                $key = array_shift($parts);
                $field = array_shift($parts);
                $filename = implode('/', $parts);

                $flexObject = $this->getFlexObject($type, $key);
                if ($flexObject && method_exists($flexObject, 'getMediaField') && method_exists($flexObject, 'getMedia')) {
                    $media = $field !== 'media' ? $flexObject->getMediaField($field) : $flexObject->getMedia();
                    $image = null;
                    if ($media) {
                        $image = $media[$filename];
                    }

                    $this->object = new MediaObject($field, $filename, $image, $flexObject);
                }
            }

            if (!isset($this->object)) {
                throw new \RuntimeException(sprintf('Object not found for identifier {type: "%s", id: "%s"}', $type, $id));
            }
        }

        return $this->object;
    }

    /**
     * @param T $object
     */
    public function setObject(MediaObjectInterface $object): void
    {
        $type = $this->getType();
        $objectType = $object->getType();

        if ($type !== $objectType) {
            throw new \RuntimeException(sprintf('Object has to be type %s, %s given', $type, $objectType));
        }

        $this->object = $object;
    }

    protected function findFlash(array $parts): ?array
    {
        $type = array_shift($parts);
        if ($type === 'account') {
            /** @var UserInterface|null $user */
            $user = Grav::instance()['user'] ?? null;
            $folder = $user->getMediaFolder();
        } else {
            $folder = 'tmp://';
        }

        if (!$folder) {
            return null;
        }

        do {
            $part = array_shift($parts);
            $folder .= "/{$part}";
        } while (!str_starts_with($part, 'flex-'));

        $uniqueId = array_shift($parts);
        $field = array_shift($parts);
        $filename = implode('/', $parts);

        return [$type, $folder, $uniqueId, $field, $filename];
    }

    protected function getFlash(string $folder, string $uniqueId): FlexFormFlash
    {
        $config = [
            'unique_id' => $uniqueId,
            'folder' => $folder
        ];

        return new FlexFormFlash($config);
    }

    protected function getFlexObject(string $type, string $key): ?FlexObjectInterface
    {
        /** @var Flex $flex */
        $flex = Grav::instance()['flex'];

        return $flex->getObject($key, $type);
    }
}
