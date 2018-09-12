<?php

namespace Grav\Framework\Flex\Traits;

/**
 * @package    Grav\Framework\Flex
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

use Grav\Common\Config\Config;
use Grav\Common\Grav;
use Grav\Common\Media\Traits\MediaTrait;
use Psr\Http\Message\UploadedFileInterface;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;
use RuntimeException;

/**
 * Implements Grav Page content and header manipulation methods.
 */
trait FlexMediaTrait
{
    use MediaTrait;

    public function uploadMediaFile(UploadedFileInterface $uploadedFile) : void
    {
        $grav = Grav::instance();
        $language = $grav['language'];

        switch ($uploadedFile->getError()) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_NO_FILE:
                throw new RuntimeException($language->translate('PLUGIN_ADMIN.NO_FILES_SENT'), 400);
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                throw new RuntimeException($language->translate('PLUGIN_ADMIN.EXCEEDED_FILESIZE_LIMIT'), 400);
            case UPLOAD_ERR_NO_TMP_DIR:
                throw new RuntimeException($language->translate('PLUGIN_ADMIN.UPLOAD_ERR_NO_TMP_DIR'), 400);
            default:
                throw new RuntimeException($language->translate('PLUGIN_ADMIN.UNKNOWN_ERRORS'), 400);
        }

        /** @var Config $config */
        $config = $grav['config'];
        $grav_limit = (int) $config->get('system.media.upload_limit', 0);

        if ($grav_limit > 0 && $uploadedFile->getSize() > $grav_limit) {
            throw new RuntimeException($language->translate('PLUGIN_ADMIN.EXCEEDED_GRAV_FILESIZE_LIMIT'), 400);
        }

        // Check the file extension.
        $filename = $uploadedFile->getClientFilename();
        $fileParts = pathinfo($filename);
        $extension = isset($fileParts['extension']) ? strtolower($fileParts['extension']) : '';

        // If not a supported type, return
        if (!$extension || !$config->get("media.types.{$extension}")) {
            throw new RuntimeException($language->translate('PLUGIN_ADMIN.UNSUPPORTED_FILE_TYPE') . ': ' . $extension, 400);
        }

        $media = $this->getMedia();

        /** @var UniformResourceLocator $locator */
        $locator = $grav['locator'];
        $path = $media->path();
        if ($locator->isStream($path)) {
            $path = $locator->findResource($path, true, true);
            $locator->clearCache($path);
        }

        try {
            // Upload it
            $uploadedFile->moveTo(sprintf('%s/%s', $path, $filename));
        } catch (\Exception $e) {
            throw new RuntimeException($language->translate('PLUGIN_ADMIN.FAILED_TO_MOVE_UPLOADED_FILE'), 400);
        }

        $this->clearMediaCache();
    }

    public function deleteMediaFile(string $filename) : void
    {
        $grav = Grav::instance();
        $language = $grav['language'];

        $media = $this->getMedia();

        /** @var UniformResourceLocator $locator */
        $locator = $grav['locator'];

        $targetPath = $media->path() . '/' . $filename;
        if ($locator->isStream($targetPath)) {
            $targetPath = $locator->findResource($targetPath, true, true);
            $locator->clearCache($targetPath);
        }

        $fileParts  = pathinfo($filename);
        $found = false;

        if (file_exists($targetPath)) {
            $found  = true;

            $result = unlink($targetPath);
            if (!$result) {
                throw new RuntimeException($language->translate('PLUGIN_ADMIN.FILE_COULD_NOT_BE_DELETED') . ': ' . $filename, 500);
            }
        }

        // Remove Extra Files
        foreach (scandir($media->path(), SCANDIR_SORT_NONE) as $file) {
            if (preg_match("/{$fileParts['filename']}@\d+x\.{$fileParts['extension']}(?:\.meta\.yaml)?$|{$filename}\.meta\.yaml$/", $file)) {

                $targetPath = $media->path() . '/' . $file;
                if ($locator->isStream($targetPath)) {
                    $targetPath = $locator->findResource($targetPath, true, true);
                    $locator->clearCache($targetPath);
                }

                $result = unlink($targetPath);
                if (!$result) {
                    throw new RuntimeException($language->translate('PLUGIN_ADMIN.FILE_COULD_NOT_BE_DELETED') . ': ' . $filename, 500);
                }

                $found = true;
            }
        }

        $this->clearMediaCache();

        if (!$found) {
            throw new RuntimeException($language->translate('PLUGIN_ADMIN.FILE_NOT_FOUND') . ': ' . $filename, 500);
        }
    }
}
