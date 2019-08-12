<?php

/**
 * @package    Grav\Framework\Flex
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Flex;

use Grav\Common\Data\Blueprint;
use Grav\Common\Data\Data;
use Grav\Common\Grav;
use Grav\Common\Twig\Twig;
use Grav\Common\Utils;
use Grav\Framework\Flex\Interfaces\FlexFormInterface;
use Grav\Framework\Flex\Interfaces\FlexObjectInterface;
use Grav\Framework\Form\Traits\FormTrait;
use Grav\Framework\Route\Route;
use Twig\Error\LoaderError;
use Twig\Error\SyntaxError;
use Twig\Template;
use Twig\TemplateWrapper;

/**
 * Class FlexForm
 * @package Grav\Framework\Flex
 */
class FlexForm implements FlexFormInterface
{
    use FormTrait {
        FormTrait::doSerialize as doTraitSerialize;
        FormTrait::doUnserialize as doTraitUnserialize;
    }

    /** @var array|null */
    private $form;

    /** @var FlexObjectInterface */
    private $object;

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

        $uniqueId = $object->exists() ? $object->getStorageKey() : "{$object->getFlexType()}:new";
        $this->setObject($object);
        $this->setId($this->getName());
        $this->setUniqueId(md5($uniqueId));
        $this->messages = [];
        $this->submitted = false;

        $flash = $this->getFlash();
        if ($flash->exists()) {
            $data = $flash->getData();
            $includeOriginal = (bool)($this->getBlueprint()->form()['images']['original'] ?? null);

            $this->data = $data ? new Data($data, $this->getBlueprint()) : null;
            $this->files = $flash->getFilesByFields($includeOriginal);
        } else {
            $this->data = null;
            $this->files = [];
        }
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        $object = $this->getObject();
        $name = $this->name ?: 'object';

        return "flex-{$object->getFlexType()}-{$name}";
    }

    /**
     * @return Data|FlexObjectInterface|object
     */
    public function getData()
    {
        return $this->data ?? $this->getObject();
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
        // Attempt to get value from the form data.
        $value = $this->data ? $this->data[$name] : null;

        // Return the form data or fall back to the object property.
        return $value ?? $this->getObject()->getFormValue($name);
    }

    public function getDefaultValue(string $name)
    {
        return $this->object->getDefaultValue($name);
    }

    /**
     * @return array
     */
    public function getDefaultValues(): array
    {
        return $this->object->getDefaultValues();
    }
    /**
     * @return string
     */
    public function getFlexType(): string
    {
        return $this->object->getFlexType();
    }

    /**
     * @return FlexObjectInterface
     */
    public function getObject(): FlexObjectInterface
    {
        return $this->object;
    }

    public function updateObject(): FlexObjectInterface
    {
        $data = $this->data instanceof Data ? $this->data->toArray() : [];
        $files = $this->files;

        return $this->getObject()->update($data, $files);
    }

    /**
     * @return Blueprint
     */
    public function getBlueprint(): Blueprint
    {
        if (null === $this->blueprint) {
            try {
                $blueprint = $this->getObject()->getBlueprint(Utils::isAdminPlugin() ? '' : $this->name);
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
     * @param string $field
     * @param string $filename
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

    public function getMediaTaskRoute(array $params = [], $extension = null): string
    {
        $grav = Grav::instance();
        /** @var Flex $flex */
        $flex = $grav['flex_objects'];

        if (method_exists($flex, 'adminRoute')) {
            return $flex->adminRoute($this->getObject(), $params, $extension ?? 'json');
        }

        return '';
    }

    /**
     * Implements \Serializable::unserialize().
     *
     * @param string $data
     */
    public function unserialize($data): void
    {
        $data = unserialize($data, ['allowed_classes' => [FlexObject::class]]);

        $this->doUnserialize($data);
    }

    public function __get($name)
    {
        $method = "get{$name}";
        if (method_exists($this, $method)) {
            return $this->{$method}();
        }

        $form = $this->getBlueprint()->form();

        return $form[$name] ?? null;
    }

    public function __set($name, $value)
    {
        $method = "set{$name}";
        if (method_exists($this, $method)) {
            $this->{$method}($value);
        }
    }

    public function __isset($name)
    {
        $method = "get{$name}";
        if (method_exists($this, $method)) {
            return true;
        }

        $form = $this->getBlueprint()->form();

        return isset($form[$name]);
    }

    public function __unset($name)
    {
    }

    /**
     * Note: this method clones the object.
     *
     * @param FlexObjectInterface $object
     * @return $this
     */
    protected function setObject(FlexObjectInterface $object): self
    {
        $this->object = clone $object;

        return $this;
    }

    /**
     * @param string $layout
     * @return Template|TemplateWrapper
     * @throws LoaderError
     * @throws SyntaxError
     */
    protected function getTemplate($layout)
    {
        $grav = Grav::instance();

        /** @var Twig $twig */
        $twig = $grav['twig'];

        return $twig->twig()->resolveTemplate(
            [
                "flex-objects/layouts/{$this->getFlexType()}/form/{$layout}.html.twig",
                "flex-objects/layouts/_default/form/{$layout}.html.twig",
                "forms/{$layout}/form.html.twig",
                'forms/default/form.html.twig'
            ]
        );
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
        $this->reset();
    }

    protected function doSerialize(): array
    {
        return $this->doTraitSerialize() + [
                'object' => $this->object,
            ];
    }

    protected function doUnserialize(array $data): void
    {
        $this->doTraitUnserialize($data);

        $this->object = $data['object'];
    }

        /**
     * Filter validated data.
     *
     * @param \ArrayAccess $data
     */
    protected function filterData(\ArrayAccess $data): void
    {
        if ($data instanceof Data) {
            $data->filter(true, true);
        }
    }
}
