<?php

/**
 * @package    Grav\Common\Data
 *
 * @copyright  Copyright (c) 2015 - 2025 Trilby Media, LLC. All rights reserved.
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
     * @param array $field
     * @return array
     */
    public static function validate(mixed $value, array $field)
    {
        if (!isset($field['type'])) {
            $field['type'] = 'text';
        }

        $validate = (array)($field['validate'] ?? null);
        $validate_type = $validate['type'] ?? null;
        $required = $validate['required'] ?? false;
        $type = $validate_type ?? $field['type'];

        $required = $required && ($validate_type !== 'ignore');

        // If value isn't required, we will stop validation if empty value is given.
        if ($required !== true && ($value === null || $value === '' || empty($value) || (($field['type'] === 'checkbox' || $field['type'] === 'switch') && $value == false))) {
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

        $success = method_exists(self::class, $method) ? self::$method($value, $validate, $field) : true;
        if (!$success) {
            $messages[$field['name']][] = $message;
        }

        // Check individual rules.
        foreach ($validate as $rule => $params) {
            $method = 'validate' . ucfirst(str_replace('-', '_', $rule));

            if (method_exists(self::class, $method)) {
                $success = self::$method($value, $params);

                if (!$success) {
                    $messages[$field['name']][] = $message;
                }
            }
        }

        return $messages;
    }

    /**
     * @param array $field
     * @return array
     */
    public static function checkSafety(mixed $value, array $field)
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

        $name = ucfirst((string) ($field['label'] ?? $field['name'] ?? 'UNKNOWN'));

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
    public static function authorize($action, ?UserInterface $user = null)
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
     * @param  array  $field
     * @return mixed  Filtered value.
     */
    public static function filter(mixed $value, array $field)
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

        if (!method_exists(self::class, $method)) {
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
    public static function typeText(mixed $value, array $params, array $field)
    {
        if (!is_string($value) && !is_numeric($value)) {
            return false;
        }

        $value = (string)$value;

        if (!empty($params['trim'])) {
            $value = trim($value);
        }

        $value = preg_replace("/\r\n|\r/um", "\n", $value);
        $len = mb_strlen((string) $value);

        $min = (int)($params['min'] ?? 0);
        if ($min && $len < $min) {
            return false;
        }

        $multiline = isset($params['multiline']) && $params['multiline'];

        $max = (int)($params['max'] ?? ($multiline ? 65536 : 2048));
        if ($max && $len > $max) {
            return false;
        }

        $step = (int)($params['step'] ?? 0);
        if ($step && ($len - $min) % $step === 0) {
            return false;
        }

        if (!$multiline && preg_match('/\R/um', (string) $value)) {
            return false;
        }

        return true;
    }

    /**
     * @param array $params
     * @param array $field
     * @return string
     */
    protected static function filterText(mixed $value, array $params, array $field)
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
     * @param array $params
     * @param array $field
     * @return string|null
     */
    protected static function filterCheckbox(mixed $value, array $params, array $field)
    {
        $value = (string)$value;
        $field_value = (string)($field['value'] ?? '1');

        return $value === $field_value ? $value : null;
    }

    /**
     * @param array $params
     * @param array $field
     * @return array|array[]|false|string[]
     */
    protected static function filterCommaList(mixed $value, array $params, array $field)
    {
        return is_array($value) ? $value : preg_split('/\s*,\s*/', (string) $value, -1, PREG_SPLIT_NO_EMPTY);
    }

    /**
     * @param array $params
     * @param array $field
     * @return bool
     */
    public static function typeCommaList(mixed $value, array $params, array $field)
    {
        if (!isset($params['max'])) {
            $params['max'] = 2048;
        }

        return is_array($value) ? true : self::typeText($value, $params, $field);
    }

    /**
     * @param array $params
     * @param array $field
     * @return array|array[]|false|string[]
     */
    protected static function filterLines(mixed $value, array $params, array $field)
    {
        return is_array($value) ? $value : preg_split('/\s*[\r\n]+\s*/', (string) $value, -1, PREG_SPLIT_NO_EMPTY);
    }

    /**
     * @param array $params
     * @return string
     */
    protected static function filterLower(mixed $value, array $params)
    {
        return mb_strtolower((string) $value);
    }

    /**
     * @param array $params
     * @return string
     */
    protected static function filterUpper(mixed $value, array $params)
    {
        return mb_strtoupper((string) $value);
    }


    /**
     * HTML5 input: textarea
     *
     * @param  mixed  $value   Value to be validated.
     * @param  array  $params  Validation parameters.
     * @param  array  $field   Blueprint for the field.
     * @return bool   True if validation succeeded.
     */
    public static function typeTextarea(mixed $value, array $params, array $field)
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
    public static function typePassword(mixed $value, array $params, array $field)
    {
        if (!isset($params['max'])) {
            $params['max'] = 256;
        }

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
    public static function typeHidden(mixed $value, array $params, array $field)
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
    public static function typeCheckboxes(mixed $value, array $params, array $field)
    {
        // Set multiple: true so checkboxes can easily use min/max counts to control number of options required
        $field['multiple'] = true;

        return self::typeArray((array) $value, $params, $field);
    }

    /**
     * @param array $params
     * @param array $field
     * @return array|null
     */
    protected static function filterCheckboxes(mixed $value, array $params, array $field)
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
    public static function typeCheckbox(mixed $value, array $params, array $field)
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
    public static function typeRadio(mixed $value, array $params, array $field)
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
    public static function typeToggle(mixed $value, array $params, array $field)
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
    public static function typeFile(mixed $value, array $params, array $field)
    {
        return self::typeArray((array)$value, $params, $field);
    }

    /**
     * @param array $params
     * @param array $field
     * @return array
     */
    protected static function filterFile(mixed $value, array $params, array $field)
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
    public static function typeSelect(mixed $value, array $params, array $field)
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
    public static function typeNumber(mixed $value, array $params, array $field)
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
            $pos = round($pos, 10);
            return is_int(static::filterNumber($pos, $params, $field));
        }

        return true;
    }

    /**
     * @param array $params
     * @param array $field
     * @return float|int
     */
    protected static function filterNumber(mixed $value, array $params, array $field)
    {
        return (string)(int)$value !== (string)(float)$value ? (float)$value : (int)$value;
    }

    /**
     * @param array $params
     * @param array $field
     * @return string
     */
    protected static function filterDateTime(mixed $value, array $params, array $field)
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
    public static function typeRange(mixed $value, array $params, array $field)
    {
        return self::typeNumber($value, $params, $field);
    }

    /**
     * @param array $params
     * @param array $field
     * @return float|int
     */
    protected static function filterRange(mixed $value, array $params, array $field)
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
    public static function typeColor(mixed $value, array $params, array $field)
    {
        return (bool)preg_match('/^\#[0-9a-fA-F]{3}[0-9a-fA-F]{3}?$/u', (string) $value);
    }

    /**
     * HTML5 input: email
     *
     * @param  mixed  $value   Value to be validated.
     * @param  array  $params  Validation parameters.
     * @param  array  $field   Blueprint for the field.
     * @return bool   True if validation succeeded.
     */
    public static function typeEmail(mixed $value, array $params, array $field)
    {
        if (empty($value)) {
            return false;
        }

        if (!isset($params['max'])) {
            $params['max'] = 320;
        }

        $values = !is_array($value) ? explode(',', (string) preg_replace('/\s+/', '', (string) $value)) : $value;

        foreach ($values as $val) {
            if (!(self::typeText($val, $params, $field) && strpos((string) $val, '@', 1))) {
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
    public static function typeUrl(mixed $value, array $params, array $field)
    {
        if (!isset($params['max'])) {
            $params['max'] = 2048;
        }

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
    public static function typeDatetime(mixed $value, array $params, array $field)
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
    public static function typeDatetimeLocal(mixed $value, array $params, array $field)
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
    public static function typeDate(mixed $value, array $params, array $field)
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
    public static function typeTime(mixed $value, array $params, array $field)
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
    public static function typeMonth(mixed $value, array $params, array $field)
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
    public static function typeWeek(mixed $value, array $params, array $field)
    {
        if (!isset($params['format']) && !preg_match('/^\d{4}-W\d{2}$/u', (string) $value)) {
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
    public static function typeArray(mixed $value, array $params, array $field)
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
     * @param array $params
     * @param array $field
     * @return array|null
     */
    protected static function filterFlatten_array(mixed $value, $params, $field)
    {
        $value = static::filterArray($value, $params, $field);

        return is_array($value) ? Utils::arrayUnflattenDotNotation($value) : null;
    }

    /**
     * @param array $params
     * @param array $field
     * @return array|null
     */
    protected static function filterArray(mixed $value, $params, $field)
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
                    $values[$key] =  trim((string) $val);
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
                $result = match ($params['key_type']) {
                    'int' => is_int($key),
                    'string' => is_string($key),
                    default => false,
                };
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
                            $val = trim((string) $val);
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
     * @param array $params
     * @param array $field
     * @return bool
     */
    public static function typeList(mixed $value, array $params, array $field)
    {
        if (!is_array($value)) {
            return false;
        }

        if (isset($field['fields'])) {
            foreach ($value as $key => $item) {
                foreach ($field['fields'] as $subKey => $subField) {
                    $subKey = trim((string) $subKey, '.');
                    $subValue = $item[$subKey] ?? null;
                    self::validate($subValue, $subField);
                }
            }
        }

        return true;
    }

    /**
     * @param array $params
     * @param array $field
     * @return array
     */
    protected static function filterList(mixed $value, array $params, array $field)
    {
        return (array) $value;
    }

    /**
     * @param array $params
     * @return array
     */
    public static function filterYaml(mixed $value, $params)
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
    public static function typeIgnore(mixed $value, array $params, array $field)
    {
        return true;
    }

    /**
     * @param array $params
     * @param array $field
     * @return mixed
     */
    public static function filterIgnore(mixed $value, array $params, array $field)
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
    public static function typeUnset(mixed $value, array $params, array $field)
    {
        return true;
    }

    /**
     * @param array $params
     * @param array $field
     * @return null
     */
    public static function filterUnset(mixed $value, array $params, array $field)
    {
        return null;
    }

    // HTML5 attributes (min, max and range are handled inside the types)
    /**
     * @param bool $params
     * @return bool
     */
    public static function validateRequired(mixed $value, $params)
    {
        if (is_scalar($value)) {
            return (bool) $params !== true || $value !== '';
        }

        return (bool) $params !== true || !empty($value);
    }

    /**
     * @param string $params
     * @return bool
     */
    public static function validatePattern(mixed $value, $params)
    {
        return (bool) preg_match("`^{$params}$`u", (string) $value);
    }

    // Internal types
    /**
     * @return bool
     */
    public static function validateAlpha(mixed $value, mixed $params)
    {
        return ctype_alpha((string) $value);
    }

    /**
     * @return bool
     */
    public static function validateAlnum(mixed $value, mixed $params)
    {
        return ctype_alnum((string) $value);
    }

    /**
     * @return bool
     */
    public static function typeBool(mixed $value, mixed $params)
    {
        return is_bool($value) || $value == 1 || $value == 0;
    }

    /**
     * @return bool
     */
    public static function validateBool(mixed $value, mixed $params)
    {
        return is_bool($value) || $value == 1 || $value == 0;
    }

    /**
     * @return bool
     */
    protected static function filterBool(mixed $value, mixed $params)
    {
        return (bool) $value;
    }

    /**
     * @return bool
     */
    public static function validateDigit(mixed $value, mixed $params)
    {
        return ctype_digit((string) $value);
    }

    /**
     * @return bool
     */
    public static function validateFloat(mixed $value, mixed $params)
    {
        return is_float(filter_var($value, FILTER_VALIDATE_FLOAT));
    }

    /**
     * @return float
     */
    protected static function filterFloat(mixed $value, mixed $params)
    {
        return (float) $value;
    }

    /**
     * @return bool
     */
    public static function validateHex(mixed $value, mixed $params)
    {
        return ctype_xdigit((string) $value);
    }

    /**
     * Custom input: int
     *
     * @param  mixed  $value   Value to be validated.
     * @param  array  $params  Validation parameters.
     * @param  array  $field   Blueprint for the field.
     * @return bool   True if validation succeeded.
     */
    public static function typeInt(mixed $value, array $params, array $field)
    {
        $params['step'] = max(1, (int)($params['step'] ?? 0));

        return self::typeNumber($value, $params, $field);
    }

    /**
     * @return bool
     */
    public static function validateInt(mixed $value, mixed $params)
    {
        return is_numeric($value) && (int)$value == $value;
    }

    /**
     * @return int
     */
    protected static function filterInt(mixed $value, mixed $params)
    {
        return (int)$value;
    }

    /**
     * @return bool
     */
    public static function validateArray(mixed $value, mixed $params)
    {
        return is_array($value) || ($value instanceof ArrayAccess && $value instanceof Traversable && $value instanceof Countable);
    }

    /**
     * @return array
     */
    public static function filterItem_List(mixed $value, mixed $params)
    {
        return array_values(array_filter($value, static fn($v) => !empty($v)));
    }

    /**
     * @return bool
     */
    public static function validateJson(mixed $value, mixed $params)
    {
        return (bool) (@json_decode((string) $value));
    }
}
