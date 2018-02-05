<?php
/**
 * @package    Grav.Common.Data
 *
 * @copyright  Copyright (C) 2014 - 2017 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Data;

use Grav\Common\Grav;
use RocketTheme\Toolbox\ArrayTraits\Export;
use RocketTheme\Toolbox\ArrayTraits\ExportInterface;
use RocketTheme\Toolbox\Blueprints\BlueprintSchema as BlueprintSchemaBase;

class BlueprintSchema extends BlueprintSchemaBase implements ExportInterface
{
    use Export;

    protected $ignoreFormKeys = [
        'title' => true,
        'help' => true,
        'placeholder' => true,
        'placeholder_key' => true,
        'placeholder_value' => true,
        'fields' => true
    ];

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
            $val = isset($rules[$key]) ? $rules[$key] : (isset($rules['*']) ? $rules['*'] : null);
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
            $val = isset($rules[$key]) ? $rules[$key] : (isset($rules['*']) ? $rules['*'] : null);
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
                && $field['validate']['required'] === true) {

                if (isset($data[$name])) {
                    continue;
                }
                if ($field['type'] == 'file' && isset($data['data']['name'][$name])) { //handle case of file input fields required
                    continue;
                }

                $value = isset($field['label']) ? $field['label'] : $field['name'];
                $language = Grav::instance()['language'];
                $message  = sprintf($language->translate('FORM.MISSING_REQUIRED_FIELD', null, true) . ' %s', $language->translate($value));
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
