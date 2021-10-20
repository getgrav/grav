<?php

/**
 * @package    Grav\Common\Data
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Data;

use ArrayAccess;
use Countable;
use DateTime;
use Grav\Common\Config\Config;
use Grav\Common\Grav;
use Grav\Common\Language\Language;
use Grav\Common\Security;
use Grav\Common\User\Interfaces\UserInterface;
use Grav\Common\Utils;
use Grav\Common\Yaml;
use Grav\Framework\Flex\Interfaces\FlexObjectInterface;
use Traversable;
use function count;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;

/**
 * Class Validation
 * @package Grav\Common\Data
 */
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
            : $language->translate('GRAV.FORM.INVALID_INPUT') . ' "' . $language->translate($name) . '"';


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
     * @param mixed $value
     * @param array $field
     * @return array
     */
    public static function checkSafety($value, array $field)
    {
        $messages = [];

        $type = $field['validate']['type'] ?? $field['type'] ?? 'text';
        $options = $field['xss_check'] ?? [];
        if ($options === false || $type === 'unset') {
            return $messages;
        }
        if (!is_array($options)) {
            $options = [];
        }

        $name = ucfirst($field['label'] ?? $field['name'] ?? 'UNKNOWN');

        /** @var UserInterface $user */
        $user = Grav::instance()['user'] ?? null;
        /** @var Config $config */
        $config = Grav::instance()['config'];

        $xss_whitelist = $config->get('security.xss_whitelist', 'admin.super');

        // Get language class.
        /** @var Language $language */
        $language = Grav::instance()['language'];

        if (!static::authorize($xss_whitelist, $user)) {
            $defaults = Security::getXssDefaults();
            $options += $defaults;
            $options['enabled_rules'] += $defaults['enabled_rules'];
            if (!empty($options['safe_protocols'])) {
                $options['invalid_protocols'] = array_diff($options['invalid_protocols'], $options['safe_protocols']);
            }
            if (!empty($options['safe_tags'])) {
                $options['dangerous_tags'] = array_diff($options['dangerous_tags'], $options['safe_tags']);
            }

            if (is_string($value)) {
                $violation = Security::detectXss($value, $options);
                if ($violation) {
                    $messages[$name][] = $language->translate(['GRAV.FORM.XSS_ISSUES', $language->translate($name)], null, true);
                }
            } elseif (is_array($value)) {
                $violations = Security::detectXssFromArray($value, "{$name}.", $options);
                if ($violations) {
                    $messages[$name][] = $language->translate(['GRAV.FORM.XSS_ISSUES', $language->translate($name)], null, true);
                }
            }
        }

        return $messages;
    }

    /**
     * Checks user authorisation to the action.
     *
     * @param  string|string[] $action
     * @param  UserInterface|null $user
     * @return bool
     */
    public static function authorize($action, UserInterface $user = null)
    {
        if (!$user) {
            return false;
        }

        $action = (array)$action;
        foreach ($action as $a) {
            // Ignore 'admin.super' if it's not the only value to be checked.
            if ($a === 'admin.super' && count($action) > 1 && $user instanceof FlexObjectInterface) {
                continue;
            }

            if ($user->authorize($a)) {
                return true;
            }
        }

        return false;
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
        if (!is_string($value) && !is_numeric($value)) {
            return false;
        }

        $value = (string)$value;

        if (!empty($params['trim'])) {
            $value = trim($value);
        }

        $value = preg_replace("/\r\n|\r/um", "\n", $value);
        $len = mb_strlen($value);

        $min = (int)($params['min'] ?? 0);
        if ($min && $len < $min) {
            return false;
        }

        $max = (int)($params['max'] ?? 0);
        if ($max && $len > $max) {
            return false;
        }

        $step = (int)($params['step'] ?? 0);
        if ($step && ($len - $min) % $step === 0) {
            return false;
        }

        if ((!isset($params['multiline']) || !$params['multiline']) && preg_match('/\R/um', $value)) {
            return false;
        }

        return true;
    }

    /**
     * @param mixed $value
     * @param array $params
     * @param array $field
     * @return string
     */
    protected static function filterText($value, array $params, array $field)
    {
        if (!is_string($value) && !is_numeric($value)) {
            return '';
        }

        $value = (string)$value;

        if (!empty($params['trim'])) {
            $value = trim($value);
        }

        return preg_replace("/\r\n|\r/um", "\n", $value);
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

    /**
     * @param mixed $value
     * @param array $params
     * @param array $field
     * @return array|array[]|false|string[]
     */
    protected static function filterCommaList($value, array $params, array $field)
    {
        return is_array($value) ? $value : preg_split('/\s*,\s*/', $value, -1, PREG_SPLIT_NO_EMPTY);
    }

    /**
     * @param mixed $value
     * @param array $params
     * @param array $field
     * @return bool
     */
    public static function typeCommaList($value, array $params, array $field)
    {
        return is_array($value) ? true : self::typeText($value, $params, $field);
    }

    /**
     * @param mixed $value
     * @param array $params
     * @param array $field
     * @return array|array[]|false|string[]
     */
    protected static function filterLines($value, array $params, array $field)
    {
        return is_array($value) ? $value : preg_split('/\s*[\r\n]+\s*/', $value, -1, PREG_SPLIT_NO_EMPTY);
    }

    /**
     * @param mixed $value
     * @param array $params
     * @return string
     */
    protected static function filterLower($value, array $params)
    {
        return mb_strtolower($value);
    }

    /**
     * @param mixed $value
     * @param array $params
     * @return string
     */
    protected static function filterUpper($value, array $params)
    {
        return mb_strtoupper($value);
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

    /**
     * @param mixed $value
     * @param array $params
     * @param array $field
     * @return array|null
     */
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
        if (is_bool($value)) {
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

    /**
     * @param mixed $value
     * @param array $params
     * @param array $field
     * @return array
     */
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

        $value = (float)$value;

        $min = 0;
        if (isset($params['min'])) {
            $min = (float)$params['min'];
            if ($value < $min) {
                return false;
            }
        }

        if (isset($params['max'])) {
            $max = (float)$params['max'];
            if ($value > $max) {
                return false;
            }
        }

        if (isset($params['step'])) {
            $step = (float)$params['step'];
            // Count of how many steps we are above/below the minimum value.
            $pos = ($value - $min) / $step;

            return is_int(static::filterNumber($pos, $params, $field));
        }

        return true;
    }

    /**
     * @param mixed $value
     * @param array $params
     * @param array $field
     * @return float|int
     */
    protected static function filterNumber($value, array $params, array $field)
    {
        return (string)(int)$value !== (string)(float)$value ? (float)$value : (int)$value;
    }

    /**
     * @param mixed $value
     * @param array $params
     * @param array $field
     * @return string
     */
    protected static function filterDateTime($value, array $params, array $field)
    {
        $format = Grav::instance()['config']->get('system.pages.dateformat.default');
        if ($format) {
            $converted = new DateTime($value);
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

    /**
     * @param mixed $value
     * @param array $params
     * @param array $field
     * @return float|int
     */
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
        return (bool)preg_match('/^\#[0-9a-fA-F]{3}[0-9a-fA-F]{3}?$/u', $value);
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
        $values = !is_array($value) ? explode(',', preg_replace('/\s+/', '', $value)) : $value;

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
        if ($value instanceof DateTime) {
            return true;
        }
        if (!is_string($value)) {
            return false;
        }
        if (!isset($params['format'])) {
            return false !== strtotime($value);
        }

        $dateFromFormat = DateTime::createFromFormat($params['format'], $value);

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
        if (!is_array($value)) {
            return false;
        }

        if (isset($field['multiple'])) {
            if (isset($params['min']) && count($value) < $params['min']) {
                return false;
            }

            if (isset($params['max']) && count($value) > $params['max']) {
                return false;
            }

            $min = $params['min'] ?? 0;
            if (isset($params['step']) && (count($value) - $min) % $params['step'] === 0) {
                return false;
            }
        }

        // If creating new values is allowed, no further checks are needed.
        $validateOptions = $field['validate']['options'] ?? null;
        if (!empty($field['selectize']['create']) || $validateOptions === 'ignore') {
            return true;
        }

        $options = $field['options'] ?? [];
        $use = $field['use'] ?? 'values';

        if ($validateOptions) {
            // Use custom options structure.
            foreach ($options as &$option) {
                $option = $option[$validateOptions] ?? null;
            }
            unset($option);
            $options = array_values($options);
        } elseif (empty($field['selectize']) || empty($field['multiple'])) {
            $options = array_keys($options);
        }
        if ($use === 'keys') {
            $value = array_keys($value);
        }

        return !($options && array_diff($value, $options));
    }

    /**
     * @param mixed $value
     * @param array $params
     * @param array $field
     * @return array|null
     */
    protected static function filterFlatten_array($value, $params, $field)
    {
        $value = static::filterArray($value, $params, $field);

        return Utils::arrayUnflattenDotNotation($value);
    }

    /**
     * @param mixed $value
     * @param array $params
     * @param array $field
     * @return array|null
     */
    protected static function filterArray($value, $params, $field)
    {
        $values = (array) $value;
        $options = isset($field['options']) ? array_keys($field['options']) : [];
        $multi = $field['multiple'] ?? false;

        if (count($values) === 1 && isset($values[0]) && $values[0] === '') {
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
                if (is_array($val)) {
                    $val = implode(',', $val);
                    $values[$key] =  array_map('trim', explode(',', $val));
                } else {
                    $values[$key] =  trim($val);
                }
            }
        }

        $ignoreEmpty = isset($field['ignore_empty']) && Utils::isPositive($field['ignore_empty']);
        $valueType = $params['value_type'] ?? null;
        $keyType = $params['key_type'] ?? null;
        if ($ignoreEmpty || $valueType || $keyType) {
            $values = static::arrayFilterRecurse($values, ['value_type' => $valueType, 'key_type' => $keyType, 'ignore_empty' => $ignoreEmpty]);
        }

        return $values;
    }

    /**
     * @param array $values
     * @param array $params
     * @return array
     */
    protected static function arrayFilterRecurse(array $values, array $params): array
    {
        foreach ($values as $key => &$val) {
            if ($params['key_type']) {
                switch ($params['key_type']) {
                    case 'int':
                        $result = is_int($key);
                        break;
                    case 'string':
                        $result = is_string($key);
                        break;
                    default:
                        $result = false;
                }
                if (!$result) {
                    unset($values[$key]);
                }
            }
            if (is_array($val)) {
                $val = static::arrayFilterRecurse($val, $params);
                if ($params['ignore_empty'] && empty($val)) {
                    unset($values[$key]);
                }
            } else {
                if ($params['value_type'] && $val !== '' && $val !== null) {
                    switch ($params['value_type']) {
                        case 'bool':
                            if (Utils::isPositive($val)) {
                                $val = true;
                            } elseif (Utils::isNegative($val)) {
                                $val = false;
                            } else {
                                // Ignore invalid bool values.
                                $val = null;
                            }
                            break;
                        case 'int':
                            $val = (int)$val;
                            break;
                        case 'float':
                            $val = (float)$val;
                            break;
                        case 'string':
                            $val = (string)$val;
                            break;
                        case 'trim':
                            $val = trim($val);
                            break;
                    }
                }

                if ($params['ignore_empty'] && ($val === '' || $val === null)) {
                    unset($values[$key]);
                }
            }
        }

        return $values;
    }

    /**
     * @param mixed $value
     * @param array $params
     * @param array $field
     * @return bool
     */
    public static function typeList($value, array $params, array $field)
    {
        if (!is_array($value)) {
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

    /**
     * @param mixed $value
     * @param array $params
     * @param array $field
     * @return array
     */
    protected static function filterList($value, array $params, array $field)
    {
        return (array) $value;
    }

    /**
     * @param mixed $value
     * @param array $params
     * @return array
     */
    public static function filterYaml($value, $params)
    {
        if (!is_string($value)) {
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

    /**
     * @param mixed $value
     * @param array $params
     * @param array $field
     * @return mixed
     */
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

    /**
     * @param mixed $value
     * @param array $params
     * @param array $field
     * @return null
     */
    public static function filterUnset($value, array $params, array $field)
    {
        return null;
    }

    // HTML5 attributes (min, max and range are handled inside the types)

    /**
     * @param mixed $value
     * @param bool $params
     * @return bool
     */
    public static function validateRequired($value, $params)
    {
        if (is_scalar($value)) {
            return (bool) $params !== true || $value !== '';
        }

        return (bool) $params !== true || !empty($value);
    }

    /**
     * @param mixed $value
     * @param string $params
     * @return bool
     */
    public static function validatePattern($value, $params)
    {
        return (bool) preg_match("`^{$params}$`u", $value);
    }

    // Internal types

    /**
     * @param mixed $value
     * @param mixed $params
     * @return bool
     */
    public static function validateAlpha($value, $params)
    {
        return ctype_alpha($value);
    }

    /**
     * @param mixed $value
     * @param mixed $params
     * @return bool
     */
    public static function validateAlnum($value, $params)
    {
        return ctype_alnum($value);
    }

    /**
     * @param mixed $value
     * @param mixed $params
     * @return bool
     */
    public static function typeBool($value, $params)
    {
        return is_bool($value) || $value == 1 || $value == 0;
    }

    /**
     * @param mixed $value
     * @param mixed $params
     * @return bool
     */
    public static function validateBool($value, $params)
    {
        return is_bool($value) || $value == 1 || $value == 0;
    }

    /**
     * @param mixed $value
     * @param mixed $params
     * @return bool
     */
    protected static function filterBool($value, $params)
    {
        return (bool) $value;
    }

    /**
     * @param mixed $value
     * @param mixed $params
     * @return bool
     */
    public static function validateDigit($value, $params)
    {
        return ctype_digit($value);
    }

    /**
     * @param mixed $value
     * @param mixed $params
     * @return bool
     */
    public static function validateFloat($value, $params)
    {
        return is_float(filter_var($value, FILTER_VALIDATE_FLOAT));
    }

    /**
     * @param mixed $value
     * @param mixed $params
     * @return float
     */
    protected static function filterFloat($value, $params)
    {
        return (float) $value;
    }

    /**
     * @param mixed $value
     * @param mixed $params
     * @return bool
     */
    public static function validateHex($value, $params)
    {
        return ctype_xdigit($value);
    }

    /**
     * Custom input: int
     *
     * @param  mixed  $value   Value to be validated.
     * @param  array  $params  Validation parameters.
     * @param  array  $field   Blueprint for the field.
     * @return bool   True if validation succeeded.
     */
    public static function typeInt($value, array $params, array $field)
    {
        $params['step'] = max(1, (int)($params['step'] ?? 0));

        return self::typeNumber($value, $params, $field);
    }

    /**
     * @param mixed $value
     * @param mixed $params
     * @return bool
     */
    public static function validateInt($value, $params)
    {
        return is_numeric($value) && (int)$value == $value;
    }

    /**
     * @param mixed $value
     * @param mixed $params
     * @return int
     */
    protected static function filterInt($value, $params)
    {
        return (int)$value;
    }

    /**
     * @param mixed $value
     * @param mixed $params
     * @return bool
     */
    public static function validateArray($value, $params)
    {
        return is_array($value) || ($value instanceof ArrayAccess && $value instanceof Traversable && $value instanceof Countable);
    }

    /**
     * @param mixed $value
     * @param mixed $params
     * @return array
     */
    public static function filterItem_List($value, $params)
    {
        return array_values(array_filter($value, static function ($v) {
            return !empty($v);
        }));
    }

    /**
     * @param mixed $value
     * @param mixed $params
     * @return bool
     */
    public static function validateJson($value, $params)
    {
        return (bool) (@json_decode($value));
    }
}
