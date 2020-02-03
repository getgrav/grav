<?php

/**
 * @package    Grav\Common\Data
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Data;

use Grav\Common\Grav;
use Grav\Common\Utils;
use Grav\Common\Yaml;

class Validation
{
    /**
     * Validate value against a blueprint field definition.
     *
     * @param mixed $value
     * @param array $field
     * @return array
     */
    public static function validate($value, array $field)
    {
        if (!isset($field['type'])) {
            $field['type'] = 'text';
        }

        $validate = (array)($field['validate'] ?? null);
        $type = $validate['type'] ?? $field['type'];
        $required = $validate['required'] ?? false;

        // If value isn't required, we will stop validation if empty value is given.
        if ($required !== true && ($value === null || $value === '' || (($field['type'] === 'checkbox' || $field['type'] === 'switch') && $value == false))
        ) {
            return [];
        }

        // Get language class.
        $language = Grav::instance()['language'];

        $name = ucfirst($field['label'] ?? $field['name']);
        $message = (string) isset($field['validate']['message'])
            ? $language->translate($field['validate']['message'])
            : $language->translate('GRAV.FORM.INVALID_INPUT', null, true) . ' "' . $language->translate($name) . '"';


        // Validate type with fallback type text.
        $method = 'type' . str_replace('-', '_', $type);

        // If this is a YAML field validate/filter as such
        if (isset($field['yaml']) && $field['yaml'] === true) {
            $method = 'typeYaml';
        }

        $messages = [];

        $success = method_exists(__CLASS__, $method) ? self::$method($value, $validate, $field) : true;
        if (!$success) {
            $messages[$field['name']][] = $message;
        }

        // Check individual rules.
        foreach ($validate as $rule => $params) {
            $method = 'validate' . ucfirst(str_replace('-', '_', $rule));

            if (method_exists(__CLASS__, $method)) {
                $success = self::$method($value, $params);

                if (!$success) {
                    $messages[$field['name']][] = $message;
                }
            }
        }

        return $messages;
    }

    /**
     * Filter value against a blueprint field definition.
     *
     * @param  mixed  $value
     * @param  array  $field
     * @return mixed  Filtered value.
     */
    public static function filter($value, array $field)
    {
        $validate = (array)($field['filter'] ?? $field['validate'] ?? null);

        // If value isn't required, we will return null if empty value is given.
        if (($value === null || $value === '') && empty($validate['required'])) {
            return null;
        }

        if (!isset($field['type'])) {
            $field['type'] = 'text';
        }
        $type = $field['filter']['type'] ?? $field['validate']['type'] ?? $field['type'];

        $method = 'filter' . ucfirst(str_replace('-', '_', $type));

        // If this is a YAML field validate/filter as such
        if (isset($field['yaml']) && $field['yaml'] === true) {
            $method = 'filterYaml';
        }

        if (!method_exists(__CLASS__, $method)) {
            $method = isset($field['array']) && $field['array'] === true ? 'filterArray' : 'filterText';
        }

        return self::$method($value, $validate, $field);
    }

    /**
     * HTML5 input: text
     *
     * @param  mixed  $value   Value to be validated.
     * @param  array  $params  Validation parameters.
     * @param  array  $field   Blueprint for the field.
     * @return bool   True if validation succeeded.
     */
    public static function typeText($value, array $params, array $field)
    {
        if (!\is_string($value) && !is_numeric($value)) {
            return false;
        }

        $value = (string)$value;

        if (!empty($params['trim'])) {
            $value = trim($value);
        }

        if (isset($params['min']) && \strlen($value) < $params['min']) {
            return false;
        }

        if (isset($params['max']) && \strlen($value) > $params['max']) {
            return false;
        }

        $min = $params['min'] ?? 0;
        if (isset($params['step']) && (\strlen($value) - $min) % $params['step'] === 0) {
            return false;
        }

        if ((!isset($params['multiline']) || !$params['multiline']) && preg_match('/\R/um', $value)) {
            return false;
        }

        return true;
    }

    protected static function filterText($value, array $params, array $field)
    {
        if (!\is_string($value) && !is_numeric($value)) {
            return '';
        }

        if (!empty($params['trim'])) {
            $value = trim($value);
        }

        return (string) $value;
    }

    /**
     * @param mixed $value
     * @param array $params
     * @param array $field
     * @return string|null
     */
    protected static function filterCheckbox($value, array $params, array $field)
    {
        $value = (string)$value;
        $field_value = (string)($field['value'] ?? '1');

        return $value === $field_value ? $value : null;
    }

    protected static function filterCommaList($value, array $params, array $field)
    {
        return \is_array($value) ? $value : preg_split('/\s*,\s*/', $value, -1, PREG_SPLIT_NO_EMPTY);
    }

    public static function typeCommaList($value, array $params, array $field)
    {
        return \is_array($value) ? true : self::typeText($value, $params, $field);
    }

    protected static function filterLower($value, array $params)
    {
        return strtolower($value);
    }

    protected static function filterUpper($value, array $params)
    {
        return strtoupper($value);
    }


    /**
     * HTML5 input: textarea
     *
     * @param  mixed  $value   Value to be validated.
     * @param  array  $params  Validation parameters.
     * @param  array  $field   Blueprint for the field.
     * @return bool   True if validation succeeded.
     */
    public static function typeTextarea($value, array $params, array $field)
    {
        if (!isset($params['multiline'])) {
            $params['multiline'] = true;
        }

        return self::typeText($value, $params, $field);
    }

    /**
     * HTML5 input: password
     *
     * @param  mixed  $value   Value to be validated.
     * @param  array  $params  Validation parameters.
     * @param  array  $field   Blueprint for the field.
     * @return bool   True if validation succeeded.
     */
    public static function typePassword($value, array $params, array $field)
    {
        return self::typeText($value, $params, $field);
    }

    /**
     * HTML5 input: hidden
     *
     * @param  mixed  $value   Value to be validated.
     * @param  array  $params  Validation parameters.
     * @param  array  $field   Blueprint for the field.
     * @return bool   True if validation succeeded.
     */
    public static function typeHidden($value, array $params, array $field)
    {
        return self::typeText($value, $params, $field);
    }

    /**
     * Custom input: checkbox list
     *
     * @param  mixed  $value   Value to be validated.
     * @param  array  $params  Validation parameters.
     * @param  array  $field   Blueprint for the field.
     * @return bool   True if validation succeeded.
     */
    public static function typeCheckboxes($value, array $params, array $field)
    {
        // Set multiple: true so checkboxes can easily use min/max counts to control number of options required
        $field['multiple'] = true;

        return self::typeArray((array) $value, $params, $field);
    }

    protected static function filterCheckboxes($value, array $params, array $field)
    {
        return self::filterArray($value, $params, $field);
    }

    /**
     * HTML5 input: checkbox
     *
     * @param  mixed  $value   Value to be validated.
     * @param  array  $params  Validation parameters.
     * @param  array  $field   Blueprint for the field.
     * @return bool   True if validation succeeded.
     */
    public static function typeCheckbox($value, array $params, array $field)
    {
        $value = (string)$value;
        $field_value = (string)($field['value'] ?? '1');

        return $value === $field_value;
    }

    /**
     * HTML5 input: radio
     *
     * @param  mixed  $value   Value to be validated.
     * @param  array  $params  Validation parameters.
     * @param  array  $field   Blueprint for the field.
     * @return bool   True if validation succeeded.
     */
    public static function typeRadio($value, array $params, array $field)
    {
        return self::typeArray((array) $value, $params, $field);
    }

    /**
     * Custom input: toggle
     *
     * @param  mixed  $value   Value to be validated.
     * @param  array  $params  Validation parameters.
     * @param  array  $field   Blueprint for the field.
     * @return bool   True if validation succeeded.
     */
    public static function typeToggle($value, array $params, array $field)
    {
        if (\is_bool($value)) {
            $value = (int)$value;
        }

        return self::typeArray((array) $value, $params, $field);
    }

    /**
     * Custom input: file
     *
     * @param  mixed  $value   Value to be validated.
     * @param  array  $params  Validation parameters.
     * @param  array  $field   Blueprint for the field.
     * @return bool   True if validation succeeded.
     */
    public static function typeFile($value, array $params, array $field)
    {
        return self::typeArray((array)$value, $params, $field);
    }

    protected static function filterFile($value, array $params, array $field)
    {
        return (array)$value;
    }

    /**
     * HTML5 input: select
     *
     * @param  mixed  $value   Value to be validated.
     * @param  array  $params  Validation parameters.
     * @param  array  $field   Blueprint for the field.
     * @return bool   True if validation succeeded.
     */
    public static function typeSelect($value, array $params, array $field)
    {
        return self::typeArray((array) $value, $params, $field);
    }

    /**
     * HTML5 input: number
     *
     * @param  mixed  $value   Value to be validated.
     * @param  array  $params  Validation parameters.
     * @param  array  $field   Blueprint for the field.
     * @return bool   True if validation succeeded.
     */
    public static function typeNumber($value, array $params, array $field)
    {
        if (!is_numeric($value)) {
            return false;
        }

        if (isset($params['min']) && $value < $params['min']) {
            return false;
        }

        if (isset($params['max']) && $value > $params['max']) {
            return false;
        }

        $min = $params['min'] ?? 0;

        return !(isset($params['step']) && fmod($value - $min, $params['step']) === 0);
    }

    protected static function filterNumber($value, array $params, array $field)
    {
        return (string)(int)$value !== (string)(float)$value ? (float) $value : (int) $value;
    }

    protected static function filterDateTime($value, array $params, array $field)
    {
        $format = Grav::instance()['config']->get('system.pages.dateformat.default');
        if ($format) {
            $converted = new \DateTime($value);
            return $converted->format($format);
        }
        return $value;
    }


    /**
     * HTML5 input: range
     *
     * @param  mixed  $value   Value to be validated.
     * @param  array  $params  Validation parameters.
     * @param  array  $field   Blueprint for the field.
     * @return bool   True if validation succeeded.
     */
    public static function typeRange($value, array $params, array $field)
    {
        return self::typeNumber($value, $params, $field);
    }

    protected static function filterRange($value, array $params, array $field)
    {
        return self::filterNumber($value, $params, $field);
    }

    /**
     * HTML5 input: color
     *
     * @param  mixed  $value   Value to be validated.
     * @param  array  $params  Validation parameters.
     * @param  array  $field   Blueprint for the field.
     * @return bool   True if validation succeeded.
     */
    public static function typeColor($value, array $params, array $field)
    {
        return preg_match('/^\#[0-9a-fA-F]{3}[0-9a-fA-F]{3}?$/u', $value);
    }

    /**
     * HTML5 input: email
     *
     * @param  mixed  $value   Value to be validated.
     * @param  array  $params  Validation parameters.
     * @param  array  $field   Blueprint for the field.
     * @return bool   True if validation succeeded.
     */
    public static function typeEmail($value, array $params, array $field)
    {
        $values = !\is_array($value) ? explode(',', preg_replace('/\s+/', '', $value)) : $value;

        foreach ($values as $val) {
            if (!(self::typeText($val, $params, $field) && filter_var($val, FILTER_VALIDATE_EMAIL))) {
                return false;
            }
        }

        return true;
    }

    /**
     * HTML5 input: url
     *
     * @param  mixed  $value   Value to be validated.
     * @param  array  $params  Validation parameters.
     * @param  array  $field   Blueprint for the field.
     * @return bool   True if validation succeeded.
     */

    public static function typeUrl($value, array $params, array $field)
    {
        return self::typeText($value, $params, $field) && filter_var($value, FILTER_VALIDATE_URL);
    }

    /**
     * HTML5 input: datetime
     *
     * @param  mixed  $value   Value to be validated.
     * @param  array  $params  Validation parameters.
     * @param  array  $field   Blueprint for the field.
     * @return bool   True if validation succeeded.
     */
    public static function typeDatetime($value, array $params, array $field)
    {
        if ($value instanceof \DateTime) {
            return true;
        }
        if (!\is_string($value)) {
            return false;
        }
        if (!isset($params['format'])) {
            return false !== strtotime($value);
        }

        $dateFromFormat = \DateTime::createFromFormat($params['format'], $value);

        return $dateFromFormat && $value === date($params['format'], $dateFromFormat->getTimestamp());
    }

    /**
     * HTML5 input: datetime-local
     *
     * @param  mixed  $value   Value to be validated.
     * @param  array  $params  Validation parameters.
     * @param  array  $field   Blueprint for the field.
     * @return bool   True if validation succeeded.
     */
    public static function typeDatetimeLocal($value, array $params, array $field)
    {
        return self::typeDatetime($value, $params, $field);
    }

    /**
     * HTML5 input: date
     *
     * @param  mixed  $value   Value to be validated.
     * @param  array  $params  Validation parameters.
     * @param  array  $field   Blueprint for the field.
     * @return bool   True if validation succeeded.
     */
    public static function typeDate($value, array $params, array $field)
    {
        if (!isset($params['format'])) {
            $params['format'] = 'Y-m-d';
        }

        return self::typeDatetime($value, $params, $field);
    }

    /**
     * HTML5 input: time
     *
     * @param  mixed  $value   Value to be validated.
     * @param  array  $params  Validation parameters.
     * @param  array  $field   Blueprint for the field.
     * @return bool   True if validation succeeded.
     */
    public static function typeTime($value, array $params, array $field)
    {
        if (!isset($params['format'])) {
            $params['format'] = 'H:i';
        }

        return self::typeDatetime($value, $params, $field);
    }

    /**
     * HTML5 input: month
     *
     * @param  mixed  $value   Value to be validated.
     * @param  array  $params  Validation parameters.
     * @param  array  $field   Blueprint for the field.
     * @return bool   True if validation succeeded.
     */
    public static function typeMonth($value, array $params, array $field)
    {
        if (!isset($params['format'])) {
            $params['format'] = 'Y-m';
        }

        return self::typeDatetime($value, $params, $field);
    }

    /**
     * HTML5 input: week
     *
     * @param  mixed  $value   Value to be validated.
     * @param  array  $params  Validation parameters.
     * @param  array  $field   Blueprint for the field.
     * @return bool   True if validation succeeded.
     */
    public static function typeWeek($value, array $params, array $field)
    {
        if (!isset($params['format']) && !preg_match('/^\d{4}-W\d{2}$/u', $value)) {
            return false;
        }

        return self::typeDatetime($value, $params, $field);
    }

    /**
     * Custom input: array
     *
     * @param  mixed  $value   Value to be validated.
     * @param  array  $params  Validation parameters.
     * @param  array  $field   Blueprint for the field.
     * @return bool   True if validation succeeded.
     */
    public static function typeArray($value, array $params, array $field)
    {
        if (!\is_array($value)) {
            return false;
        }

        if (isset($field['multiple'])) {
            if (isset($params['min']) && \count($value) < $params['min']) {
                return false;
            }

            if (isset($params['max']) && \count($value) > $params['max']) {
                return false;
            }

            $min = $params['min'] ?? 0;
            if (isset($params['step']) && (\count($value) - $min) % $params['step'] === 0) {
                return false;
            }
        }

        // If creating new values is allowed, no further checks are needed.
        if (!empty($field['selectize']['create'])) {
            return true;
        }

        $options = $field['options'] ?? [];
        $use = $field['use'] ?? 'values';

        if (empty($field['selectize']) || empty($field['multiple'])) {
            $options = array_keys($options);
        }
        if ($use === 'keys') {
            $value = array_keys($value);
        }

        return !($options && array_diff($value, $options));
    }

    protected static function filterArray($value, $params, $field)
    {
        $values = (array) $value;
        $options = isset($field['options']) ? array_keys($field['options']) : [];
        $multi = $field['multiple'] ?? false;

        if (\count($values) === 1 && isset($values[0]) && $values[0] === '') {
            return null;
        }


        if ($options) {
            $useKey = isset($field['use']) && $field['use'] === 'keys';
            foreach ($values as $key => $val) {
                $values[$key] = $useKey ? (bool) $val : $val;
            }
        }

        if ($multi) {
            foreach ($values as $key => $val) {
                if (\is_array($val)) {
                    $val = implode(',', $val);
                    $values[$key] =  array_map('trim', explode(',', $val));
                } else {
                    $values[$key] =  trim($val);
                }
            }
        }

        if (isset($field['ignore_empty']) && Utils::isPositive($field['ignore_empty'])) {
            foreach ($values as $key => $val) {
                if ($val === '') {
                    unset($values[$key]);
                } elseif (\is_array($val)) {
                    foreach ($val as $inner_key => $inner_value) {
                        if ($inner_value === '') {
                            unset($val[$inner_key]);
                        }
                    }
                }

                $values[$key] = $val;
            }
        }

        return $values;
    }

    public static function typeList($value, array $params, array $field)
    {
        if (!\is_array($value)) {
            return false;
        }

        if (isset($field['fields'])) {
            foreach ($value as $key => $item) {
                foreach ($field['fields'] as $subKey => $subField) {
                    $subKey = trim($subKey, '.');
                    $subValue = $item[$subKey] ?? null;
                    self::validate($subValue, $subField);
                }
            }
        }

        return true;
    }

    protected static function filterList($value, array $params, array $field)
    {
        return (array) $value;
    }

    public static function filterYaml($value, $params)
    {
        if (!\is_string($value)) {
            return $value;
        }

        return (array) Yaml::parse($value);

    }

    /**
     * Custom input: ignore (will not validate)
     *
     * @param  mixed  $value   Value to be validated.
     * @param  array  $params  Validation parameters.
     * @param  array  $field   Blueprint for the field.
     * @return bool   True if validation succeeded.
     */
    public static function typeIgnore($value, array $params, array $field)
    {
        return true;
    }

    public static function filterIgnore($value, array $params, array $field)
    {
        return $value;
    }

    /**
     * Input value which can be ignored.
     *
     * @param  mixed  $value   Value to be validated.
     * @param  array  $params  Validation parameters.
     * @param  array  $field   Blueprint for the field.
     * @return bool   True if validation succeeded.
     */
    public static function typeUnset($value, array $params, array $field)
    {
        return true;
    }

    public static function filterUnset($value, array $params, array $field)
    {
        return null;
    }

    // HTML5 attributes (min, max and range are handled inside the types)

    public static function validateRequired($value, $params)
    {
        if (is_scalar($value)) {
            return (bool) $params !== true || $value !== '';
        }

        return (bool) $params !== true || !empty($value);
    }

    public static function validatePattern($value, $params)
    {
        return (bool) preg_match("`^{$params}$`u", $value);
    }


    // Internal types

    public static function validateAlpha($value, $params)
    {
        return ctype_alpha($value);
    }

    public static function validateAlnum($value, $params)
    {
        return ctype_alnum($value);
    }

    public static function typeBool($value, $params)
    {
        return \is_bool($value) || $value == 1 || $value == 0;
    }

    public static function validateBool($value, $params)
    {
        return \is_bool($value) || $value == 1 || $value == 0;
    }

    protected static function filterBool($value, $params)
    {
        return (bool) $value;
    }

    public static function validateDigit($value, $params)
    {
        return ctype_digit($value);
    }

    public static function validateFloat($value, $params)
    {
        return \is_float(filter_var($value, FILTER_VALIDATE_FLOAT));
    }

    protected static function filterFloat($value, $params)
    {
        return (float) $value;
    }

    public static function validateHex($value, $params)
    {
        return ctype_xdigit($value);
    }

    public static function validateInt($value, $params)
    {
        return is_numeric($value) && (int)$value == $value;
    }

    protected static function filterInt($value, $params)
    {
        return (int)$value;
    }

    public static function validateArray($value, $params)
    {
        return \is_array($value) || ($value instanceof \ArrayAccess && $value instanceof \Traversable && $value instanceof \Countable);
    }

    public static function filterItem_List($value, $params)
    {
        return array_values(array_filter($value, function($v) { return !empty($v); } ));
    }

    public static function validateJson($value, $params)
    {
        return (bool) (@json_decode($value));
    }
}
