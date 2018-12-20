<?php

/**
 * @package    Grav\Framework\Flex
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Flex;

use Grav\Common\Data\Blueprint;
use Grav\Common\Data\Data;
use Grav\Common\Data\ValidationException;
use Grav\Common\Grav;
use Grav\Common\Utils;
use Grav\Framework\Flex\Interfaces\FlexFormInterface;
use Grav\Framework\Flex\Interfaces\FlexObjectInterface;
use Grav\Framework\Form\FormFlash;
use Grav\Framework\Route\Route;
use Grav\Framework\Session\Session;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;

/**
 * Class FlexForm
 * @package Grav\Framework\Flex
 */
class FlexForm implements FlexFormInterface
{
    /** @var string */
    private $name;
    /** @var string */
    private $id;
    /** @var string */
    private $uniqueid;
    /** @var bool */
    private $submitted;
    /** @var string[] */
    private $errors;
    /** @var Data|FlexObjectInterface */
    private $data;
    /** @var array|UploadedFileInterface[] */
    private $files;
    /** @var FlexObjectInterface */
    private $object;
    /** @var array $form */
    private $form;
    /** @var FormFlash */
    private $flash;
    /** @var Blueprint */
    private $blueprint;

    /**
     * FlexForm constructor.
     * @param string $name
     * @param FlexObjectInterface $object
     * @param array|null $form
     */
    public function __construct(string $name, FlexObjectInterface $object, array $form = null)
    {
        $this->name = $name;
        $this->form = $form;
        $this->setObject($object);
        $this->setId($this->getName());
        $this->reset();
    }

    /**
     * Get HTML id="..." attribute.
     *
     * Defaults to 'flex-[type]-[name]', where 'type' is object type and 'name' is the first parameter given in constructor.
     *
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Sets HTML id="" attribute.
     *
     * @param string $id
     */
    public function setId(string $id): void
    {
        $this->id = $id;
    }

    /**
     * Get unique id for the current form instance. By default regenerated on every page reload.
     *
     * This id is used to load the saved form state, if available.
     *
     * @return string
     */
    public function getUniqueId(): string
    {
        if (null === $this->uniqueid) {
            $this->uniqueid = Utils::generateRandomString(20);
        }

        return $this->uniqueid;
    }

    /**
     * Sets unique form id allowing you to attach the form state to the object for example.
     *
     * @param string $uniqueId
     */
    public function setUniqueId(string $uniqueId): void
    {
        $this->uniqueid = $uniqueId;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        $object = $this->getObject();
        $name = $this->name ?: 'object';

        return "flex-{$object->getType(false)}-{$name}";
    }

    /**
     * @return string
     */
    public function getFormName(): string
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getNonceName(): string
    {
        return 'form-nonce';
    }

    /**
     * @return string
     */
    public function getNonceAction(): string
    {
        return 'form';
    }

    /**
     * @return string
     */
    public function getAction(): string
    {
        // TODO:
        return '';
    }

    /**
     * @return Data|FlexObjectInterface
     */
    public function getData()
    {
        return $this->data ?? $this->getObject();
    }

    /**
     * @return array|UploadedFileInterface[]
     */
    public function getFiles(): array
    {
        return $this->files;
    }

    /**
     * Get a value from the form.
     *
     * Note: Used in form fields.
     *
     * @param string $name
     * @return mixed
     */
    public function getValue(string $name)
    {
        if (null === $this->data) {
            return $this->getObject()->getNestedProperty($name);
        }

        return $this->data->get($name);
    }

    /**
     * @return FlexObjectInterface
     */
    public function getObject(): FlexObjectInterface
    {
        return $this->object;
    }

    /**
     * @param ServerRequestInterface $request
     * @return $this
     */
    public function handleRequest(ServerRequestInterface $request): FlexFormInterface
    {
        try {
            $method = $request->getMethod();
            if (!\in_array($method, ['PUT', 'POST', 'PATCH'])) {
                throw new \RuntimeException(sprintf('FlexForm: Bad HTTP method %s', $method));
            }

            $flash = $this->getFlash();

            $includeOriginal = (bool)($this->getBlueprint()->form()['images']['original'] ?? null);
            $files = $flash->getFilesByFields($includeOriginal);
            $data = $request->getParsedBody();

            $this->submit($data, $files);

            $flash->delete();
        } catch (\Exception $e) {
            $this->errors[] = $e->getMessage();
        }

        return $this;
    }

