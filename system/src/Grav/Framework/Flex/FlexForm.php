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
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;

/**
 * Class FlexForm
 * @package Grav\Framework\Flex
 */
class FlexForm implements \Serializable
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
    /** @var Data */
    private $data;
    /** @var UploadedFileInterface[] */
    private $files;
    /** @var FlexObject */
    private $object;

    /**
     * FlexForm constructor.
     * @param string $name
     * @param FlexObject|null $object
     */
    public function __construct(string $name = '', FlexObject $object = null)
    {
        $this->reset();

        if ($object) {
            $this->setObject($object);
        }

        $this->name = $name;
        $this->id = $this->getName();
    }

    public function getId() : string
    {
        return $this->id;
    }

    public function setId(string $id)
    {
        $this->id = $id;
    }

    /**
     * @return string
     */
    public function getName() : string
    {
        $object = $this->object;
        $name = $this->name ?: 'object';

        return "flex-{$object->getType(false)}-{$name}";
    }

    public function getUniqueId() : string
    {
        if (null === $this->uniqueid) {
            $this->uniqueid = Utils::generateRandomString(20);
        }

        return $this->uniqueid;
    }

    /**
     * @return string
     */
    public function getAction() : string
    {
        // TODO:
        return '';
    }

    /**
     * @return array
     */
    public function getButtons() : array
    {
        return [
            [
                'type' => 'submit',
                'value' => 'Save'
            ]
        ];
    }

    /**
     * @return Data|FlexObject
     */
    public function getData()
    {
        if (null === $this->data) {
            $this->data = $this->getObject();
        }

        return $this->data;
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
        $data = $this->getData();
        return $data instanceof FlexObject ? $data->getNestedProperty($name) : $data->get($name);
    }

    /**
     * @return UploadedFileInterface[]
     */
    public function getFiles() : array
    {
        return $this->files;
    }

    /**
     * Note: this method clones the object.
     *
     * @param FlexObject $object
     * @return $this
     */
    public function setObject(FlexObject $object) : self
    {
        $this->object = clone $object;

        return $this;
    }

    /**
     * @return FlexObject
     */
    public function getObject() : FlexObject
    {
        if (!$this->object) {
            throw new \RuntimeException('FlexForm: Object is not defined');
        }

        return $this->object;
    }

    /**
     * @param ServerRequestInterface $request
     * @return $this
     */
    public function handleRequest(ServerRequestInterface $request) : self
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
    public function isValid() : bool
    {
        return !$this->errors;
    }

    /**
     * @return array
     */
    public function getErrors() : array
    {
        return $this->errors;
    }

    /**
     * @return bool
     */
    public function isSubmitted() : bool
    {
        return $this->submitted;
    }

    /**
     * @param array $data
     * @param UploadedFileInterface[] $files
     * @return $this
     */
    public function submit(array $data, array $files = null) : self
    {
        try {
            if ($this->isSubmitted()) {
                throw new \RuntimeException('Form has already been submitted');
            }

            $this->data = new Data($data);
            $this->files = $files ?? [];
            $this->submitted = true;

            $this->checkUploads($files);

            $object = clone $this->object;
            $object->update($this->data->toArray());
            /*
            if (method_exists($object, 'upload')) {
                $object->upload($this->files);
            }
            $object->save();
            */

            $this->object = $object;
            $this->valid = true;
        } catch (ValidationException $e) {
            $this->errors = $e->getMessages();
        }  catch (\Exception $e) {
            $this->errors[] = $e->getMessage();
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function reset() : self
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
    public function getFields() : array
    {
        return $this->getBlueprint()->fields();
    }

    /**
     * @return Blueprint
     */
    public function getBlueprint() : Blueprint
    {
        return $this->getObject()->getBlueprint($this->name);
    }

    /**
     * Implements \Serializable::serialize().
     *
     * @return string
     */
    public function serialize() : string
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
    public function unserialize($data) : void
    {
        $data = unserialize($data, ['allowed_classes' => [FlexObject::class]]);

        $this->name = $data['name'];
        $this->data = $data['data'];
        $this->files = $data['files'];
        $this->errors = $data['errors'];
        $this->submitted = $data['submitted'];
        $this->object = $data['object'];
    }


    public function getMediaTaskRoute() : string
    {
        $grav = Grav::instance();
        /** @var Flex $flex */
        $flex = $grav['flex_objects'];

        if (method_exists($flex, 'adminRoute')) {
            return $flex->adminRoute($this->object) . '.json';
        }

        return '';
    }

    public function getMediaRoute() : string
    {
        return '/' . $this->object->getKey();
    }

    protected function checkUploads(array $files) : void
    {
        foreach ($files as $file) {
            if ($file instanceof UploadedFileInterface) {
                $this->checkUpload($file);
            } else {
                $this->checkUploads($file);
            }
        }
    }

    protected function checkUpload(UploadedFileInterface $file) : void
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
}
