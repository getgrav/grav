<?php

/**
 * @package    Grav\Framework\Form
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Form;

use Exception;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use Grav\Common\User\Interfaces\UserInterface;
use Grav\Common\Utils;
use Grav\Framework\Form\Interfaces\FormFlashInterface;
use Psr\Http\Message\UploadedFileInterface;
use RocketTheme\Toolbox\File\YamlFile;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;
use RuntimeException;
use function func_get_args;
use function is_array;

/**
 * Class FormFlash
 * @package Grav\Framework\Form
 */
class FormFlash implements FormFlashInterface
{
    /** @var bool */
    protected $exists;
    /** @var string */
    protected $sessionId;
    /** @var string */
    protected $uniqueId;
    /** @var string */
    protected $formName;
    /** @var string */
    protected $url;
    /** @var array|null */
    protected $user;
    /** @var int */
    protected $createdTimestamp;
    /** @var int */
    protected $updatedTimestamp;
    /** @var array|null */
    protected $data;
    /** @var array */
    protected $files;
    /** @var array */
    protected $uploadedFiles;
    /** @var string[] */
    protected $uploadObjects;
    /** @var string */
    protected $folder;

    /**
     * @inheritDoc
     */
    public function __construct($config)
    {
        // Backwards compatibility with Grav 1.6 plugins.
        if (!is_array($config)) {
            user_error(__CLASS__ . '::' . __FUNCTION__ . '($sessionId, $uniqueId, $formName) is deprecated since Grav 1.6.11, use $config parameter instead', E_USER_DEPRECATED);

            $args = func_get_args();
            $config = [
                'session_id' => $args[0],
                'unique_id' => $args[1] ?? null,
                'form_name' => $args[2] ?? null,
            ];
            $config = array_filter($config, static function ($val) {
                return $val !== null;
            });
        }

        $this->sessionId = $config['session_id'] ?? 'no-session';
        $this->uniqueId = $config['unique_id'] ?? '';

        $folder = $config['folder'] ?? ($this->sessionId ? 'tmp://forms/' . $this->sessionId : '');

        /** @var UniformResourceLocator $locator */
        $locator = Grav::instance()['locator'];

        $this->folder = $folder && $locator->isStream($folder) ? $locator->findResource($folder, true, true) : $folder;

        $this->init($this->loadStoredForm(), $config);
    }

    /**
     * @param array|null $data
     * @param array $config
     */
    protected function init(?array $data, array $config): void
    {
        if (null === $data) {
            $this->exists = false;
            $this->formName = $config['form_name'] ?? '';
            $this->url = '';
            $this->createdTimestamp = $this->updatedTimestamp = time();
            $this->files = [];
        } else {
            $this->exists = true;
            $this->formName = $data['form'] ?? $config['form_name'] ?? '';
            $this->url = $data['url'] ?? '';
            $this->user = $data['user'] ?? null;
            $this->updatedTimestamp = $data['timestamps']['updated'] ?? time();
            $this->createdTimestamp = $data['timestamps']['created'] ?? $this->updatedTimestamp;
            $this->data = $data['data'] ?? null;
            $this->files = $data['files'] ?? [];
        }
    }

    /**
     * Load raw flex flash data from the filesystem.
     *
     * @return array|null
     */
    protected function loadStoredForm(): ?array
    {
        $file = $this->getTmpIndex();
        $exists = $file && $file->exists();

        $data = null;
        if ($exists) {
            try {
                $data = (array)$file->content();
            } catch (Exception $e) {
            }
        }

        return $data;
    }

    /**
     * @inheritDoc
     */
    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    /**
     * @inheritDoc
     */
    public function getUniqueId(): string
    {
        return $this->uniqueId;
    }

    /**
     * @return string
     * @deprecated 1.6.11 Use '->getUniqueId()' method instead.
     */
    public function getUniqieId(): string
    {
        user_error(__CLASS__ . '::' . __FUNCTION__ . '() is deprecated since Grav 1.6.11, use ->getUniqueId() method instead', E_USER_DEPRECATED);

        return $this->getUniqueId();
    }

    /**
     * @inheritDoc
     */
    public function getFormName(): string
    {
        return $this->formName;
    }


    /**
     * @inheritDoc
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * @inheritDoc
     */
    public function getUsername(): string
    {
        return $this->user['username'] ?? '';
    }