    public function setRequest(ServerRequestInterface $request): FlexFormInterface
    {
        $method = $request->getMethod();
        if (!\in_array($method, ['PUT', 'POST', 'PATCH'])) {
            throw new \RuntimeException(sprintf('FlexForm: Bad HTTP method %s', $method));
        }

        $flash = $this->getFlash();

        $includeOriginal = (bool)($this->getBlueprint()->form()['images']['original'] ?? null);
        $files = $flash->getFilesByFields($includeOriginal);
        $body = $request->getParsedBody();

        $this->files = $files ?? [];
        $this->data = new Data($this->decodeData($body['data'] ?? []), $this->getBlueprint());

        return $this;
    }

    public function updateObject(): FlexObjectInterface
    {
        return $this->getObject()->update($this->data->toArray(), $this->files);
    }

    /**
     * @return bool
     */
    public function isValid(): bool
    {
        return !$this->errors;
    }

    /**
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * @return bool
     */
    public function isSubmitted(): bool
    {
        return $this->submitted;
    }

    /**
     * @return bool
     */
    public function validate(): bool
    {
        if ($this->errors) {
            return false;
        }

        try {
            $this->data->validate();
            $this->data->filter();
            $this->checkUploads($this->files);
        } catch (ValidationException $e) {
            $list = [];
            foreach ($e->getMessages() as $field => $errors) {
                $list[] = $errors;
            }
            $list = array_merge(...$list);
            $this->errors = $list;
        }  catch (\Exception $e) {
            $this->errors[] = $e->getMessage();
        }

        return empty($this->errors);
    }

    /**
     * @param array $data
     * @param UploadedFileInterface[] $files
     * @return $this
     */
    public function submit(array $data, array $files = null): FlexFormInterface
    {
        try {
            if ($this->isSubmitted()) {
                throw new \RuntimeException('Form has already been submitted');
            }

            $this->files = $files ?? [];
            $this->data = new Data($this->decodeData($data['data'] ?? []), $this->getBlueprint());

            if (!$this->validate()) {
                return $this;
            }

            $this->doSubmit($this->data->toArray(), $this->files);

            $this->submitted = true;
        } catch (\Exception $e) {
            $this->errors[] = $e->getMessage();
        }

        return $this;
    }

    public function reset(): void
    {
        $this->data = null;
        $this->files = [];
        $this->errors = [];
        $this->submitted = false;

        // Also make sure that the flash object gets deleted.
        $flash = $this->getFlash();
        $flash->delete();
        $this->flash = null;
    }

    /**
     * Note: Used in form fields.
     *
     * @return array
     */
    public function getFields(): array
    {
        return $this->getBlueprint()->fields();
    }

    /**
     * Return form buttons
     *
     * @return array
     */
    public function getButtons(): array
    {
        return $this->getBlueprint()['form']['buttons'] ?? [];
    }

    /**
     * Return form buttons
     *
     * @return array
     */
    public function getTasks(): array
    {
        return $this->getBlueprint()['form']['tasks'] ?? [];
    }

    /**
     * @return Blueprint
     */
    public function getBlueprint(): Blueprint
    {
        if (null === $this->blueprint) {
            try {
                $blueprint = $this->getObject()->getBlueprint($this->name);
                if ($this->form) {
                    // We have field overrides available.
                    $blueprint->extend(['form' => $this->form], true);
                    $blueprint->init();
                }
            } catch (\RuntimeException $e) {
                if (!isset($this->form['fields'])) {
                    throw $e;
                }

                // Blueprint is not defined, but we have custom form fields available.
                $blueprint = new Blueprint(null, ['form' => $this->form]);
                $blueprint->load();
                $blueprint->setScope('object');
                $blueprint->init();
            }

            $this->blueprint = $blueprint;
        }

        return $this->blueprint;
    }

    /**
     * Implements \Serializable::serialize().
     *
     * @return string
     */
    public function serialize(): string
    {
        $data = [
            'name' => $this->name,
            'data' => $this->data,
            'files' => $this->files,
            'errors' => $this->errors,
            'submitted' => $this->submitted,
            'object' => $this->object,
        ];

        return serialize($data);
    }

