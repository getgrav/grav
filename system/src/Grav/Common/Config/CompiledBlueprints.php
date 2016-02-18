<?php
namespace Grav\Common\Config;

use Grav\Common\File\CompiledYamlFile;
use Grav\Common\Grav;
use RocketTheme\Toolbox\Blueprints\Blueprints;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

/**
 * The Compiled Blueprints class.
 */
class CompiledBlueprints extends CompiledBase
{
    /**
     * @var int Version number for the compiled file.
     */
    public $version = 2;

    /**
     * @var Blueprints  Blueprints object.
     */
    protected $object;

    /**
     * Returns checksum from the configuration files.
     *
     * You can set $this->checksum = false to disable this check.
     *
     * @return bool|string
     */
    public function checksum()
    {
        if (!isset($this->checksum)) {
            $this->checksum = md5(json_encode($this->files) . json_encode($this->getTypes()) . $this->version);
        }

        return $this->checksum;
    }

    /**
     * Create configuration object.
     *
     * @param array  $data
     */
    protected function createObject(array $data = [])
    {
        $this->object = (new Blueprints($data))->setTypes($this->getTypes());
    }

    /**
     * Get list of form field types.
     *
     * @return array
     */
    protected function getTypes()
    {
        return Grav::instance()['plugins']->formFieldTypes;
    }

    /**
     * Finalize configuration object.
     */
    protected function finalizeObject()
    {
        $this->object->init('static');
    }

    /**
     * Load single configuration file and append it to the correct position.
     *
     * @param  string  $name  Name of the position.
     * @param  array   $files  Files to be loaded.
     */
    protected function loadFile($name, $files)
    {
        $data = $this->loadBlueprints($files);

        // Merge all extends into a single blueprint.
        foreach ($data as $content) {
            $this->object->embed($name, $content, '/', true);
        }
    }

    /**
     * Internal function that handles loading extended blueprints.
     *
     * @param array $files
     * @return array
     */
    protected function loadBlueprints(array $files)
    {
        $filename = array_shift($files);
        $file = CompiledYamlFile::instance($filename);
        $content = $file->content();
        $file->free();

        $extends = isset($content['@extends']) ? (array) $content['@extends']
            : (isset($content['extends@']) ? (array) $content['extends@'] : null);

        $data = isset($extends) ? $this->extendBlueprint($files, $extends) : [];
        $data[] = $content;

        return $data;
    }

    /**
     * Internal function to recursively load extended blueprints.
     *
     * @param array $parents
     * @param array $extends
     * @return array
     */
    protected function extendBlueprint(array $parents, array $extends)
    {
        if (is_string(key($extends))) {
            $extends = [$extends];
        }

        $data = [];
        foreach ($extends as $extendConfig) {
            // Accept array of type and context or a string.
            $extendType = !is_string($extendConfig)
                ? !isset($extendConfig['type']) ? null : $extendConfig['type'] : $extendConfig;

            if (!$extendType) {
                continue;
            }

            if ($extendType === '@parent' || $extendType === 'parent@') {
                $files = $parents;

            } else {
                if (strpos($extendType, '://')) {
                    $path = $extendType;
                } elseif (empty($extendConfig['context'])) {
                    $path = "blueprints://{$extendType}";
                } else {
                    $separator = $extendConfig['context'][strlen($extendConfig['context'])-1] === '/' ? '' : '/';
                    $path = $extendConfig['context'] . $separator . $extendType;
                }
                if (!preg_match('/\.yaml$/', $path)) {
                    $path .= '.yaml';
                }

                /** @var UniformResourceLocator $locator */
                $locator = Grav::instance()['locator'];

                $files = $locator->findResources($path);
            }

            if ($files) {
                $data = array_merge($data, $this->loadBlueprints($files));
            }
        }

        return $data;
    }

    /**
     * Load and join all configuration files.
     *
     * @return bool
     * @internal
     */
    protected function loadFiles()
    {
        $this->createObject();

        // Convert file list into parent list.
        $list = [];
        foreach ($this->files as $files) {
            foreach ($files as $name => $item) {
                $list[$name][] = $this->path . $item['file'];
            }
        }

        // Load files.
        foreach ($list as $name => $files) {
            $this->loadFile($name, $files);
        }

        $this->finalizeObject();

        return true;
    }
}
