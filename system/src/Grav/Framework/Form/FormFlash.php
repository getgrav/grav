<?php

/**
 * @package    Grav\Framework\Form
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Form;

use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use Grav\Common\Session;
use Grav\Common\User\User;
use RocketTheme\Toolbox\File\YamlFile;

class FormFlash implements \JsonSerializable
{
    /** @var string */
    protected $form;
    /** @var string */
    protected $uniqueId;
    /** @var string */
    protected $url;
    /** @var array */
    protected $user;
    /** @var array */
    protected $uploads;
    /** @var array */
    protected $uploadObjects;
    /** @var bool */
    protected $exists;

    /**
     * FormFlashObject constructor.
     * @param string $form
     * @param string $uniqueId
     */
    public function __construct(string $form, $uniqueId = null)
    {
        $this->form = $form;
        $this->uniqueId = $uniqueId;

        $file = $this->getTmpIndex();
        $this->exists = $file->exists();

        $data = $this->exists ? (array)$file->content() : [];
        $this->url = $data['url'] ?? null;
        $this->user = $data['user'] ?? null;
        $this->uploads = $data['uploads'] ?? [];
    }

    /**
     * @return string
     */
    public function getFormName() : string
    {
        return $this->form;
    }

    /**
     * @return string
     */
    public function getUniqieId() : string
    {
        return $this->uniqueId ?? $this->getFormName();
    }

    /**
     * @return bool
     */
    public function exists() : bool
    {
        return $this->exists;
    }

    /**
     * @return $this
     */
    public function save() : self
    {
        $file = $this->getTmpIndex();
        $file->save($this->jsonSerialize());
        $this->exists = true;

        return $this;
    }

    public function delete() : self
    {
        $this->removeTmpDir();
        $this->uploads = [];
        $this->exists = false;

        return $this;
    }

    /**
     * @return string
     */
    public function getUrl() : string
    {
        return $this->url ?? '';
    }

    /**
     * @param string $url
     * @return $this
     */
    public function setUrl(string $url) : self
    {
        $this->url = $url;

        return $this;
    }

    /**
     * @return string
     */
    public function getUsername() : string
    {
        return $this->user['username'] ?? '';
    }

    /**
     * @return string
     */
    public function getUserEmail() : string
    {
        return $this->user['email'] ?? '';
    }

    /**
     * @param User|null $user
     * @return $this
     */
    public function setUser(?User $user = null) : self
    {
        if ($user && $user->username) {
            $this->user = [
                'username' => $user->username,
                'email' => $user->email
            ];
        } else {
            $this->user = null;
        }

        return $this;
    }


    /**
     * @param string $field
     * @return array
     */
    public function getFilesByField(string $field) : array
    {
        if (!isset($this->uploadObjects[$field])) {
            $objects = [];
            foreach ($this->uploads[$field] ?? [] as $filename => $upload) {
                $objects[$filename] = new FormFlashFile($field, $upload, $this);
            }
            $this->uploadObjects[$field] = $objects;
        }

        return $this->uploadObjects[$field];
    }

    /**
     * @return array
     */
    public function getFilesByFields() : array
    {
        $list = [];
        foreach ($this->uploads as $field => $values) {
            if (strpos($field, '/')) {
                continue;
            }
            $list[$field] = $this->getFilesByField($field);
        }

        return $list;
    }

    /**
     * @return array
     * @deprecated 1.6 For backwards compatibility only, do not use.
     */
    public function getLegacyFiles() : array
    {
        $fields = [];
        foreach ($this->uploads as $field => $files) {
            if (strpos($field, '/')) {
                continue;
            }
            foreach ($files as $file) {
                $file['tmp_name'] = $this->getTmpDir() . '/' . $file['tmp_name'];
                $fields[$field][$file['path'] ?? $file['name']] = $file;
            }
        }

        return $fields;
    }

    /**
     * @param string $field
     * @param string $filename
     * @param array $upload
     * @return bool
     */
    public function uploadFile(string $field, string $filename, array $upload) : bool
    {
        $tmp_dir = $this->getTmpDir();

        Folder::create($tmp_dir);

        $tmp_file = $upload['file']['tmp_name'];
        $basename = basename($tmp_file);

        if (!move_uploaded_file($tmp_file, $tmp_dir . '/' . $basename)) {
            return false;
        }

        $upload['file']['tmp_name'] = $basename;

        if (!isset($this->uploads[$field])) {
            $this->uploads[$field] = [];
        }

        // Prepare object for later save
        $upload['file']['name'] = $filename;

        // Replace old file, including original
        $oldUpload = $this->uploads[$field][$filename] ?? null;
        if (isset($oldUpload['tmp_name'])) {
            $this->removeTmpFile($oldUpload['tmp_name']);
        }

        $originalUpload = $this->uploads[$field . '/original'][$filename] ?? null;
        if (isset($originalUpload['tmp_name'])) {
            $this->removeTmpFile($originalUpload['tmp_name']);
            unset($this->uploads[$field . '/original'][$filename]);
        }

        // Prepare data to be saved later
        $this->uploads[$field][$filename] = $upload['file'];

        return true;
    }

    /**
     * @param string $field
     * @param string $filename
     * @param array $upload
     * @param array $crop
     * @return bool
     */
    public function cropFile(string $field, string $filename, array $upload, array $crop) : bool
    {
        $tmp_dir = $this->getTmpDir();

        Folder::create($tmp_dir);

        $tmp_file = $upload['file']['tmp_name'];
        $basename = basename($tmp_file);

        if (!move_uploaded_file($tmp_file, $tmp_dir . '/' . $basename)) {
            return false;
        }

        $upload['file']['tmp_name'] = $basename;

        if (!isset($this->uploads[$field])) {
            $this->uploads[$field] = [];
        }

        // Prepare object for later save
        $upload['file']['name'] = $filename;

        $oldUpload = $this->uploads[$field][$filename] ?? null;
        if ($oldUpload) {
            $originalUpload = $this->uploads[$field . '/original'][$filename] ?? null;
            if ($originalUpload) {
                $this->removeTmpFile($oldUpload['tmp_name']);
            } else {
                $oldUpload['crop'] = $crop;
                $this->uploads[$field . '/original'][$filename] = $oldUpload;
            }
        }

        // Prepare data to be saved later
        $this->uploads[$field][$filename] = $upload['file'];

        return true;
    }

    /**
     * @param string $field
     * @param string $filename
     * @return bool
     */
    public function removeFile(string $field, string $filename) : bool
    {
        if (!$field || !$filename) {
            return false;
        }

        $file = $this->getTmpIndex();
        if (!$file->exists()) {
            return false;
        }

        $upload = $this->uploads[$field][$filename] ?? null;
        if (null !== $upload) {
            $this->removeTmpFile($upload['tmp_name'] ?? '');
        }
        $upload = $this->uploads[$field . '/original'][$filename] ?? null;
        if (null !== $upload) {
            $this->removeTmpFile($upload['tmp_name'] ?? '');
        }

        // Walk backward to cleanup any empty field that's left
        unset(
            $this->uploadObjects[$field][$filename],
            $this->uploads[$field][$filename],
            $this->uploadObjects[$field . '/original'][$filename],
            $this->uploads[$field . '/original'][$filename]
        );
        if (empty($this->uploads[$field])) {
            unset($this->uploads[$field]);
        }
        if (empty($this->uploads[$field . '/original'])) {
            unset($this->uploads[$field . '/original']);
        }

        return true;
    }

    /**
     * @return array
     */
    public function jsonSerialize() : array
    {
        return [
            'form' => $this->form,
            'unique_id' => $this->uniqueId,
            'url' => $this->url,
            'user' => $this->user,
            'uploads' => $this->uploads
        ];
    }

    /**
     * @return string
     */
    public function getTmpDir() : string
    {
        $grav = Grav::instance();

        /** @var Session $session */
        $session = $grav['session'];

        $location = [
            'forms',
            $session->getId(),
            $this->uniqueId ?: $this->form
        ];

        return $grav['locator']->findResource('tmp://', true, true) . '/' . implode('/', $location);
    }

    /**
     * @return YamlFile
     */
    protected function getTmpIndex() : YamlFile
    {
        // Do not use CompiledYamlFile as the file can change multiple times per second.
        return YamlFile::instance($this->getTmpDir() . '/index.yaml');
    }

    /**
     * @param string $name
     */
    protected function removeTmpFile(string $name) : void
    {
        $filename = $this->getTmpDir() . '/' . $name;
        if ($name && is_file($filename)) {
            unlink($filename);
        }
    }

    protected function removeTmpDir() : void
    {
        $tmpDir = $this->getTmpDir();
        if (file_exists($tmpDir)) {
            Folder::delete($tmpDir);
        }
    }
}
