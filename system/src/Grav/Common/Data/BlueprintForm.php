<?php
namespace Grav\Common\Data;

use RocketTheme\Toolbox\ArrayTraits\Export;
use RocketTheme\Toolbox\ArrayTraits\ExportInterface;
use RocketTheme\Toolbox\ArrayTraits\NestedArrayAccessWithGetters;

/**
 * The Config class contains configuration information.
 *
 * @author RocketTheme
 */
abstract class BlueprintForm implements \ArrayAccess, ExportInterface
{
    use NestedArrayAccessWithGetters, Export;

    /**
     * @var array
     */
    protected $items;

    /**
     * @var string
     */
    protected $filename;

    /**
     * @var string
     */
    protected $context;

    /**
     * @var array
     */
    protected $overrides = [];

    /**
     * @var array
     */
    protected $dynamic = [];

    /**
     * Load file and return its contents.
     *
     * @param string $filename
     * @return string
     */
    abstract protected function loadFile($filename);

    /**
     * Get list of blueprint form files (file and its parents for overrides).
     *
     * @param string|array $path
     * @param string $context
     * @return array
     */
    abstract protected function getFiles($path, $context = null);

    /**
     * Constructor.
     *
     * @param string|array $filename
     * @param array $items
     */
    public function __construct($filename, array $items = [])
    {
        $this->filename = $filename;
        $this->items = $items;
    }

    /**
     * Set context for import@ and extend@.
     *
     * @param $context
     * @return $this
     */
    public function setContext($context)
    {
        $this->context = $context;

        return $this;
    }

    /**
     * Set custom overrides for import@ and extend@.
     *
     * @param array $overrides
     * @return $this
     */
    public function setOverrides($overrides)
    {
        $this->overrides = $overrides;

        return $this;
    }

    /**
     * Load blueprint.
     *
     * @return $this
     */
    public function load()
    {
        // Only load and extend blueprint if it has not yet been loaded.
        if (empty($this->items)) {
            // Get list of files.
            $files = $this->getFiles($this->filename);

            // Load and extend blueprints.
            $data = $this->doLoad($files);

            $this->items = array_shift($data);

            foreach ($data as $content) {
                $this->extend($content, true);
            }
        }

        // Import blueprints.
        $this->deepInit($this->items);

        return $this;
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
                if (is_object($current)) {
                    // Handle objects.
                    if (!isset($current->{$field})) {
                        $current->{$field} = array();
                    }
                    $current = &$current->{$field};
                } else {
                    // Handle arrays and scalars.
                    if (!is_array($current)) {
                        $current = array($field => array());
                    } elseif (!isset($current[$field])) {
                        $current[$field] = array();
                    }
                    $current = &$current[$field];
                }
            }

