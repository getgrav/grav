<?php
/**
 * @package    Grav.Common.Data
 *
 * @copyright  Copyright (C) 2014 - 2016 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Data;

use Grav\Common\File\CompiledYamlFile;
use Grav\Common\Grav;
use RocketTheme\Toolbox\Blueprints\BlueprintForm;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

class Blueprint extends BlueprintForm
{
    /**
     * @var string
     */
    protected $context = 'blueprints://';

    /**
     * @var BlueprintSchema
     */
    protected $blueprintSchema;

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

        return $this->blueprintSchema->getDefaults();
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
     * @return array
     */
    public function filter(array $data)
    {
        $this->initInternals();

        return $this->blueprintSchema->filter($data);
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

    /**
     * Initialize validator.
     */
    protected function initInternals()
    {
        if (!isset($this->blueprintSchema)) {
            $types = Grav::instance()['plugins']->formFieldTypes;

            $this->blueprintSchema = new BlueprintSchema;
            if ($types) {
                $this->blueprintSchema->setTypes($types);
            }
            $this->blueprintSchema->embed('', $this->items);
            $this->blueprintSchema->init();
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

        if (is_string($path) && !$locator->isStream($path)) {
            // Find path overrides.
            $paths = isset($this->overrides[$path]) ? (array) $this->overrides[$path] : [];

            // Add path pointing to default context.
            if ($context === null) {
                $context = $this->context;
            }
            if ($context && $context[strlen($context)-1] !== '/') {
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
            if (is_string($lookup) && strpos($lookup, '://')) {
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

        if (is_array($params)) {
            $function = array_shift($params);
        } else {
            $function = $params;
            $params = [];
        }

        list($o, $f) = preg_split('/::/', $function, 2);
        if (!$f) {
            if (function_exists($o)) {
                $data = call_user_func_array($o, $params);
            }
        } else {
            if (method_exists($o, $f)) {
                $data = call_user_func_array(array($o, $f), $params);
            }
        }

        // If function returns a value,
        if (isset($data)) {
            if (isset($field[$property]) && is_array($field[$property]) && is_array($data)) {
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

        $default = isset($field[$property]) ? $field[$property] : null;
        $config = Grav::instance()['config']->get($value, $default);

        if (!is_null($config)) {
            $field[$property] = $config;
        }
    }
}
