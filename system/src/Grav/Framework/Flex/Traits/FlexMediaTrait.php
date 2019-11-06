<?php

namespace Grav\Framework\Flex\Traits;

/**
 * @package    Grav\Framework\Flex
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

use Grav\Common\Cache;
use Grav\Common\Config\Config;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use Grav\Common\Media\Interfaces\MediaCollectionInterface;
use Grav\Common\Media\Traits\MediaTrait;
use Grav\Common\Page\Medium\AbstractMedia;
use Grav\Common\Page\Medium\Medium;
use Grav\Common\Page\Medium\MediumFactory;
use Grav\Common\Utils;
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

    protected $_uploads;

    public function __debugInfo()
    {
        return parent::__debugInfo() + [
            'uploads:private' => $this->getUpdatedMedia()
        ];
    }

    /**
     * @return string
     */
    public function getStorageFolder()
    {
        return $this->exists() ? $this->getFlexDirectory()->getStorageFolder($this->getStorageKey()) : '';
    }

    /**
     * @return string
     */
    public function getMediaFolder()
    {
        return $this->exists() ? $this->getFlexDirectory()->getMediaFolder($this->getStorageKey()) : '';
    }

    /**
     * @return MediaCollectionInterface
     */
    public function getMedia()
    {
        if ($this->media === null) {
            /** @var AbstractMedia $media */
            $media = $this->getExistingMedia();

            // Include uploaded media to the object media.
            /** @var FormFlashFile $upload */
            foreach ($this->getUpdatedMedia() as $filename => $upload) {
                // Just make sure we do not include removed or moved media.
                if ($upload && $upload->getError() === \UPLOAD_ERR_OK && !$upload->isMoved()) {
                    $media->add($filename, MediumFactory::fromUploadedFile($upload));
                }
            }

            $media->setTimestamps();
        }

        return $this->media;
    }

    public function checkUploadedMediaFile(UploadedFileInterface $uploadedFile)
    {
        $grav = Grav::instance();
        $language = $grav['language'];

        switch ($uploadedFile->getError()) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_NO_FILE:
                if ($uploadedFile instanceof FormFlashFile) {
                    break;
                }
                throw new RuntimeException($language->translate('PLUGIN_ADMIN.NO_FILES_SENT'), 400);
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                throw new RuntimeException($language->translate('PLUGIN_ADMIN.EXCEEDED_FILESIZE_LIMIT'), 400);
            case UPLOAD_ERR_NO_TMP_DIR:
                throw new RuntimeException($language->translate('PLUGIN_ADMIN.UPLOAD_ERR_NO_TMP_DIR'), 400);
            default:
                throw new RuntimeException($language->translate('PLUGIN_ADMIN.UNKNOWN_ERRORS'), 400);
        }

        $filename = $uploadedFile->getClientFilename();

        if (!Utils::checkFilename($filename)) {
            throw new RuntimeException(sprintf($language->translate('PLUGIN_ADMIN.FILEUPLOAD_UNABLE_TO_UPLOAD'), $filename, 'Bad filename'), 400);
        }

        $grav_limit = Utils::getUploadLimit();
        if ($grav_limit > 0 && $uploadedFile->getSize() > $grav_limit) {
            throw new RuntimeException($language->translate('PLUGIN_ADMIN.EXCEEDED_GRAV_FILESIZE_LIMIT'), 400);
        }

        $this->checkMediaFilename($filename);
    }

    public function checkMediaFilename(string $filename)
    {
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

    public function uploadMediaFile(UploadedFileInterface $uploadedFile, string $filename = null): void
    {
        $this->checkUploadedMediaFile($uploadedFile);

        if ($filename) {
            $this->checkMediaFilename(basename($filename));
        } else {
            $filename = $uploadedFile->getClientFilename();
        }

        $media = $this->getMedia();
        $grav = Grav::instance();

        /** @var UniformResourceLocator $locator */
        $locator = $grav['locator'];
        $path = $media->getPath();
        if (!$path) {
            $language = $grav['language'];

            throw new RuntimeException($language->translate('PLUGIN_ADMIN.FAILED_TO_MOVE_UPLOADED_FILE'), 400);
        }

        if ($locator->isStream($path)) {
            $path = $locator->findResource($path, true, true);
            $locator->clearCache($path);
        }

        try {
            // Upload it
            $filepath = sprintf('%s/%s', $path, $filename);
            Folder::create(\dirname($filepath));
            if ($uploadedFile instanceof FormFlashFile) {
                $metadata = $uploadedFile->getMetaData();
                if ($metadata) {
                    $file = YamlFile::instance($filepath . '.meta.yaml');
                    $file->save(['upload' => $metadata]);
                }
                if ($uploadedFile->getError() === \UPLOAD_ERR_OK) {
                    $uploadedFile->moveTo($filepath);
                } elseif (!file_exists($filepath) && $pos = strpos($filename, '/')) {
                    $origpath = sprintf('%s/%s', $path, substr($filename, $pos));
                    if (file_exists($origpath)) {
                        copy($origpath, $filepath);
                    }
                }
            } else {
                $uploadedFile->moveTo($filepath);
            }
        } catch (\Exception $e) {
            $language = $grav['language'];

            throw new RuntimeException($language->translate('PLUGIN_ADMIN.FAILED_TO_MOVE_UPLOADED_FILE'), 400);
        }

        $this->clearMediaCache();
    }

    public function deleteMediaFile(string $filename): void
    {
        $grav = Grav::instance();
        $language = $grav['language'];

        $basename = basename($filename);
        $dirname = dirname($filename);
        $dirname = $dirname === '.' ? '' : '/' . $dirname;

        if (!Utils::checkFilename($basename)) {
            throw new RuntimeException($language->translate('PLUGIN_ADMIN.FILE_COULD_NOT_BE_DELETED') . ': Bad filename: ' . $filename, 400);
        }

        $media = $this->getMedia();
        $path = $media->getPath();
        if (!$path) {
            return;
        }

        /** @var UniformResourceLocator $locator */
        $locator = $grav['locator'];

        $targetPath = $path . '/' . $dirname;
        $targetFile = $path . '/' . $filename;
        if ($locator->isStream($targetFile)) {
            $targetPath = $locator->findResource($targetPath, true, true);
            $targetFile = $locator->findResource($targetFile, true, true);
            $locator->clearCache($targetPath);
            $locator->clearCache($targetFile);
        }

        $fileParts  = pathinfo($basename);

        if (!file_exists($targetPath)) {
            return;
        }

        if (file_exists($targetFile)) {
            $result = unlink($targetFile);
            if (!$result) {
                throw new RuntimeException($language->translate('PLUGIN_ADMIN.FILE_COULD_NOT_BE_DELETED') . ': ' . $filename, 500);
            }
        }

        // Remove Extra Files
        foreach (scandir($targetPath, SCANDIR_SORT_NONE) as $file) {
            $preg_name = preg_quote($fileParts['filename'], '`');
            $preg_ext =preg_quote($fileParts['extension'], '`');
            $preg_filename = preg_quote($basename, '`');

            if (preg_match("`({$preg_name}@\d+x\.{$preg_ext}(?:\.meta\.yaml)?$|{$preg_filename}\.meta\.yaml)$`", $file)) {
                $testPath = $targetPath . '/' . $file;
                if ($locator->isStream($testPath)) {
                    $testPath = $locator->findResource($testPath, true, true);
                    $locator->clearCache($testPath);
                }

                if (is_file($testPath)) {
                    $result = unlink($testPath);
                    if (!$result) {
                        throw new RuntimeException($language->translate('PLUGIN_ADMIN.FILE_COULD_NOT_BE_DELETED') . ': ' . $filename, 500);
                    }
                }
            }
        }

        $this->clearMediaCache();
    }

    /**
     * @param array $files
     */
    protected function setUpdatedMedia(array $files): void
    {
        $list = [];
        foreach ($files as $field => $group) {
            if ($field === '' || \strpos($field, '/', true)) {
                continue;
            }
            foreach ($group as $filename => $file) {
                $list[$filename] = $file;
            }
        }

        $this->_uploads = $list;
    }

    /**
     * @return array
     */
    protected function getUpdatedMedia(): array
    {
        return $this->_uploads ?? [];
    }

    protected function saveUpdatedMedia(): void
    {
        /**
         * @var string $filename
         * @var UploadedFileInterface $file
         */
        foreach ($this->getUpdatedMedia() as $filename => $file) {
            if ($file) {
                $this->uploadMediaFile($file, $filename);
            } else {
                $this->deleteMediaFile($filename);
            }
        }

        $this->setUpdatedMedia([]);
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

        return $file && file_exists($file) ? MediumFactory::fromFile($file) : null;
    }

    /**
     * @return Cache
     */
    protected function getMediaCache()
    {
        return $this->getCache('object');
    }

    protected function offsetLoad_media()
    {
        return $this->getMedia();
    }

    protected function offsetSerialize_media()
    {
        return null;
    }

    abstract public function getFlexDirectory(): FlexDirectory;

    abstract public function getStorageKey(): string;
}