            // Set dynamic property.
            foreach ($data as $property => $call) {
                $action = 'dynamic' . ucfirst($call['action']);

                if (method_exists($this, $action)) {
                    $this->{$action}($current, $property, $call);
                }
            }
        }

        return $this;
    }


    /**
     * Get form.
     *
     * @return array
     */
    public function form()
    {
        return (array) $this->get('form');
    }

    /**
     * Get form fields.
     *
     * @return array
     */
    public function fields()
    {
        return (array) $this->get('form.fields');
    }

    /**
     * Extend blueprint with another blueprint.
     *
     * @param BlueprintForm|array $extends
     * @param bool $append
     * @return $this
     */
    public function extend($extends, $append = false)
    {
        if ($extends instanceof BlueprintForm) {
            $extends = $extends->toArray();
        }

        if ($append) {
            $a = $this->items;
            $b = $extends;
        } else {
            $a = $extends;
            $b = $this->items;
        }

        $this->items = $this->deepMerge($a, $b);

        return $this;
    }

    /**
     * @param string $name
     * @param mixed $value
     * @param string $separator
     * @param bool $append
     * @return $this
     */
    public function embed($name, $value, $separator = '/', $append = false)
    {
        $oldValue = $this->get($name, null, $separator);

        if (is_array($oldValue) && is_array($value)) {
            if ($append) {
                $a = $oldValue;
                $b = $value;
            } else {
                $a = $value;
                $b = $oldValue;
            }

            $value = $this->deepMerge($a, $b);
        }

        $this->set($name, $value, $separator);

        return $this;
    }

    /**
     * Get blueprints by using dot notation for nested arrays/objects.
     *
     * @example $value = $this->resolve('this.is.my.nested.variable');
     * returns ['this.is.my', 'nested.variable']
     *
     * @param array  $path
     * @param string  $separator
     * @return array
     */
    public function resolve(array $path, $separator = '/')
    {
        $fields = false;
        $parts = [];
        $current = $this['form.fields'];
        $result = [null, null, null];

        while (($field = current($path)) !== null) {
            if (!$fields && isset($current['fields'])) {
                if (!empty($current['array'])) {
                    $result = [$current, $parts, $path ? implode($separator, $path) : null];
                    // Skip item offset.
                    $parts[] = array_shift($path);
                }

                $current = $current['fields'];
                $fields = true;

            } elseif (isset($current[$field])) {
                $parts[] = array_shift($path);
                $current = $current[$field];
                $fields = false;

            } elseif (isset($current['.' . $field])) {
                $parts[] = array_shift($path);
                $current = $current['.' . $field];
                $fields = false;

            } else {
                break;
            }
        }

        return $result;
    }

    /**
     * Deep merge two arrays together.
     *
     * @param array $a
     * @param array $b
     * @return array
     */
    protected function deepMerge(array $a, array $b)
    {
        $bref_stack = array(&$a);
        $head_stack = array($b);

        do {
            end($bref_stack);
            $bref = &$bref_stack[key($bref_stack)];
            $head = array_pop($head_stack);
            unset($bref_stack[key($bref_stack)]);

            foreach (array_keys($head) as $key) {
                if (isset($key, $bref[$key]) && is_array($bref[$key]) && is_array($head[$key])) {
                    $bref_stack[] = &$bref[$key];
                    $head_stack[] = $head[$key];
                } else {
                    $bref = array_merge($bref, [$key => $head[$key]]);
                }
            }
        } while (count($head_stack));

        return $a;
    }

    /**
     * @param array $items
     * @param array $path
     * @return string
     */
    protected function deepInit(array &$items, $path = [])
    {
        $ordering = '';
        $order = [];
        $field = end($path) === 'fields';

        foreach ($items as $key => &$item) {
            // Set name for nested field.
            if ($field && isset($item['type'])) {
                $item['name'] = $key;
            }

            // Handle special instructions in the form.
            if (!empty($key) && ($key[0] === '@' || $key[strlen($key) - 1] === '@')) {
                $name = trim($key, '@');

                switch ($name) {
                    case 'import':
                        $this->doImport($item, $path);
                        unset($items[$key]);
                        break;
                    case 'ordering':
                        $ordering = $item;
                        unset($items[$key]);
                        break;
                    default:
                        $list = explode('-', trim($name, '@'), 2);
                        $action = array_shift($list);
                        $property = array_shift($list);

                        $this->dynamic[implode('/', $path)][$property] = ['action' => $action, 'params' => $item];
                }

            } elseif (is_array($item)) {
                // Recursively initialize form.
                $newPath = array_merge($path, [$key]);

                $location = $this->deepInit($item, $newPath);
                if ($location) {
                    $order[$key] = $location;
                }
            }
        }

        if ($order) {
            // Reorder fields if needed.
            $items = $this->doReorder($items, $order);
        }

        return $ordering;
    }

    /**
     * @param array $value
     * @param array $path
     */
    protected function doImport(array &$value, array &$path)
    {
        $type = !is_string($value) ? !isset($value['type']) ? null : $value['type'] : $value;

        $files = $this->getFiles($type, isset($value['context']) ? $value['context'] : null);

        if (!$files) {
            return;
        }

        /** @var BlueprintForm $blueprint */
        $blueprint = new static($files);
        $blueprint->setContext($this->context)->setOverrides($this->overrides)->load();

        $name = implode('/', $path);

        $this->embed($name, $blueprint->form(), '/', false);
    }

    /**
     * Internal function that handles loading extended blueprints.
     *
     * @param array $files
     * @return array
     */
    protected function doLoad(array $files)
    {
        $filename = array_shift($files);
        $content = $this->loadFile($filename);

        $extends = isset($content['@extends']) ? (array) $content['@extends']
            : (isset($content['extends@']) ? (array) $content['extends@'] : null);

        $data = isset($extends) ? $this->doExtend($files, $extends) : [];
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
    protected function doExtend(array $parents, array $extends)
    {
        if (is_string(key($extends))) {
            $extends = [$extends];
        }

        $data = [];
        foreach ($extends as $value) {
            // Accept array of type and context or a string.
            $type = !is_string($value)
                ? !isset($value['type']) ? null : $value['type'] : $value;

            if (!$type) {
                continue;
            }

            if ($type === '@parent' || $type === 'parent@') {
                $files = $parents;
            } else {
                $files = $this->getFiles($type, isset($value['context']) ? $value['context'] : null);
            }

            if ($files) {
                $data = array_merge($data, $this->doLoad($files));
            }
        }

        return $data;
    }

    /**
     * Internal function to reorder items.
     *
     * @param array $items
     * @param array $keys
     * @return array
     */
    protected function doReorder(array $items, array $keys)
    {
        $reordered = array_keys($items);

        foreach ($keys as $item => $ordering) {
            if ((string)(int) $ordering === (string) $ordering) {
                $location = array_search($item, $reordered);
                $rel = array_splice($reordered, $location, 1);
                array_splice($reordered, $ordering, 0, $rel);

            } elseif (isset($items[$ordering])) {
                $location = array_search($item, $reordered);
                $rel = array_splice($reordered, $location, 1);
                $location = array_search($ordering, $reordered);
                array_splice($reordered, $location + 1, 0, $rel);
            }
        }

        return array_merge(array_flip($reordered), $items);
    }
}
