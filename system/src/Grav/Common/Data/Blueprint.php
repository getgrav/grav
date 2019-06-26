<?php

/**
 * @package    Grav\Common\Data
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Data;

use Grav\Common\File\CompiledYamlFile;
use Grav\Common\Grav;
use Grav\Common\User\Interfaces\UserInterface;
use RocketTheme\Toolbox\Blueprints\BlueprintForm;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

class Blueprint extends BlueprintForm
{
    /** @var string */
    protected $context = 'blueprints://';

    protected $scope;

    /** @var BlueprintSchema */
    protected $blueprintSchema;

    /** @var array */
    protected $defaults;

    protected $handlers = [];

    public function __clone()
    {
        if ($this->blueprintSchema) {
            $this->blueprintSchema = clone $this->blueprintSchema;
        }
    }

    public function setScope($scope)
    {
        $this->scope = $scope;
    }

    /**
     * Set default values for field types.
     *
     * @param array $types
     * @return $this
     */
    public function setTypes(array $types)
    {
        $this->initInternals();

        $this->blueprintSchema->setTypes($types);

        return $this;
    }

    /**
     * Get nested structure containing default values defined in the blueprints.
     *
     * Fields without default value are ignored in the list.
     *
     * @return array
     */
    public function getDefaults()
    {
        $this->initInternals();

        if (null === $this->defaults) {
            $this->defaults = $this->blueprintSchema->getDefaults();
        }

        return $this->defaults;
    }

    /**
     * Initialize blueprints with its dynamic fields.
     *
     * @return $this
     */
    public function init()
    {
        foreach ($this->dynamic as $key => $data) {
            // Locate field.
            $path = explode('/', $key);
            $current = &$this->items;

            foreach ($path as $field) {
                if (\is_object($current)) {
                    // Handle objects.
                    if (!isset($current->{$field})) {
                        $current->{$field} = [];
                    }

                    $current = &$current->{$field};
                } else {
                    // Handle arrays and scalars.
                    if (!\is_array($current)) {
                        $current = [$field => []];
                    } elseif (!isset($current[$field])) {
                        $current[$field] = [];
                    }

                    $current = &$current[$field];
                }
            }

            // Set dynamic property.
            foreach ($data as $property => $call) {
                $action = $call['action'];
                $method = 'dynamic' . ucfirst($action);

                if (isset($this->handlers[$action])) {
                    $callable = $this->handlers[$action];
                    $callable($current, $property, $call);
                } elseif (method_exists($this, $method)) {
                    $this->{$method}($current, $property, $call);
                }
            }
        }

        return $this;
    }

    /**
     * Merge two arrays by using blueprints.
     *
     * @param  array $data1
     * @param  array $data2
     * @param  string $name         Optional
     * @param  string $separator    Optional
     * @return array
     */
    public function mergeData(array $data1, array $data2, $name = null, $separator = '.')
    {
        $this->initInternals();

        return $this->blueprintSchema->mergeData($data1, $data2, $name, $separator);
    }

    /**
     * Process data coming from a form.
     *
     * @param array $data
     * @param array $toggles
     * @return array
     */
    public function processForm(array $data, array $toggles = [])
    {
        $this->initInternals();

        return $this->blueprintSchema->processForm($data, $toggles);
    }

    /**
     * Return data fields that do not exist in blueprints.
     *
     * @param  array  $data
     * @param  string $prefix
     * @return array
     */
    public function extra(array $data, $prefix = '')
    {
        $this->initInternals();

        return $this->blueprintSchema->extra($data, $prefix);
    }

    /**
     * Validate data against blueprints.
     *
     * @param  array $data
     * @throws \RuntimeException
     */
    public function validate(array $data)
    {
        $this->initInternals();

        $this->blueprintSchema->validate($data);
    }

    /**
     * Filter data by using blueprints.
     *
     * @param  array $data
     * @param  bool $missingValuesAsNull
     * @param  bool $keepEmptyValues
     * @return array
     */
    public function filter(array $data, bool $missingValuesAsNull = false, bool $keepEmptyValues = false)
    {
        $this->initInternals();

        return $this->blueprintSchema->filter($data, $missingValuesAsNull, $keepEmptyValues);
    }


    /**
     * Flatten data by using blueprints.
     *
     * @param  array $data
     * @return array
     */
    public function flattenData(array $data)
    {
        $this->initInternals();

        return $this->blueprintSchema->flattenData($data);
    }


    /**
     * Return blueprint data schema.
     *
     * @return BlueprintSchema
     */
    public function schema()
    {
        $this->initInternals();

        return $this->blueprintSchema;
    }

    public function addDynamicHandler(string $name, callable $callable): void
    {
        $this->handlers[$name] = $callable;
    }

    /**
     * Initialize validator.
     */
    protected function initInternals()
    {
        if (null === $this->blueprintSchema) {
            $types = Grav::instance()['plugins']->formFieldTypes;

            $this->blueprintSchema = new BlueprintSchema;

            if ($types) {
                $this->blueprintSchema->setTypes($types);
            }

            $this->blueprintSchema->embed('', $this->items);
            $this->blueprintSchema->init();
            $this->defaults = null;
        }
    }

    /**
     * @param string $filename
     * @return string
     */
    protected function loadFile($filename)
    {
        $file = CompiledYamlFile::instance($filename);
        $content = $file->content();
        $file->free();

        return $content;
    }

    /**
     * @param string|array $path
     * @param string $context
     * @return array
     */
    protected function getFiles($path, $context = null)
    {
        /** @var UniformResourceLocator $locator */
        $locator = Grav::instance()['locator'];

        if (\is_string($path) && !$locator->isStream($path)) {
            // Find path overrides.
            $paths = (array) ($this->overrides[$path] ?? null);

            // Add path pointing to default context.
            if ($context === null) {
                $context = $this->context;
            }

            if ($context && $context[\strlen($context)-1] !== '/') {
                $context .= '/';
            }

            $path = $context . $path;

            if (!preg_match('/\.yaml$/', $path)) {
                $path .= '.yaml';
            }

            $paths[] = $path;
        } else {
            $paths = (array) $path;
        }

        $files = [];
        foreach ($paths as $lookup) {
            if (\is_string($lookup) && strpos($lookup, '://')) {
                $files = array_merge($files, $locator->findResources($lookup));
            } else {
                $files[] = $lookup;
            }
        }

        return array_values(array_unique($files));
    }

    /**
     * @param array $field
     * @param string $property
     * @param array $call
     */
    protected function dynamicData(array &$field, $property, array &$call)
    {
        $params = $call['params'];

        if (\is_array($params)) {
            $function = array_shift($params);
        } else {
            $function = $params;
            $params = [];
        }

        [$o, $f] = explode('::', $function, 2);

        $data = null;
        if (!$f) {
            if (\function_exists($o)) {
                $data = \call_user_func_array($o, $params);
            }
        } else {
            if (method_exists($o, $f)) {
                $data = \call_user_func_array([$o, $f], $params);
            }
        }

        // If function returns a value,
        if (null !== $data) {
            if (\is_array($data) && isset($field[$property]) && \is_array($field[$property])) {
                // Combine field and @data-field together.
                $field[$property] += $data;
            } else {
                // Or create/replace field with @data-field.
                $field[$property] = $data;
            }
        }
    }

    /**
     * @param array $field
     * @param string $property
     * @param array $call
     */
    protected function dynamicConfig(array &$field, $property, array &$call)
    {
        $value = $call['params'];
        $default = $field[$property] ?? null;
        $config = Grav::instance()['config']->get($value, $default);

        if (null !== $config) {
            $field[$property] = $config;
        }
    }

    /**
     * @param array $field
     * @param string $property
     * @param array $call
     */
    protected function dynamicSecurity(array &$field, $property, array &$call)
    {
        if ($property || !empty($field['validate']['ignore'])) {
            return;
        }

        $grav = Grav::instance();
        $actions = (array)$call['params'];

        /** @var UserInterface|null $user */
        $user = $grav['user'] ?? null;
        foreach ($actions as $action) {
            if (!$user || !$user->authorize($action)) {
                $this->addPropertyRecursive($field, 'validate', ['ignore' => true]);
                return;
            }
        }
    }

    /**
     * @param array $field
     * @param string $property
     * @param array $call
     */
    protected function dynamicScope(array &$field, $property, array &$call)
    {
        if ($property && $property !== 'ignore') {
            return;
        }

        $scopes = (array)$call['params'];
        $matches = \in_array($this->scope, $scopes, true);
        if ($this->scope && $property !== 'ignore') {
            $matches = !$matches;
        }

        if ($matches) {
            $this->addPropertyRecursive($field, 'validate', ['ignore' => true]);
            return;
        }
    }

    protected function addPropertyRecursive(array &$field, $property, $value)
    {
        if (\is_array($value) && isset($field[$property]) && \is_array($field[$property])) {
            $field[$property] = array_merge_recursive($field[$property], $value);
        } else {
            $field[$property] = $value;
        }

        if (!empty($field['fields'])) {
            foreach ($field['fields'] as $key => &$child) {
                $this->addPropertyRecursive($child, $property, $value);
            }
        }
    }
}
