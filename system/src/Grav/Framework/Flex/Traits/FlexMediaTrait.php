<?php

namespace Grav\Framework\Flex\Traits;

/**
 * @package    Grav\Framework\Flex
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

use Grav\Common\Config\Config;
use Grav\Common\Grav;
use Grav\Common\Media\Interfaces\MediaCollectionInterface;
use Grav\Common\Media\Interfaces\MediaUploadInterface;
use Grav\Common\Media\Traits\MediaTrait;
use Grav\Common\Page\Media;
use Grav\Common\Page\Medium\Medium;
use Grav\Common\Page\Medium\MediumFactory;
use Grav\Common\Utils;
use Grav\Framework\Cache\CacheInterface;
use Grav\Framework\Filesystem\Filesystem;
use Grav\Framework\Flex\FlexDirectory;
use Grav\Framework\Form\FormFlashFile;
use Psr\Http\Message\UploadedFileInterface;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;
use RuntimeException;
use function array_key_exists;
use function in_array;
use function is_array;
use function is_callable;
use function is_int;
use function is_object;
use function is_string;
use function strpos;

/**
 * Implements Grav Page content and header manipulation methods.
 */
trait FlexMediaTrait
{
    use MediaTrait {
        MediaTrait::getMedia as protected getExistingMedia;
    }

    /** @var array */
    protected $_uploads;

    /**
     * @return string|null
     */
    public function getStorageFolder()
    {
        return $this->exists() ? $this->getFlexDirectory()->getStorageFolder($this->getStorageKey()) : null;
    }

    /**
     * @return string|null
     */
    public function getMediaFolder()
    {
        return $this->exists() ? $this->getFlexDirectory()->getMediaFolder($this->getStorageKey()) : null;
    }

    /**
     * @return MediaCollectionInterface
     */
    public function getMedia()
    {
        $media = $this->media;
        if (null === $media) {
            $media = $this->getExistingMedia();

            // Include uploaded media to the object media.
            $this->addUpdatedMedia($media);
        }

        return $media;
    }

    /**
     * @param string $field
     * @return MediaCollectionInterface|null
     */
    public function getMediaField(string $field): ?MediaCollectionInterface
    {
        // Field specific media.
        $settings = $this->getFieldSettings($field);
        if (!empty($settings['media_field'])) {
            $var = 'destination';
        } elseif (!empty($settings['media_picker_field'])) {
            $var = 'folder';
        }

        if (empty($var)) {
            // Not a media field.
            $media = null;
        } elseif ($settings['self']) {
            // Uses main media.
            $media = $this->getMedia();
        } else {
            // Uses custom media.
            $media = new Media($settings[$var]);
            $this->addUpdatedMedia($media);
        }

        return $media;
    }

    /**
     * @param string $field
     * @return array|null
     */
    public function getFieldSettings(string $field): ?array
    {
        if ($field === '') {
            return null;
        }

        // Load settings for the field.
        $schema = $this->getBlueprint()->schema();
        $settings = $field && is_object($schema) ? (array)$schema->getProperty($field) : null;
        if (!is_array($settings)) {
            return null;
        }

        $type = $settings['type'] ?? '';

        // Media field.
        if (!empty($settings['media_field']) || array_key_exists('destination', $settings) || in_array($type, ['avatar', 'file', 'pagemedia'], true)) {
            $settings['media_field'] = true;
            $var = 'destination';
        }

        // Media picker field.
        if (!empty($settings['media_picker_field']) || in_array($type, ['filepicker', 'pagemediaselect'], true)) {
            $settings['media_picker_field'] = true;
            $var = 'folder';
        }

        // Set media folder for media fields.
        if (isset($var)) {
            $folder = $settings[$var] ?? '';
            if (in_array(rtrim($folder, '/'), ['', '@self', 'self@', '@self@'], true)) {
                $settings[$var] = $this->getMediaFolder();
                $settings['self'] = true;
            } else {
                $settings[$var] = Utils::getPathFromToken($folder, $this);
                $settings['self'] = false;
            }
        }

        return $settings;
    }

    /**
     * @param string $field
     * @return array
     * @internal
     */
    protected function getMediaFieldSettings(string $field): array
    {
        $settings = $this->getFieldSettings($field) ?? [];

        return $settings + ['accept' => '*', 'limit' => 1000, 'self' => true];
    }

    protected function getMediaFields(): array
    {
        // Load settings for the field.
        $schema = $this->getBlueprint()->schema();

        $list = [];
        foreach ($schema->getState()['items'] as $field => $settings) {
            if (isset($settings['type']) && (in_array($settings['type'], ['avatar', 'file', 'pagemedia']) || !empty($settings['destination']))) {
                $list[] = $field;
            }
        }

        return $list;
    }

