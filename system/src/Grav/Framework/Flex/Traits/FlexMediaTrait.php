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

    public function __debugInfo()
    {
        return parent::__debugInfo() + [
            'uploads:private' => $this->getUpdatedMedia()
        ];
    }

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
            $updated = false;
            $media = $this->getExistingMedia();

            // Include uploaded media to the object media.
            /**
             * @var string $filename
             * @var UploadedFileInterface|null $upload
             */
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

        return $media;
    }

    /**
     * @param UploadedFileInterface $uploadedFile
     * @return void
     */
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

        $filename = $uploadedFile->getClientFilename() ?? '';

        if (!Utils::checkFilename($filename)) {
            throw new RuntimeException(sprintf($language->translate('PLUGIN_ADMIN.FILEUPLOAD_UNABLE_TO_UPLOAD'), $filename, 'Bad filename'), 400);
        }

        $grav_limit = Utils::getUploadLimit();
        if ($grav_limit > 0 && $uploadedFile->getSize() > $grav_limit) {
            throw new RuntimeException($language->translate('PLUGIN_ADMIN.EXCEEDED_GRAV_FILESIZE_LIMIT'), 400);
        }

        $this->checkMediaFilename($filename);
    }

    /**
     * @param string $filename
     * @return void
     */
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

    /**
     * @param UploadedFileInterface $uploadedFile
     * @param string|null $filename
     * @return void
     */
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
            $path = (string)$locator->findResource($path, true, true);
            $locator->clearCache($path);
        }

        try {
            $filesystem = Filesystem::getInstance(false);

            // Upload it
            $filepath = sprintf('%s/%s', $path, $filename);
            Folder::create($filesystem->dirname($filepath));
            if ($uploadedFile instanceof FormFlashFile) {
                $metadata = $uploadedFile->getMetaData();
                if ($metadata) {
                    $file = YamlFile::instance($filepath . '.meta.yaml');
                    $file->save(['upload' => $metadata]);
                }
                if ($uploadedFile->getError() === \UPLOAD_ERR_OK) {
                    $uploadedFile->moveTo($filepath);
                } elseif ($filename && !file_exists($filepath) && $pos = strpos($filename, '/')) {
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

    /**
     * @param string $filename
     * @return void
     */
    public function deleteMediaFile(string $filename): void
    {
        $grav = Grav::instance();
        $language = $grav['language'];

        $filesystem = Filesystem::getInstance(false);

        $basename = basename($filename);
        $dirname = $filesystem->dirname($filename);
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
            $targetPath = (string)$locator->findResource($targetPath, true, true);
            $targetFile = (string)$locator->findResource($targetFile, true, true);
            $locator->clearCache($targetPath);
            $locator->clearCache($targetFile);
        }

        $fileParts = (array)$filesystem->pathinfo($basename);

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
        $dir = scandir($targetPath, SCANDIR_SORT_NONE);
        if (false === $dir) {
            throw new \RuntimeException('Internal error');
        }

        foreach ($dir as $file) {
            $preg_name = preg_quote($fileParts['filename'], '`');
            $preg_ext = preg_quote($fileParts['extension'] ?? '.', '`');
            $preg_filename = preg_quote($basename, '`');

            if (preg_match("`({$preg_name}@\d+x\.{$preg_ext}(?:\.meta\.yaml)?$|{$preg_filename}\.meta\.yaml)$`", $file)) {
                $testPath = $targetPath . '/' . $file;
                if ($locator->isStream($testPath)) {
                    $testPath = (string)$locator->findResource($testPath, true, true);
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
     * @return void
     */
    protected function setUpdatedMedia(array $files): void
    {
        $list = [];
        foreach ($files as $field => $group) {
            if ($field === '' || \strpos((string)$field, '/')) {
                continue;
            }
            foreach ($group as $filename => $file) {
                $list[(string)$filename] = $file;
            }
        }

        $this->_uploads = $list;
    }

    /**
     * @return array<UploadedFileInterface|null>
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
        /**
         * @var string $filename
         * @var UploadedFileInterface|null $file
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
}
