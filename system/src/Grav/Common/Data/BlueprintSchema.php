<?php

/**
 * @package    Grav\Common\Data
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
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
     * @return array
     */
    public function getTypes()
    {
        return $this->types;
    }

    /**
     * @param string $name
     * @return array
     */
    public function getType($name)
    {
        return $this->types[$name] ?? [];
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
     * @param array $data
     * @param array $toggles
     * @return array
     */
    public function processForm(array $data, array $toggles = [])
    {
        return $this->processFormRecursive($data, $toggles, $this->nested);
    }

    /**
     * Filter data by using blueprints.
     *
     * @param  array $data                  Incoming data, for example from a form.
     * @param  bool  $missingValuesAsNull   Include missing values as nulls.
     * @param bool   $keepEmptyValues       Include empty values.
     * @return array
     */
    public function filter(array $data, $missingValuesAsNull = false, $keepEmptyValues = false)
    {
        return $this->filterArray($data, $this->nested, $missingValuesAsNull, $keepEmptyValues);
    }

    /**
     * Flatten data by using blueprints.
     *
     * @param  array $data                  Data to be flattened.
     * @return array
     */
    public function flattenData(array $data)
    {
        return $this->flattenArray($data, $this->nested, '');
    }

    /**
     * @param array $data
     * @param array $rules
     * @param string $prefix
     * @return array
     */
    protected function flattenArray(array $data, array $rules, string $prefix)
    {
        $array = [];

        foreach ($data as $key => $field) {
            $val = $rules[$key] ?? $rules['*'] ?? null;
            $rule = is_string($val) ? $this->items[$val] : null;

            if ($rule || isset($val['*'])) {
                // Item has been defined in blueprints.
                $array[$prefix.$key] = $field;
            } elseif (is_array($field) && is_array($val)) {
                // Array has been defined in blueprints.
                $array += $this->flattenArray($field, $val, $prefix . $key . '.');
            } else {
                // Undefined/extra item.
                $array[$prefix.$key] = $field;
            }
        }
        return $array;
    }

    /**
     * @param array $data
     * @param array $rules
     * @return array
     * @throws \RuntimeException
     */
    protected function validateArray(array $data, array $rules)
    {
        $messages = $this->checkRequired($data, $rules);

        foreach ($data as $key => $child) {
            $val = $rules[$key] ?? $rules['*'] ?? null;
            $rule = \is_string($val) ? $this->items[$val] : null;

            if ($rule) {
                // Item has been defined in blueprints.
                if (!empty($rule['disabled']) || !empty($rule['validate']['ignore'])) {
                    // Skip validation in the ignored field.
                    continue;
                }

                $messages += Validation::validate($child, $rule);
            } elseif (\is_array($child) && \is_array($val)) {
                // Array has been defined in blueprints.
                $messages += $this->validateArray($child, $val);
            } elseif (isset($rules['validation']) && $rules['validation'] === 'strict') {
                // Undefined/extra item.
                throw new \RuntimeException(sprintf('%s is not defined in blueprints', $key));
            }
        }

        return $messages;
    }

    /**
     * @param array $data
     * @param array $rules
     * @param bool  $missingValuesAsNull
     * @param bool $keepEmptyValues
     * @return array
     */
    protected function filterArray(array $data, array $rules, $missingValuesAsNull, $keepEmptyValues)
    {
        $results = [];

        if ($missingValuesAsNull) {
            // First pass is to fill up all the fields with null. This is done to lock the ordering of the fields.
            foreach ($rules as $key => $rule) {
                if ($key && !isset($results[$key])) {
                    $val = $rules[$key] ?? $rules['*'] ?? null;
                    $rule = \is_string($val) ? $this->items[$val] : null;

                    if (empty($rule['disabled']) && empty($rule['validate']['ignore'])) {
                        continue;
                    }
                }
            }
        }

        foreach ($data as $key => $field) {
            $val = $rules[$key] ?? $rules['*'] ?? null;
            $rule = \is_string($val) ? $this->items[$val] : null;

            if ($rule) {
                // Item has been defined in blueprints.
                if (!empty($rule['disabled']) || !empty($rule['validate']['ignore'])) {
                    // Skip any data in the ignored field.
                    unset($results[$key]);
                    continue;
                }

                $field = Validation::filter($field, $rule);
            } elseif (\is_array($field) && \is_array($val)) {
                // Array has been defined in blueprints.
                $field = $this->filterArray($field, $val, $missingValuesAsNull, $keepEmptyValues);

            } elseif (isset($rules['validation']) && $rules['validation'] === 'strict') {
                // Skip any extra data.
                continue;
            }

            if ($keepEmptyValues || (null !== $field && (!\is_array($field) || !empty($field)))) {
                $results[$key] = $field;
            }
        }

        return $results ?: null;
    }

    /**
     * @param array|null $data
     * @param array $toggles
     * @param array $nested
     * @return array|null
     */
    protected function processFormRecursive(?array $data, array $toggles, array $nested)
    {
        foreach ($nested as $key => $value) {
            if ($key === '') {
                continue;
            }
            if ($key === '*') {
                // TODO: Add support to collections.
                continue;
            }
            if (is_array($value)) {
                // Recursively fetch the items.
                $data[$key] = $this->processFormRecursive($data[$key] ?? null, $toggles[$key] ?? [], $value);
            } else {
                $field = $this->get($value);
                // Do not add the field if:
                if (
                    // Not an input field
                    !$field
                    // Field has been disabled
                    || !empty($field['disabled'])
                    // Field validation is set to be ignored
                    || !empty($field['validate']['ignore'])
                    // Field is toggleable and the toggle is turned off
                    || (!empty($field['toggleable']) && empty($toggles[$key]))
                ) {
                    continue;
                }
                if (!isset($data[$key])) {
                    $data[$key] = null;
                }
            }
        }

        return $data;
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
            if (!\is_string($field)) {
                continue;
            }

            $field = $this->items[$field];

            // Skip ignored field, it will not be required.
            if (!empty($field['disabled']) || !empty($field['validate']['ignore'])) {
                continue;
            }

            // Check if required.
            if (isset($field['validate']['required'])
                && $field['validate']['required'] === true) {

                if (isset($data[$name])) {
                    continue;
                }
                if ($field['type'] === 'file' && isset($data['data']['name'][$name])) { //handle case of file input fields required
                    continue;
                }

                $value = $field['label'] ?? $field['name'];
                $language = Grav::instance()['language'];
                $message  = sprintf($language->translate('GRAV.FORM.MISSING_REQUIRED_FIELD', null, true) . ' %s', $language->translate($value));
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

        $default = $field[$property] ?? null;
        $config = Grav::instance()['config']->get($value, $default);

        if (null !== $config) {
            $field[$property] = $config;
        }
    }
}
