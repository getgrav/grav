<?php
namespace Grav\Common\Data;

use Grav\Common\Grav;
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
    use Export;

    public $initialized = false;

    /**
     * @param string|array $name
     * @param array $data
     * @param Blueprints $context
     */
    public function __construct($name = null, array $data = null)
    {
        parent::__construct(is_array($name) ? $name : null);

        $types = Grav::instance()['plugins']->formFieldTypes;

        $this->setTypes($types);

        if ($data) {
            $this->embed('', $data);
        }
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
            $messages = $this->validateArray($data, $this->nested);

        } catch (\RuntimeException $e) {
            throw (new ValidationException($e->getMessage(), $e->getCode(), $e))->setMessages();
        }

        if (!empty($messages)) {
            throw (new ValidationException())->setMessages($messages);
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
     * @param array $data
     * @param array $rules
     * @returns array
     * @throws \RuntimeException
     * @internal
     */
    protected function validateArray(array $data, array $rules)
    {
        $messages = $this->checkRequired($data, $rules);

        foreach ($data as $key => $field) {
            $val = isset($rules[$key]) ? $rules[$key] : null;
            $rule = is_string($val) ? $this->items[$val] : null;

            if ($rule) {
                // Item has been defined in blueprints.
                $messages += Validation::validate($field, $rule);
            } elseif (is_array($field) && is_array($val)) {
                // Array has been defined in blueprints.
                $messages += $this->validateArray($field, $val);
            } elseif (isset($rules['validation']) && $rules['validation'] == 'strict') {
                // Undefined/extra item.
                throw new \RuntimeException(sprintf('%s is not defined in blueprints', $key));
            }
        }

        return $messages;
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
            } elseif (isset($rules['validation']) && $rules['validation'] == 'strict') {
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
     * @return array
     */
    protected function checkRequired(array $data, array $fields)
    {
        $messages = [];

        foreach ($fields as $name => $field) {
            if (!is_string($field)) {
                continue;
            }
            $field = $this->items[$field];
            if (isset($field['validate']['required'])
                && $field['validate']['required'] === true
                && !isset($data[$name])) {
                $value = isset($field['label']) ? $field['label'] : $field['name'];
                $language = Grav::instance()['language'];
                $message  = sprintf($language->translate('FORM.MISSING_REQUIRED_FIELD', null, true) . ' %s', $value);
                $messages[$field['name']][] = $message;
            }
        }

        return $messages;
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