    /**
     * @inheritDoc
     */
    public function getUserEmail(): string
    {
        return $this->user['email'] ?? '';
    }

    /**
     * @inheritDoc
     */
    public function getCreatedTimestamp(): int
    {
        return $this->createdTimestamp;
    }

    /**
     * @inheritDoc
     */
    public function getUpdatedTimestamp(): int
    {
        return $this->updatedTimestamp;
    }


    /**
     * @inheritDoc
     */
    public function getData(): ?array
    {
        return $this->data;
    }

    /**
     * @inheritDoc
     */
    public function setData(?array $data): void
    {
        $this->data = $data;
    }

    /**
     * @inheritDoc
     */
    public function exists(): bool
    {
        return $this->exists;
    }

    /**
     * @inheritDoc
     */
    public function save(bool $force = false)
    {
        if (!($this->folder && $this->uniqueId)) {
            return $this;
        }

        if ($force || $this->data || $this->files) {
            // Only save if there is data or files to be saved.
            $file = $this->getTmpIndex();
            if ($file) {
                $file->save($this->jsonSerialize());
                $this->exists = true;
            }
        } elseif ($this->exists) {
            // Delete empty form flash if it exists (it carries no information).
            return $this->delete();
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function delete()
    {
        if ($this->folder && $this->uniqueId) {
            $this->removeTmpDir();
            $this->files = [];
            $this->exists = false;
        }

        return $this;
    }

    /**
     * @inheritDoc
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
     * @inheritDoc
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
     * @inheritDoc
     */
    public function addUploadedFile(UploadedFileInterface $upload, string $field = null, array $crop = null): string
    {
        $tmp_dir = $this->getTmpDir();
        $tmp_name = Utils::generateRandomString(12);
        $name = $upload->getClientFilename();
        if (!$name) {
            throw new RuntimeException('Uploaded file has no filename');
        }

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
     * @inheritDoc
     */
    public function addFile(string $filename, string $field, array $crop = null): bool
    {
        if (!file_exists($filename)) {
            throw new RuntimeException("File not found: {$filename}");
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
     * @inheritDoc
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
     * @inheritDoc
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
     * @inheritDoc
     */
    public function jsonSerialize(): array
    {
        return [
            'form' => $this->formName,
            'unique_id' => $this->uniqueId,
            'url' => $this->url,
            'user' => $this->user,
            'timestamps' => [
                'created' => $this->createdTimestamp,
                'updated' => time(),
            ],
            'data' => $this->data,
            'files' => $this->files
        ];
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
     * @param UserInterface|null $user
     * @return $this
     */
    public function setUser(UserInterface $user = null)
    {
        if ($user && $user->username) {
            $this->user = [
                'username' => $user->username,
                'email' => $user->email ?? ''
            ];
        } else {
            $this->user = null;
        }

        return $this;
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

    /**
     * @return string
     */
    public function getTmpDir(): string
    {
        return $this->folder && $this->uniqueId ? "{$this->folder}/{$this->uniqueId}" : '';
    }

    /**
     * @return ?YamlFile
     */
    protected function getTmpIndex(): ?YamlFile
    {
        $tmpDir = $this->getTmpDir();

        // Do not use CompiledYamlFile as the file can change multiple times per second.
        return $tmpDir ? YamlFile::instance($tmpDir . '/index.yaml') : null;
    }

    /**
     * @param string $name
     */
    protected function removeTmpFile(string $name): void
    {
        $tmpDir = $this->getTmpDir();
        $filename =  $tmpDir ? $tmpDir . '/' . $name : '';
        if ($name && $filename && is_file($filename)) {
            unlink($filename);
        }
    }

    /**
     * @return void
     */
    protected function removeTmpDir(): void
    {
        // Make sure that index file cache gets always cleared.
        $file = $this->getTmpIndex();
        if ($file) {
            $file->free();
        }

        $tmpDir = $this->getTmpDir();
        if ($tmpDir && file_exists($tmpDir)) {
            Folder::delete($tmpDir);
        }
    }

    /**
     * @param string|null $field
     * @param string $name
     * @param array $data
     * @param array|null $crop
     * @return void
     */
    protected function addFileInternal(?string $field, string $name, array $data, array $crop = null): void
    {
        if (!($this->folder && $this->uniqueId)) {
            throw new RuntimeException('Cannot upload files: form flash folder not defined');
        }

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
