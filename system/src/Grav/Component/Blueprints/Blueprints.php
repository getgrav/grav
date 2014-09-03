<?php
namespace Grav\Component\Blueprints;

/**
 * Blueprints define a data structure.
 *
 * @author RocketTheme
 * @license MIT
 */
class Blueprints
{
    protected $items = array();
    protected $rules = array();
    protected $nested = array();
    protected $filter = ['validation' => 1];

    public function __construct(array $serialized = null) {
        if ($serialized) {
            $this->items = $serialized['items'];
            $this->rules = $serialized['rules'];
            $this->nested = $serialized['nested'];
            $this->filter = $serialized['filter'];
        }
    }

    /**
     * Set filter for inherited properties.
     *
     * @param array $filter     List of field names to be inherited.
     */
    public function setFilter(array $filter)
    {
        $this->filter = array_flip($filter);
    }

    /**
     * Get value by using dot notation for nested arrays/objects.
     *
     * @example $value = $data->get('this.is.my.nested.variable');
     *
     * @param string  $name       Dot separated path to the requested value.
     * @param mixed   $default    Default value (or null).
     * @param string  $separator  Separator, defaults to '.'
     *
     * @return mixed  Value.
     */
    public function get($name, $default = null, $separator = '.')
    {
        $name = $separator != '.' ? strtr($name, $separator, '.') : $name;

        return isset($this->items[$name]) ? $this->items[$name] : $default;
    }

    /**
     * Set value by using dot notation for nested arrays/objects.
     *
     * @example $value = $data->set('this.is.my.nested.variable', $newField);
     *
     * @param string  $name       Dot separated path to the requested value.
     * @param mixed   $value      New value.
     * @param string  $separator  Separator, defaults to '.'
     */
    public function set($name, $value, $separator = '.')
    {
        $name = $separator != '.' ? strtr($name, $separator, '.') : $name;

        $this->items[$name] = $value;
        $this->addProperty($name);
    }

    /**
     * Define value by using dot notation for nested arrays/objects.
     *
     * @example $value = $data->set('this.is.my.nested.variable', true);
     *
     * @param string  $name       Dot separated path to the requested value.
     * @param mixed   $value      New value.
     * @param string  $separator  Separator, defaults to '.'
     */
    public function def($name, $value, $separator = '.')
    {
        $this->set($name, $this->get($name, $value, $separator), $separator);
    }

    /**
     * Convert object into an array.
     *
     * @return array
     */
    public function toArray()
    {
        return ['items' => $this->items, 'rules' => $this->rules, 'nested' => $this->nested, 'filter' => $this->filter];
    }

    /**
     * Embed an array to the blueprint.
     *
     * @param $name
     * @param array $value
     * @param string $separator
     */
    public function embed($name, array $value, $separator = '.')
    {
        if (isset($value['rules'])) {
            $this->rules = array_merge($this->rules, $value['rules']);
        }
        if (!isset($value['form']['fields']) || !is_array($value['form']['fields'])) {
            return;
        }
        $prefix = $name ? ($separator != '.' ? strtr($name, $separator, '.') : $name) . '.' : '';
        $params = array_intersect_key($this->filter, $value);
        $this->parseFormFields($value['form']['fields'], $params, $prefix);
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
        $nested = $this->getProperty($name, $separator);
        return $this->mergeArrays($data1, $data2, $nested);
    }

    /**
     * @param array $data1
     * @param array $data2
     * @param array $rules
     * @return array
     * @internal
     */
    protected function mergeArrays(array $data1, array $data2, array $rules)
    {
        foreach ($data2 as $key => $field) {
            $val = isset($rules[$key]) ? $rules[$key] : null;
            $rule = is_string($val) ? $this->items[$val] : null;

            if (!$rule && array_key_exists($key, $data1) && is_array($field) && is_array($val)) {
                // Array has been defined in blueprints.
                $data1[$key] = $this->mergeArrays($data1[$key], $field, $val);
            } else {
                // Otherwise just take value from the data2.
                $data1[$key] = $field;
            }
        }

        return $data1;
    }

    /**
     * Gets all field definitions from the blueprints.
     *
     * @param array $fields
     * @param array $params
     * @param string $prefix
     * @internal
     */
    protected function parseFormFields(array &$fields, $params, $prefix)
    {
        // Go though all the fields in current level.
        foreach ($fields as $key => &$field) {
            // Set name from the array key.
            $field['name'] = $prefix . $key;
            $field += $params;

            if (isset($field['fields'])) {
                // Recursively get all the nested fields.
                $newParams = array_intersect_key($this->filter, $field);
                $this->parseFormFields($field['fields'], $newParams, $prefix);
            } else {
                // Add rule.
                $this->items[$prefix . $key] = &$field;
                $this->addProperty($prefix . $key);

                foreach ($field as $name => $value) {
                    // TODO: Support nested blueprints.
                    /*
                    if (isset($this->context) && $name == '@import') {
                        $values = (array) $value;
                        if (!isset($field['fields'])) {
                            $field['fields'] = array();
                        }
                        foreach ($values as $bname) {
                            $b = $this->context->get($bname);
                            $field['fields'] = array_merge($field['fields'], $b->fields());
                        }
                    }

                    // Support for callable data values.
                    else
                    */
                    if (substr($name, 0, 6) == '@data-') {
                        $property = substr($name, 6);
                        if (is_array($value)) {
                            $func = array_shift($value);
                        } else {
                            $func = $value;
                            $value = array();
                        }
                        list($o, $f) = preg_split('/::/', $func);
                        if (!$f && function_exists($o)) {
                            $data = call_user_func_array($o, $value);
                        } elseif ($f && method_exists($o, $f)) {
                            $data = call_user_func_array(array($o, $f), $value);
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
                }

                // Initialize predefined validation rule.
                if (isset($field['validate']['rule'])) {
                    $field['validate'] += $this->getRule($field['validate']['rule']);
                }
            }
        }
    }

    /**
     * Get property from the definition.
     *
     * @param  string  $path  Comma separated path to the property.
     * @param  string  $separator
     * @return array
     * @internal
     */
    protected function getProperty($path = null, $separator = '.')
    {
        if (!$path) {
            return $this->nested;
        }
        $parts = explode($separator, $path);
        $item = array_pop($parts);

        $nested = $this->nested;
        foreach ($parts as $part) {
            if (!isset($nested[$part])) {
                return [];
            }
            $nested = $nested[$part];
        }

        return isset($nested[$item]) ? $nested[$item] : [];
    }

    /**
     * Add property to the definition.
     *
     * @param  string  $path  Comma separated path to the property.
     * @internal
     */
    protected function addProperty($path)
    {
        $parts = explode('.', $path);
        $item = array_pop($parts);

        $nested = &$this->nested;
        foreach ($parts as $part) {
            if (!isset($nested[$part])) {
                $nested[$part] = array();
            }
            $nested = &$nested[$part];
        }

        if (!isset($nested[$item])) {
            $nested[$item] = $path;
        }
    }

    /**
     * @param $rule
     * @return array
     * @internal
     */
    protected function getRule($rule)
    {
        if (isset($this->rules[$rule]) && is_array($this->rules[$rule])) {
            return $this->rules[$rule];
        }
        return array();
    }
}