    /**
     * Implements \Serializable::unserialize().
     *
     * @param string $data
     */
    public function unserialize($data): void
    {
        $data = unserialize($data, ['allowed_classes' => [FlexObject::class]]);

        $this->name = $data['name'];
        $this->data = $data['data'];
        $this->files = $data['files'];
        $this->errors = $data['errors'];
        $this->submitted = $data['submitted'];
        $this->object = $data['object'];
    }

    /**
     * @return Route|null
     */
    public function getFileUploadAjaxRoute(): ?Route
    {
        $object = $this->getObject();
        if (!method_exists($object, 'route')) {
            return null;
        }

        return $object->route('/edit.json/task:media.upload');
    }

    /**
     * @param $field
     * @param $filename
     * @return Route|null
     */
    public function getFileDeleteAjaxRoute($field, $filename): ?Route
    {
        $object = $this->getObject();
        if (!method_exists($object, 'route')) {
            return null;
        }

        return $object->route('/edit.json/task:media.delete');
    }

    public function getMediaTaskRoute(): string
    {
        $grav = Grav::instance();
        /** @var Flex $flex */
        $flex = $grav['flex_objects'];

        if (method_exists($flex, 'adminRoute')) {
            return $flex->adminRoute($this->getObject()) . '.json';
        }

        return '';
    }

    public function getMediaRoute(): string
    {
        return '/' . $this->getObject()->getKey();
    }

    /**
     * Note: this method clones the object.
     *
     * @param FlexObjectInterface $object
     * @return $this
     */
    protected function setObject(FlexObjectInterface $object): FlexFormInterface
    {
        $this->object = clone $object;

        return $this;
    }

    /**
     * Get flash object
     *
     * @return FormFlash
     */
    protected function getFlash(): FormFlash
    {
        if (null === $this->flash) {
            $grav = Grav::instance();

            /** @var Session $session */
            $session = $grav['session'];

            $this->flash = new FormFlash($session->getId(), $this->getUniqueId(), $this->getName());
        }

        return $this->flash;
    }

    protected function setErrors(array $errors): void
    {
        $this->errors = array_merge($this->errors, $errors);
    }

    protected function setError(string $error): void
    {
        $this->errors[] = $error;
    }

    /**
     * @param array $data
     * @param array $files
     * @throws \Exception
     */
    protected function doSubmit(array $data, array $files)
    {
        /** @var FlexObject $object */
        $object = clone $this->getObject();
        $object->update($data, $files);
        $object->save();

        $this->setObject($object);
    }

    protected function checkUploads(array $files): void
    {
        foreach ($files as $file) {
            if (null === $file) {
                continue;
            }
            if ($file instanceof UploadedFileInterface) {
                $this->checkUpload($file);
            } else {
                $this->checkUploads($file);
            }
        }
    }

    protected function checkUpload(UploadedFileInterface $file): void
    {
        // Handle bad filenames.
        $filename = $file->getClientFilename();
        if (strtr($filename, "\t\n\r\0\x0b", '_____') !== $filename
            || rtrim($filename, '. ') !== $filename
            || preg_match('|\.php|', $filename)) {
            $grav = Grav::instance();
            throw new \RuntimeException(
                sprintf($grav['language']->translate('PLUGIN_FORM.FILEUPLOAD_UNABLE_TO_UPLOAD', null, true), $filename, 'Bad filename')
            );
        }
    }

    /**
     * Decode data
     *
     * @param array $data
     * @return array
     */
    protected function decodeData($data): array
    {
        if (!\is_array($data)) {
            return [];
        }

        // Decode JSON encoded fields and merge them to data.
        if (isset($data['_json'])) {
            $data = array_replace_recursive($data, $this->jsonDecode($data['_json']));
            unset($data['_json']);
        }

        return $data;
    }

    /**
     * Recursively JSON decode data.
     *
     * @param  array $data
     *
     * @return array
     */
    protected function jsonDecode(array $data): array
    {
        foreach ($data as $key => &$value) {
            if (\is_array($value)) {
                $value = $this->jsonDecode($value);
            } else {
                $value = json_decode($value, true);
                if ($value === null && json_last_error() !== JSON_ERROR_NONE) {
                    unset($data[$key]);
                    $this->errors[] = "Badly encoded JSON data (for {$key}) was sent to the form";
                }
            }
        }

        return $data;
    }
}
