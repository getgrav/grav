<?php

namespace Grav\Framework\Flex\Traits;

/**
 * @package    Grav\Framework\Flex
 *
 * @copyright  Copyright (C) 2015 - 2020 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

use Grav\Common\Config\Config;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use Grav\Common\Media\Interfaces\MediaCollectionInterface;
use Grav\Common\Media\Interfaces\MediaUploadInterface;
use Grav\Common\Media\Traits\MediaTrait;
use Grav\Common\Page\Medium\Medium;
use Grav\Common\Page\Medium\MediumFactory;
use Grav\Common\Utils;
use Grav\Framework\Cache\CacheInterface;
use Grav\Framework\Filesystem\Filesystem;
use Grav\Framework\Flex\FlexDirectory;
use Grav\Framework\Form\FormFlashFile;
use Psr\Http\Message\UploadedFileInterface;
use RocketTheme\Toolbox\File\YamlFile;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;
use RuntimeException;

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
     * @return MediaCollectionInterface|MediaUploadInterface
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

    public function __debugInfo()
    {
        return parent::__debugInfo() + [
                'uploads:private' => $this->getUpdatedMedia()
            ];
    }

    /**
     * @param string $field
     * @return array
     */
    protected function getMediaFieldSettings(string $field): array
    {
        // Load settings for the field.
        $schema = $this->getBlueprint()->schema();
        $settings = $schema ? (array)$schema->getProperty($field) : [];

        // Set destination folder.
        if (empty($settings['destination']) || in_array($settings['destination'], ['@self', 'self@', '@self@'], true)) {
            $settings['destination'] = $this->getMediaFolder();
            $settings['self'] = true;
        } else {
            $settings['self'] = false;
        }

        return $settings;
    }

    /**
     * @param array $files
     * @return void
     */
    protected function setUpdatedMedia(array $files): void
    {
        $media = $this->getMedia();

        $list = [];
        foreach ($files as $field => $group) {
            // Ignore files without a field and resized images.
            if ($field === '' || \strpos((string)$field, '/')) {
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
                    // Check file upload against media limits.
                    $filename = $media->checkUploadedFile($file, $filename, $settings);
                }

                $self = $settings['self'];
                if ($this->_loadMedia && $self) {
                    $filepath = $filename;
                } else {
                    $filepath = "{$settings['destination']}/{$filename}";
                }

                $list[$filename] = [$file, $settings];

                $path = str_replace('.', "\n", $field);
                if (null !== $data) {
                    $data['name'] = $filename;
                    $data['path'] = $filepath;

                    $this->setNestedProperty("{$path}\n{$filepath}", $data, "\n");
                } else {
                    $this->unsetNestedProperty("{$path}\n{$filepath}", "\n");
                }
            }
        }

        $this->_uploads = $list;
    }

    /**
     * @param MediaCollectionInterface $media
     */
    protected function addUpdatedMedia(MediaCollectionInterface $media): void
    {
        $updated = false;
        foreach ($this->getUpdatedMedia() as $filename => $upload) {
            if ($upload) {
                $medium = MediumFactory::fromUploadedFile($upload);
                if ($medium) {
                    $updated = true;
                    $media->add($filename, $medium);
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

    /**
     * @param UploadedFileInterface $uploadedFile
     * @return void
     * @deprecated 1.7 Use Media class that implements MediaUploadInterface instead.
     */
    public function checkUploadedMediaFile(UploadedFileInterface $uploadedFile)
    {
        user_error(__METHOD__ . '() is deprecated since Grav 1.7, use Media class that implements MediaUploadInterface instead', E_USER_DEPRECATED);

        $media = $this->getMedia();
        $media->checkUploadedFile($uploadedFile);
    }

    /**
     * @param UploadedFileInterface $uploadedFile
     * @param string|null $filename
     * @return void
     * @deprecated 1.7 Use Media class that implements MediaUploadInterface instead.
     */
    public function uploadMediaFile(UploadedFileInterface $uploadedFile, string $filename = null): void
    {
        user_error(__METHOD__ . '() is deprecated since Grav 1.7, use Media class that implements MediaUploadInterface instead', E_USER_DEPRECATED);

        $media = $this->getMedia();
        $media->copyUploadedFile($uploadedFile, $filename);
        $this->clearMediaCache();
    }

    /**
     * @param string $filename
     * @return void
     * @deprecated 1.7 Use Media class that implements MediaUploadInterface instead.
     */
    public function deleteMediaFile(string $filename): void
    {
        user_error(__METHOD__ . '() is deprecated since Grav 1.7, use Media class that implements MediaUploadInterface instead', E_USER_DEPRECATED);

        $media = $this->getMedia();
        $media->deleteFile($filename);
        $this->clearMediaCache();
    }
}
