<?php

/**
 * @package    Grav\Common\Form
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Form;

use Grav\Common\Filesystem\Folder;
use Grav\Framework\Form\FormFlash as FrameworkFormFlash;
use function is_array;

/**
 * Class FormFlash
 * @package Grav\Common\Form
 */
class FormFlash extends FrameworkFormFlash
{
    /**
     * @return array
     * @deprecated 1.6 For backwards compatibility only, do not use
     */
    public function getLegacyFiles(): array
    {
        $fields = [];
        foreach ($this->files as $field => $files) {
            if (strpos($field, '/')) {
                continue;
            }
            foreach ($files as $file) {
                if (is_array($file)) {
                    $file['tmp_name'] = $this->getTmpDir() . '/' . $file['tmp_name'];
                    $fields[$field][$file['path'] ?? $file['name']] = $file;
                }
            }
        }

        return $fields;
    }

    /**
     * @param string $field
     * @param string $filename
     * @param array $upload
     * @return bool
     * @deprecated 1.6 For backwards compatibility only, do not use
     */
    public function uploadFile(string $field, string $filename, array $upload): bool
    {
        if (!$this->uniqueId) {
            return false;
        }

        $tmp_dir = $this->getTmpDir();
        Folder::create($tmp_dir);

        $tmp_file = $upload['file']['tmp_name'];
        $basename = basename($tmp_file);

        if (!move_uploaded_file($tmp_file, $tmp_dir . '/' . $basename)) {
            return false;
        }

        $upload['file']['tmp_name'] = $basename;
        $upload['file']['name'] = $filename;

        $this->addFileInternal($field, $filename, $upload['file']);

        return true;
    }

    /**
     * @param string $field
     * @param string $filename
     * @param array $upload
     * @param array $crop
     * @return bool
     * @deprecated 1.6 For backwards compatibility only, do not use
     */
    public function cropFile(string $field, string $filename, array $upload, array $crop): bool
    {
        if (!$this->uniqueId) {
            return false;
        }

        $tmp_dir = $this->getTmpDir();
        Folder::create($tmp_dir);

        $tmp_file = $upload['file']['tmp_name'];
        $basename = basename($tmp_file);

        if (!move_uploaded_file($tmp_file, $tmp_dir . '/' . $basename)) {
            return false;
        }

        $upload['file']['tmp_name'] = $basename;
        $upload['file']['name'] = $filename;

        $this->addFileInternal($field, $filename, $upload['file'], $crop);

        return true;
    }
}
