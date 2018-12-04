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
    /** @var FormFlash */
    private $flash;

    /**
     * FlexForm constructor.
     * @param string $name
     * @param FlexObjectInterface $object
     */
    public function __construct(string $name, FlexObjectInterface $object)
    {
        $this->name = $name;
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

            $data = $request->getParsedBody();
            $files = $request->getUploadedFiles();

            $this->submit($data, $files);
        } catch (\Exception $e) {
            $this->errors[] = $e->getMessage();
        }

        return $this;
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
            if ($this->getErrors()) {
                return $this;
            }

            $this->doSubmit($this->data->toArray(), $this->files);

            $this->submitted = true;
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

        return $this;
    }

    /**
     * @return $this
     */
    public function reset(): FlexFormInterface
    {
        $this->data = null;
        $this->files = [];
        $this->errors = [];
        $this->submitted = false;

        return $this;
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
     * @return Blueprint
     */
    public function getBlueprint(): Blueprint
    {
        return $this->getObject()->getBlueprint($this->name);
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
    protected function getFlash()
    {
        if (null === $this->flash) {
            $this->flash = new FormFlash($this->getName(), $this->getUniqueId());
        }

        return $this->flash;
    }

    /**
     * @throws \Exception
     */
    protected function validate(): void
    {
        $this->data->validate();
        $this->data->filter();
        $this->checkUploads($this->files);
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
        $this->validate();

        $object = clone $this->getObject();
        $object->update($data);

        if (method_exists($object, 'triggerEvent')) {
            $object->triggerEvent('onSave');
        }

        if (method_exists($object, 'upload')) {
            $object->upload($files);
        }

        $object->save();

        $this->setObject($object);
    }

    protected function checkUploads(array $files): void
    {
        foreach ($files as $file) {
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
                    // FIXME: add back
                    //$this->errors[] = "Badly encoded JSON data (for {$key}) was sent to the form";
                }
            }
        }

        return $data;
    }
}