    /**
     * @param array|mixed $value
     * @param array $settings
     * @return array|mixed
     */
    protected function parseFileProperty($value, array $settings = [])
    {
        if (!is_array($value)) {
            return $value;
        }

        $media = $this->getMedia();
        $originalMedia = is_callable([$this, 'getOriginalMedia']) ? $this->getOriginalMedia() : null;

        $list = [];
        foreach ($value as $filename => $info) {
            if (!is_array($info)) {
                $list[$filename] = $info;
                continue;
            }

            if (is_int($filename)) {
                $filename = $info['path'] ?? $info['name'];
            }

            /** @var Medium|null $imageFile */
            $imageFile = $media[$filename];

            /** @var Medium|null $originalFile */
            $originalFile = $originalMedia ? $originalMedia[$filename] : null;

            $url = $imageFile ? $imageFile->url() : null;
            $originalUrl = $originalFile ? $originalFile->url() : null;
            $list[$filename] = [
                'name' => $info['name'] ?? null,
                'type' => $info['type'] ?? null,
                'size' => $info['size'] ?? null,
                'path' => $filename,
                'thumb_url' => $url,
                'image_url' => $originalUrl ?? $url
            ];
            if ($originalFile) {
                $list[$filename]['cropData'] = (object)($originalFile->metadata()['upload']['crop'] ?? []);
            }
        }

        return $list;
    }

    /**
     * @param UploadedFileInterface $uploadedFile
     * @param string|null $filename
     * @param string|null $field
     * @return void
     * @internal
     */
    public function checkUploadedMediaFile(UploadedFileInterface $uploadedFile, string $filename = null, string $field = null)
    {
        $media = $this->getMedia();
        if (!$media instanceof MediaUploadInterface) {
            throw new RuntimeException("Media for {$this->getFlexDirectory()->getFlexType()} doesn't support file uploads.");
        }

        $media->checkUploadedFile($uploadedFile, $filename, $this->getMediaFieldSettings($field ?? ''));
    }

    /**
     * @param UploadedFileInterface $uploadedFile
     * @param string|null $filename
     * @param string|null $field
     * @return void
     * @internal
     */
    public function uploadMediaFile(UploadedFileInterface $uploadedFile, string $filename = null, string $field = null): void
    {
        $settings = $this->getMediaFieldSettings($field ?? '');

        $media = $field ? $this->getMediaField($field) : $this->getMedia();
        if (!$media instanceof MediaUploadInterface) {
            throw new RuntimeException("Media for {$this->getFlexDirectory()->getFlexType()} doesn't support file uploads.");
        }

        $filename = $media->checkUploadedFile($uploadedFile, $filename, $settings);
        $media->copyUploadedFile($uploadedFile, $filename, $settings);
        $this->clearMediaCache();
    }

    /**
     * @param string $filename
     * @return void
     * @internal
     */
    public function deleteMediaFile(string $filename): void
    {
        $media = $this->getMedia();
        if (!$media instanceof MediaUploadInterface) {
            throw new RuntimeException("Media for {$this->getFlexDirectory()->getFlexType()} doesn't support file uploads.");
        }

        $media->deleteFile($filename);
        $this->clearMediaCache();
    }

    /**
     * @return array
     */
    public function __debugInfo()
    {
        return parent::__debugInfo() + [
                'uploads:private' => $this->getUpdatedMedia()
            ];
    }

    /**
     * @param array $files
     * @return void
     */
    protected function setUpdatedMedia(array $files): void
    {
        $media = $this->getMedia();
        if (!$media instanceof MediaUploadInterface) {
            return;
        }

        $filesystem = Filesystem::getInstance(false);

        $list = [];
        foreach ($files as $field => $group) {
            $field = (string)$field;
            // Ignore files without a field and resized images.
            if ($field === '' || strpos($field, '/')) {
                continue;
            }

            // Load settings for the field.
            $settings = $this->getMediaFieldSettings($field);
            foreach ($group as $filename => $file) {
                if ($file) {
                    // File upload.
                    $filename = $file->getClientFilename();

                    /** @var FormFlashFile $file */
                    $data = $file->jsonSerialize();
                    unset($data['tmp_name'], $data['path']);
                } else {
                    // File delete.
                    $data = null;
                }

                if ($file) {
                    // Check file upload against media limits (except for max size).
                    $filename = $media->checkUploadedFile($file, $filename, ['filesize' => 0] + $settings);
                }

                $self = $settings['self'];
                if ($this->_loadMedia && $self) {
                    $filepath = $filename;
                } else {
                    $filepath = "{$settings['destination']}/{$filename}";
                }

                // Calculate path without the retina scaling factor.
                $realpath = $filesystem->pathname($filepath) . str_replace(['@3x', '@2x'], '', basename($filepath));

                $list[$filename] = [$file, $settings];

                $path = str_replace('.', "\n", $field);
                if (null !== $data) {
                    $data['name'] = $filename;
                    $data['path'] = $filepath;

                    $this->setNestedProperty("{$path}\n{$realpath}", $data, "\n");
                } else {
                    $this->unsetNestedProperty("{$path}\n{$realpath}", "\n");
                }
            }
        }

        $this->clearMediaCache();

        $this->_uploads = $list;
    }

