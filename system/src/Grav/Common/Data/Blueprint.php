<?php
namespace Grav\Common\Data;

use Grav\Common\File\CompiledYamlFile;
use Grav\Common\Grav;
use RocketTheme\Toolbox\Blueprints\BlueprintForm;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

/**
 * The Config class contains configuration information.
 *
 * @author RocketTheme
 */
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
        if (is_string($path) && !strpos($path, '://')) {
            // Resolve filename.
            if (isset($this->overrides[$path])) {
                $path = $this->overrides[$path];
            } else {
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
            }
        }

        if (is_string($path) && strpos($path, '://')) {
            /** @var UniformResourceLocator $locator */
            $locator = Grav::instance()['locator'];

            $files = $locator->findResources($path);
        } else {
            $files = (array) $path;
        }

        return $files;
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
