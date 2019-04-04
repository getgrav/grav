<?php

/**
 * @package    Grav\Framework\Form
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Form;

use Grav\Common\Filesystem\Folder;
use Grav\Common\Utils;
use Psr\Http\Message\UploadedFileInterface;
use RocketTheme\Toolbox\File\YamlFile;

class FormFlash implements \JsonSerializable
{
    /** @var string */
    protected $sessionId;
    /** @var string */
    protected $uniqueId;
    /** @var string */
    protected $formName;
    /** @var string */
    protected $url;
    /** @var array */
    protected $user;
    /** @var array */
    protected $data;
    /** @var array */
    protected $files;
    /** @var array */
    protected $uploadedFiles;
    /** @var string[] */
    protected $uploadObjects;
    /** @var bool */
    protected $exists;

    /**
     * @param string $sessionId
     * @return string
     */
    public static function getSessionTmpDir(string $sessionId): string
    {
        return "tmp://forms/{$sessionId}";
    }

    /**
     * FormFlashObject constructor.
     * @param string $sessionId
     * @param string $uniqueId
     * @param string|null $formName
     */
    public function __construct(string $sessionId, string $uniqueId, string $formName = null)
    {
        $this->sessionId = $sessionId;
        $this->uniqueId = $uniqueId;

        $file = $this->getTmpIndex();
        $this->exists = $file->exists();

        if ($this->exists) {
            try {
                $data = (array)$file->content();
            } catch (\Exception $e) {
                $data = [];
            }
            $this->formName = null !== $formName ? $content['form'] ?? '' : '';
            $this->url = $data['url'] ?? '';
            $this->user = $data['user'] ?? null;
            $this->data = $data['data'] ?? null;
            $this->files = $data['files'] ?? [];
        } else {
            $this->formName = $formName;
            $this->url = '';
            $this->files = [];
        }
    }

    /**
     * @return string
     */
    public function getFormName(): string
    {
        return $this->formName;
    }

    /**
     * @return string
     */
    public function getUniqieId(): string
    {
        return $this->uniqueId;
    }

    /**
     * @return bool
     */
    public function exists(): bool
    {
        return $this->exists;
    }

    /**
     * @return $this
     */
    public function save(): self
    {
        $file = $this->getTmpIndex();
        $file->save($this->jsonSerialize());
        $this->exists = true;

        return $this;
    }

    public function delete(): self
    {
        $this->removeTmpDir();
        $this->files = [];
        $this->exists = false;

        return $this;
    }

    /**
     * @return string
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * @param string $url
     * @return $this
     */
    public function setUrl(string $url): self
    {
        $this->url = $url;

        return $this;
    }

    /**
     * @return string
     */
    public function getUsername(): string
    {
        return $this->user['username'] ?? '';
    }

    /**
     * @return string
     */
    public function getUserEmail(): string
    {
        return $this->user['email'] ?? '';
    }

    /**
     * @param string|null $username
     * @return $this
     */
    public function setUserName(string $username = null): self
    {
        $this->user['username'] = $username;

        return $this;
    }

    /**
     * @param string|null $email
     * @return $this
     */
    public function setUserEmail(string $email = null): self
    {
        $this->user['email'] = $email;

        return $this;
    }

    public function getData(): ?array
    {
        return $this->data;
    }

    public function setData(?array $data): void
    {
        $this->data = $data;
    }

    /**
     * @param string $field
     * @return array
     */
    public function getFilesByField(string $field): array
    {
        if (!isset($this->uploadObjects[$field])) {
            $objects = [];
            foreach ($this->files[$field] ?? [] as $name => $upload) {
                $objects[$name] = $upload ? new FormFlashFile($field, $upload, $this) : null;
            }
            $this->uploadedFiles[$field] = $objects;
        }

        return $this->uploadedFiles[$field];
    }

    /**
     * @param bool $includeOriginal
     * @return array
     */
    public function getFilesByFields($includeOriginal = false): array
    {
        $list = [];
        foreach ($this->files as $field => $values) {
            if (!$includeOriginal && strpos($field, '/')) {
                continue;
            }
            $list[$field] = $this->getFilesByField($field);
        }

        return $list;
    }

    /**
     * Add uploaded file to the form flash.
     *
     * @param UploadedFileInterface $upload
     * @param string|null $field
     * @param array|null $crop
     * @return string Return name of the file
     */
    public function addUploadedFile(UploadedFileInterface $upload, string $field = null, array $crop = null): string
    {
        $tmp_dir = $this->getTmpDir();
        $tmp_name = Utils::generateRandomString(12);
        $name = $upload->getClientFilename();

        // Prepare upload data for later save
        $data = [
            'name' => $name,
            'type' => $upload->getClientMediaType(),
            'size' => $upload->getSize(),
            'tmp_name' => $tmp_name
        ];

        Folder::create($tmp_dir);
        $upload->moveTo("{$tmp_dir}/{$tmp_name}");

        $this->addFileInternal($field, $name, $data, $crop);

        return $name;
    }


    /**
     * Add existing file to the form flash.
     *
     * @param string $filename
     * @param string $field
     * @param array $crop
     * @return bool
     */
    public function addFile(string $filename, string $field, array $crop = null): bool
    {
        if (!file_exists($filename)) {
            throw new \RuntimeException("File not found: {$filename}");
        }

        // Prepare upload data for later save
        $data = [
            'name' => basename($filename),
            'type' => Utils::getMimeByLocalFile($filename),
            'size' => filesize($filename),
        ];

        $this->addFileInternal($field, $data['name'], $data, $crop);

        return true;
    }

    /**
     * Remove any file from form flash.
     *
     * @param string $name
     * @param string $field
     * @return bool
     */
    public function removeFile(string $name, string $field = null): bool
    {
        if (!$name) {
            return false;
        }

        $field = $field ?: 'undefined';

        $upload = $this->files[$field][$name] ?? null;
        if (null !== $upload) {
            $this->removeTmpFile($upload['tmp_name'] ?? '');
        }
        $upload = $this->files[$field . '/original'][$name] ?? null;
        if (null !== $upload) {
            $this->removeTmpFile($upload['tmp_name'] ?? '');
        }

        // Mark file as deleted.
        $this->files[$field][$name] = null;
        $this->files[$field . '/original'][$name] = null;

        unset(
            $this->uploadedFiles[$field][$name],
            $this->uploadedFiles[$field . '/original'][$name]
        );

        return true;
    }

    /**
     * Clear form flash from all uploaded files.
     */
    public function clearFiles()
    {
        foreach ($this->files as $field => $files) {
            foreach ($files as $name => $upload) {
                $this->removeTmpFile($upload['tmp_name'] ?? '');
            }
        }

        $this->files = [];
    }

    /**
     * @return array
     */
    public function jsonSerialize(): array
    {
        return [
            'form' => $this->formName,
            'unique_id' => $this->uniqueId,
            'url' => $this->url,
            'user' => $this->user,
            'data' => $this->data,
            'files' => $this->files
        ];
    }

    /**
     * @return string
     */
    public function getTmpDir(): string
    {
        return static::getSessionTmpDir($this->sessionId) . '/' . $this->uniqueId;
    }

    /**
     * @return YamlFile
     */
    protected function getTmpIndex(): YamlFile
    {
        // Do not use CompiledYamlFile as the file can change multiple times per second.
        return YamlFile::instance($this->getTmpDir() . '/index.yaml');
    }

    /**
     * @param string $name
     */
    protected function removeTmpFile(string $name): void
    {
        $filename = $this->getTmpDir() . '/' . $name;
        if ($name && is_file($filename)) {
            unlink($filename);
        }
    }

    protected function removeTmpDir(): void
    {
        $tmpDir = $this->getTmpDir();
        if (file_exists($tmpDir)) {
            Folder::delete($tmpDir);
        }
    }

    /**
     * @param string $field
     * @param string $name
     * @param array $data
     * @param array|null $crop
     */
    protected function addFileInternal(?string $field, string $name, array $data, array $crop = null): void
    {
        $field = $field ?: 'undefined';
        if (!isset($this->files[$field])) {
            $this->files[$field] = [];
        }

        $oldUpload = $this->files[$field][$name] ?? null;

        if ($crop) {
            // Deal with crop upload
            if ($oldUpload) {
                $originalUpload = $this->files[$field . '/original'][$name] ?? null;
                if ($originalUpload) {
                    // If there is original file already present, remove the modified file
                    $this->files[$field . '/original'][$name]['crop'] = $crop;
                    $this->removeTmpFile($oldUpload['tmp_name'] ?? '');
                } else {
                    // Otherwise make the previous file as original
                    $oldUpload['crop'] = $crop;
                    $this->files[$field . '/original'][$name] = $oldUpload;
                }
            } else {
                $this->files[$field . '/original'][$name] = [
                    'name' => $name,
                    'type' => $data['type'],
                    'crop' => $crop
                ];
            }
        } else {
            // Deal with replacing upload
            $originalUpload = $this->files[$field . '/original'][$name] ?? null;
            $this->files[$field . '/original'][$name] = null;

            $this->removeTmpFile($oldUpload['tmp_name'] ?? '');
            $this->removeTmpFile($originalUpload['tmp_name'] ?? '');
        }

        // Prepare data to be saved later
        $this->files[$field][$name] = $data;
    }
}
