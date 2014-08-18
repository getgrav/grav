<?php
namespace Grav\Common\Data;

use \Symfony\Component\Yaml\Yaml;

/**
 * Blueprint handles the inside logic of blueprints.
 *
 * @author RocketTheme
 * @license MIT
 */
class Blueprint
{
    public $name;
    public $initialized = false;
    protected $blueprints;
    protected $context;
    protected $fields;
    protected $rules = array();
    protected $nested = array();

    /**
     * @param string $name
     * @param array  $data
     * @param Blueprints $context
     */
    public function __construct($name, array $data, Blueprints $context)
    {
        $this->name = $name;
        $this->blueprints = $data;
        $this->context = $context;
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
        $path = explode($separator, $name);
        $current = $this->blueprints;
        foreach ($path as $field) {
            if (is_object($current) && isset($current->{$field})) {
                $current = $current->{$field};
            } elseif (is_array($current) && isset($current[$field])) {
                $current = $current[$field];
            } else {
                return $default;
            }
        }

        return $current;
    }

    /**
     * Sey value by using dot notation for nested arrays/objects.
     *
     * @example $value = $data->set('this.is.my.nested.variable', true);
     *
     * @param string  $name       Dot separated path to the requested value.
     * @param mixed   $value      New value.
     * @param string  $separator  Separator, defaults to '.'
     */
    public function set($name, $value, $separator = '.')
    {
        $path = explode($separator, $name);
        $current = &$this->blueprints;
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

        $current = $value;
    }

    /**
     * Return all form fields.
     *
     * @return array
     */
    public function fields()
    {
        if (!isset($this->fields)) {
            $this->fields = isset($this->blueprints['form']['fields']) ? $this->blueprints['form']['fields'] : array();
            $this->getFields($this->fields);
        }

        return $this->fields;
    }

    /**
     * Validate data against blueprints.
     *
     * @param  array $data
     * @throws \RuntimeException
     */
    public function validate(array $data)
    {
        // Initialize data
        $this->fields();

        try {
            $this->validateArray($data, $this->nested);
        } catch (\RuntimeException $e) {
            throw new \RuntimeException(sprintf('Page validation failed: %s', $e->getMessage()));
        }
    }

    /**
     * Merge two arrays by using blueprints.
     *
     * @param  array $data1
     * @param  array $data2
     * @return array
     */
    public function mergeData(array $data1, array $data2)
    {
        // Initialize data
        $this->fields();
        return $this->mergeArrays($data1, $data2, $this->nested);
    }

    /**
     * Filter data by using blueprints.
     *
     * @param  array $data
     * @return array
     */
    public function filter(array $data)
    {
        // Initialize data
        $this->fields();
        return $this->filterArray($data, $this->nested);
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
        // Initialize data
        $this->fields();
        return $this->extraArray($data, $this->nested, $prefix);
    }

    /**
     * @param array $data
     * @param array $rules
     * @throws \RuntimeException
     * @internal
     */
    protected function validateArray(array $data, array $rules)
    {
        $this->checkRequired($data, $rules);

        foreach ($data as $key => $field) {
            $val = isset($rules[$key]) ? $rules[$key] : null;
            $rule = is_string($val) ? $this->rules[$val] : null;

            if ($rule) {
                // Item has been defined in blueprints.
                Validation::validate($field, $rule);
            } elseif (is_array($field) && is_array($val)) {
                // Array has been defined in blueprints.
                $this->validateArray($field, $val);
            } elseif (isset($this->blueprints['validation']) && $this->blueprints['validation'] == 'strict') {
                 // Undefined/extra item.
                 throw new \RuntimeException(sprintf('%s is not defined in blueprints', $key));
            }
        }
    }

    /**
     * @param array $data
     * @param array $rules
     * @return array
     * @internal
     */
    protected function filterArray(array $data, array $rules)
    {
        $results = array();
        foreach ($data as $key => $field) {
            $val = isset($rules[$key]) ? $rules[$key] : null;
            $rule = is_string($val) ? $this->rules[$val] : null;

            if ($rule) {
                // Item has been defined in blueprints.
                $field = Validation::filter($field, $rule);
            } elseif (is_array($field) && is_array($val)) {
                // Array has been defined in blueprints.
                $field = $this->filterArray($field, $val);
            } elseif (isset($this->blueprints['validation']) && $this->blueprints['validation'] == 'strict') {
                $field = null;
            }

            if (isset($field) && (!is_array($field) || !empty($field))) {
                $results[$key] = $field;
            }
        }

        return $results;
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
            $rule = is_string($val) ? $this->rules[$val] : null;

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
     * @param array $data
     * @param array $rules
     * @param string $prefix
     * @return array
     * @internal
     */
    protected function extraArray(array $data, array $rules, $prefix)
    {
        $array = array();
        foreach ($data as $key => $field) {
            $val = isset($rules[$key]) ? $rules[$key] : null;
            $rule = is_string($val) ? $this->rules[$val] : null;

            if ($rule) {
                // Item has been defined in blueprints.
            } elseif (is_array($field) && is_array($val)) {
                // Array has been defined in blueprints.
                $array += $this->ExtraArray($field, $val, $prefix);
            } else {
                // Undefined/extra item.
                $array[$prefix.$key] = $field;
            }
        }
        return $array;
    }

    /**
     * Gets all field definitions from the blueprints.
     *
     * @param array $fields
     * @internal
     */
    protected function getFields(array &$fields)
    {
        // Go though all the fields in current level.
        foreach ($fields as $key => &$field) {
            // Set name from the array key.
            $field['name'] = $key;

            if (isset($field['fields'])) {
                // Recursively get all the nested fields.
                $this->getFields($field['fields']);
            } else {
                // Add rule.
                $this->rules[$key] = &$field;
                $this->addProperty($key);

                foreach ($field as $name => $value) {
                    // Support nested blueprints.
                    if ($name == '@import') {
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
                    elseif (substr($name, 0, 6) == '@data-') {
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
        if (isset($this->blueprints['rules'][$rule]) && is_array($this->blueprints['rules'][$rule])) {
            return $this->blueprints['rules'][$rule];
        }
        return array();
    }

    /**
     * @param array $data
     * @param array $fields
     * @throws \RuntimeException
     * @internal
     */
    protected function checkRequired(array $data, array $fields) {
        foreach ($fields as $name => $field) {
            if (!is_string($field)) {
                continue;
            }
            $field = $this->rules[$field];
            if (isset($field['validate']['required'])
                && $field['validate']['required'] == true
                && empty($data[$name])) {
                throw new \RuntimeException("Missing required field: {$field['name']}");
            }
        }
    }

    /**
     * Convert blueprints into an array.
     *
     * @return array
     */
    public function toArray()
    {
        return $this->blueprints;
    }

    /**
     * Convert blueprints into YAML string.
     *
     * @return string
     */
    public function toYaml()
    {
        return Yaml::dump($this->blueprints);
    }

    /**
     * Convert blueprints into JSON string.
     *
     * @return string
     */
    public function toJson()
    {
        return json_encode($this->blueprints);
    }

    /**
     * Extend blueprint with another blueprint.
     *
     * @param Blueprint $extends
     * @param bool $append
     */
    public function extend(Blueprint $extends, $append = false)
    {
        $blueprints = $append ? $this->blueprints : $extends->toArray();
        $appended = $append ? $extends->toArray() : $this->blueprints;

        $bref_stack = array(&$blueprints);
        $head_stack = array($appended);

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
                    $bref = array_merge($bref, array($key => $head[$key]));
                }
            }
        } while(count($head_stack));

        $this->blueprints = $blueprints;
    }
}