    /**
     * @param MediaCollectionInterface $media
     */
    protected function addUpdatedMedia(MediaCollectionInterface $media): void
    {
        $updated = false;
        foreach ($this->getUpdatedMedia() as $filename => $upload) {
            if (is_array($upload)) {
                // Uses new format with [UploadedFileInterface, array].
                $settings = $upload[1];
                if (isset($settings['destination']) && $settings['destination'] === $media->getPath()) {
                    $upload = $upload[0];
                } else {
                    $upload = false;
                }
            }
            if (false !== $upload) {
                $medium = $upload ? MediumFactory::fromUploadedFile($upload) : null;
                $updated = true;
                if ($medium) {
                    $medium->uploaded = true;
                    $media->add($filename, $medium);
                } elseif (is_callable([$media, 'hide'])) {
                    $media->hide($filename);
                }
            }
        }

        if ($updated) {
            $media->setTimestamps();
        }
    }

    /**
     * @return array<string, UploadedFileInterface|array|null>
     */
    protected function getUpdatedMedia(): array
    {
        return $this->_uploads ?? [];
    }

    /**
     * @return void
     */
    protected function saveUpdatedMedia(): void
    {
        $media = $this->getMedia();
        if (!$media instanceof MediaUploadInterface) {
            return;
        }

        // Upload/delete altered files.
        /**
         * @var string $filename
         * @var UploadedFileInterface|array|null $file
         */
        foreach ($this->getUpdatedMedia() as $filename => $file) {
            if (is_array($file)) {
                [$file, $settings] = $file;
            } else {
                $settings = null;
            }
            if ($file instanceof UploadedFileInterface) {
                $media->copyUploadedFile($file, $filename, $settings);
            } else {
                $media->deleteFile($filename, $settings);
            }
        }

        $this->setUpdatedMedia([]);
        $this->clearMediaCache();
    }

    /**
     * @return void
     */
    protected function freeMedia(): void
    {
        $this->unsetObjectProperty('media');
    }

    /**
     * @param string $uri
     * @return Medium|null
     */
    protected function createMedium($uri)
    {
        $grav = Grav::instance();

        /** @var UniformResourceLocator $locator */
        $locator = $grav['locator'];

        $file = $uri && $locator->isStream($uri) ? $locator->findResource($uri) : $uri;

        return is_string($file) && file_exists($file) ? MediumFactory::fromFile($file) : null;
    }

    /**
     * @return CacheInterface
     */
    protected function getMediaCache()
    {
        return $this->getCache('object');
    }

    /**
     * @return MediaCollectionInterface
     */
    protected function offsetLoad_media()
    {
        return $this->getMedia();
    }

    /**
     * @return null
     */
    protected function offsetSerialize_media()
    {
        return null;
    }

    /**
     * @return FlexDirectory
     */
    abstract public function getFlexDirectory(): FlexDirectory;

    /**
     * @return string
     */
    abstract public function getStorageKey(): string;

    /**
     * @param string $filename
     * @return void
     * @deprecated 1.7 Use Media class that implements MediaUploadInterface instead.
     */
    public function checkMediaFilename(string $filename)
    {
        user_error(__METHOD__ . '() is deprecated since Grav 1.7, use Media class that implements MediaUploadInterface instead', E_USER_DEPRECATED);

        // Check the file extension.
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        $grav = Grav::instance();

        /** @var Config $config */
        $config = $grav['config'];

        // If not a supported type, return
        if (!$extension || !$config->get("media.types.{$extension}")) {
            $language = $grav['language'];
            throw new RuntimeException($language->translate('PLUGIN_ADMIN.UNSUPPORTED_FILE_TYPE') . ': ' . $extension, 400);
        }
    }
}
