<?php
namespace Grav\Common\Data;

use Grav\Common\GravTrait;
use RocketTheme\Toolbox\ArrayTraits\Export;
use RocketTheme\Toolbox\ArrayTraits\ExportInterface;
use RocketTheme\Toolbox\Blueprints\Blueprints as BaseBlueprints;

/**
 * Blueprint handles the inside logic of blueprints.
 *
 * @author RocketTheme
 * @license MIT
 */
class Blueprint extends BaseBlueprints implements ExportInterface
{
    use Export, GravTrait;

    public $initialized = false;

    protected $context;

    /**
     * @param string|array $name
     * @param array $data
     * @param Blueprints $context
     */
    public function __construct($name = null, array $data = [], Blueprints $context = null)
    {
        parent::__construct(is_array($name) ? $name : null);

        if ($data) {
            $this->embed('', $data);
        }

        if ($context) {
            $this->setContext($context);
        }
    }

    /**
     * Set context to find external blueprints.
     *
     * @param Blueprints $context
     * @return $this
     */
    public function setContext(Blueprints $context)
    {
        $this->context = $context;

        return $this;
    }


    /**
     * Get meta value by using dot notation for nested arrays/objects.
     *
     * @param string  $name       Dot separated path to the requested value.
     * @param string  $field      Meta field to fetch.
     * @param mixed   $default    Default value (or null).
     * @param string  $separator  Separator, defaults to '.'
     *
     * @return mixed  Value.
     */
    public function getMeta($name, $field, $default = null, $separator = '.')
    {
        $name = $separator != '.' ? strtr($name, $separator, '.') : $name;

        return isset($this->items[$name]['meta'][$field]) ? $this->items[$name]['meta'][$field] : $default;
    }

    /**
     * Set meta value by using dot notation for nested arrays/objects.
     *
     * @param string  $name       Dot separated path to the requested value.
     * @param string  $field      Meta field to fetch.
     * @param mixed   $value      New value.
     * @param string  $separator  Separator, defaults to '.'
     */
    public function setMeta($name, $field, $value, $separator = '.')
    {
        $name = $separator != '.' ? strtr($name, $separator, '.') : $name;

        $this->items[$name]['meta'][$field] = $value;
    }

    /**
     * Define meta value by using dot notation for nested arrays/objects.
     *
     * @param string  $name       Dot separated path to the requested value.
     * @param string  $field      Meta field to fetch.
     * @param mixed   $value      New value.
     * @param string  $separator  Separator, defaults to '.'
     */
    public function defMeta($name, $field, $value, $separator = '.')
    {
        $this->setMeta($name, $field, $this->getMeta($name, $field, $value, $separator), $separator);
    }

    /**
     * Return all form fields in a nested list.
     *
     * @return array
     */
    public function fields($name = '', $separator = '.')
    {
        $name = $separator != '.' ? strtr($name, $separator, '.') : $name;
        if (isset($this->form[$name])) {
            $form = &$this->form[$name];
        } else {
            return [];
        }

        $fields = $this->buildFields($form);

        return $fields;
    }

    public function toArray($name = '', $separator = '.')
    {
        $meta = isset($this->items[$name]['meta']) ? $this->items[$name]['meta'] : [];
        $formMeta = isset($this->items[$name]['form']) ? $this->items[$name]['form'] : [];
        $fields = $this->fields($name, $separator);

        return $meta + ['form' => $formMeta + ['fields' => $fields]];
    }

    protected function buildFields(array &$fields)
    {
        $result = [];

        foreach ($fields as $key => $value) {
            $result[$key] = isset($this->items[$key]) ? $this->items[$key] : [];
            if (is_array($value)) {
                $result[$key]['fields'] = $this->buildFields($value);
            }
        }

        return $result;
    }

    /**
     * Validate data against blueprints.
     *
     * @param  array $data
     * @throws \RuntimeException
     */
    public function validate(array $data)
    {
        try {
            $this->validateArray($data, $this->nested);
        } catch (\RuntimeException $e) {
            $language = self::getGrav()['language'];
            $message = sprintf($language->translate('FORM.VALIDATION_FAIL', null, true) . ' %s', $e->getMessage());
            throw new \RuntimeException($message);
        }
    }

    /**
     * Filter data by using blueprints.
     *
     * @param  array $data
     * @return array
     */
    public function filter(array $data)
    {
        return $this->filterArray($data, $this->nested);
    }

    /**
     * Extend blueprint with another blueprint.
     *
     * @param Blueprint $extends
     * @param bool $append
     */
    public function extend(Blueprint $extends, $append = false)
    {
        throw new \Exception('Extend is not implemented yet');
        // FIXME: Currently not working...
        $blueprints = $append ? $this->form : $extends->fields();
        $appended = $append ? $extends->fields() : $this->form;

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
        } while (count($head_stack));

        $this->form = $blueprints;
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
            $rule = is_string($val) ? $this->items[$val] : null;

            if ($rule) {
                // Item has been defined in blueprints.
                Validation::validate($field, $rule);
            } elseif (is_array($field) && is_array($val)) {
                // Array has been defined in blueprints.
                $this->validateArray($field, $val);
            } elseif (isset($this->items['form']['validation']) && $this->items['form']['validation'] == 'strict') {
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
            $rule = is_string($val) ? $this->items[$val] : null;

            if ($rule) {
                // Item has been defined in blueprints.
                $field = Validation::filter($field, $rule);
            } elseif (is_array($field) && is_array($val)) {
                // Array has been defined in blueprints.
                $field = $this->filterArray($field, $val);
            } elseif (isset($this->items['form']['validation']) && $this->items['form']['validation'] == 'strict') {
                $field = null;
            }

            if (isset($field) && (!is_array($field) || !empty($field))) {
                $results[$key] = $field;
            }
        }

        return $results;
    }

    /**
     * @param array $data
     * @param array $fields
     * @throws \RuntimeException
     * @internal
     */
    protected function checkRequired(array $data, array $fields)
    {
        foreach ($fields as $name => $field) {
            if (!is_string($field)) {
                continue;
            }
            $field = $this->items[$field];
            if (isset($field['validate']['required'])
                && $field['validate']['required'] === true
                && empty($data[$name])) {
                $value = isset($field['label']) ? $field['label'] : $field['name'];
                $language = self::getGrav()['language'];
                $message  = sprintf($language->translate('FORM.MISSING_REQUIRED_FIELD', null, true) . ' %s', $value);
                throw new \RuntimeException($message);
            }
        }
    }

    /**
     * @param array $field
     * @param string $property
     * @param array $call
     */
    protected function dynamicImport(array &$field, $property, array &$call)
    {
        $params = $call['params'];

        // Support nested blueprints.
        if ($this->context) {
            $values = (array) $params;
            if (!isset($field['fields'])) {
                $field['fields'] = [];
            }
            foreach ($values as $bname) {
                $b = $this->context->get($bname);
                $field['fields'] = array_merge($field['fields'], $b->fields());
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
        $config = self::getGrav()['config']->get($value, $default);

        if (!is_null($config)) {
            $field[$property] = $config;
        }
    }
}
