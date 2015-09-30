<?php
namespace Grav\Common\Data;
use Grav\Common\GravTrait;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Parser;

/**
 * Data validation.
 *
 * @author RocketTheme
 * @license MIT
 */
class Validation
{
    use GravTrait;

    /**
     * Validate value against a blueprint field definition.
     *
     * @param mixed $value
     * @param array $field
     * @throws \RuntimeException
     */
    public static function validate($value, array $field)
    {
        $validate = isset($field['validate']) ? (array) $field['validate'] : array();

        // If value isn't required, we will stop validation if empty value is given.
        if (empty($validate['required']) && ($value === null || $value === '')) {
            return;
        }

        // Get language class
        $language = self::getGrav()['language'];

        // Validate type with fallback type text.
        $type = (string) isset($field['validate']['type']) ? $field['validate']['type'] : $field['type'];
        $method = 'type'.strtr($type, '-', '_');
        $name = ucfirst($field['label'] ? $field['label'] : $field['name']);
        $message = (string) isset($field['validate']['message']) ? $field['validate']['message'] : 'Invalid input in "' . $language->translate($name) . '""';

        if (method_exists(__CLASS__, $method)) {
            $success = self::$method($value, $validate, $field);
        } else {
            $success = self::typeText($value, $validate, $field);
        }
        if (!$success) {
            throw new \RuntimeException($message);
        }

        // Check individual rules
        foreach ($validate as $rule => $params) {
            $method = 'validate'.strtr($rule, '-', '_');
            if (method_exists(__CLASS__, $method)) {
                $success = self::$method($value, $params);

                if (!$success) {
                    throw new \RuntimeException($message);
                }
            }
        }
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
        $validate = isset($field['validate']) ? (array) $field['validate'] : array();

        // If value isn't required, we will return null if empty value is given.
        if (empty($validate['required']) && ($value === null || $value === '')) {
            return null;
        }

        // if this is a YAML field, simply parse it and return the value
        if (isset($field['yaml']) && $field['yaml'] === true) {
            try {
                $yaml = new Parser();
                return $yaml->parse($value);
            } catch (ParseException $e) {
                throw new \RuntimeException($e->getMessage());
            }
        }

        // Validate type with fallback type text.
        $type = (string) isset($field['validate']['type']) ? $field['validate']['type'] : $field['type'];
        $method = 'filter'.strtr($type, '-', '_');
        if (method_exists(__CLASS__, $method)) {
            $value = self::$method($value, $validate, $field);
        } else {
            $value = self::filterText($value, $validate, $field);
        }

        return $value;
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
        if (!is_string($value)) {
            return false;
        }

        if (isset($params['min']) && strlen($value) < $params['min']) {
            return false;
        }

        if (isset($params['max']) && strlen($value) > $params['max']) {
            return false;
        }

        $min = isset($params['min']) ? $params['min'] : 0;
        if (isset($params['step']) && (strlen($value) - $min) % $params['step'] == 0) {
            return false;
        }

        if ((!isset($params['multiline']) || !$params['multiline']) && preg_match('/\R/um', $value)) {
            return false;
        }

        return true;
    }

    protected static function filterText($value, array $params, array $field)
    {
        return (string) $value;
    }

    protected static function filterCommaList($value, array $params, array $field)
    {
        return is_array($value) ? $value : preg_split('/\s*,\s*/', $value, -1, PREG_SPLIT_NO_EMPTY);
    }

    protected static function typeCommaList($value, array $params, array $field)
    {
        return is_array($value) ? true : self::typeText($value, $params, $field);
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
        $value = (string) $value;

        if (!isset($field['value'])) {
            $field['value'] = 1;
        }
        if ($value && $value != $field['value']) {
            return false;
        }

        return true;
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
        return self::typeArray((array) $value, $params, $field);
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

        $min = isset($params['min']) ? $params['min'] : 0;
        if (isset($params['step']) && fmod($value - $min, $params['step']) == 0) {
            return false;
        }

        return true;
    }

    protected static function filterNumber($value, array $params, array $field)
    {
        return (int) $value;
    }

    protected static function filterDateTime($value, array $params, array $field)
    {
        $format = self::getGrav()['config']->get('system.pages.dateformat.default');
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
        return self::typeText($value, $params, $field) && filter_var($value, FILTER_VALIDATE_EMAIL);
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
        } elseif (!is_string($value)) {
            return false;
        } elseif (!isset($params['format'])) {
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
        $params = array($params);
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
        $params = array($params);
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
        $params = array($params);
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

            $min = isset($params['min']) ? $params['min'] : 0;
            if (isset($params['step']) && (count($value) - $min) % $params['step'] == 0) {
                return false;
            }
        }

        $options = isset($field['options']) ? array_keys($field['options']) : array();
        $values = isset($field['use']) && $field['use'] == 'keys' ? array_keys($value) : $value;
        if ($options && array_diff($values, $options)) {
            return false;
        }

        return true;
    }

    protected static function filterArray($value, $params, $field)
    {
        $values = (array) $value;
        $options = isset($field['options']) ? array_keys($field['options']) : array();
        $multi = isset($field['multiple']) ? $field['multiple'] : false;

        if ($options) {
            $useKey = isset($field['use']) && $field['use'] == 'keys';
            foreach ($values as $key => $value) {
                $values[$key] = $useKey ? (bool) $value : $value;
            }
        }

        if ($multi) {
            foreach ($values as $key => $value) {
                if (is_array($value)) {
                    $value = implode(',', $value);
                }

                $values[$key] =  array_map('trim', explode(',', $value));
            }
        }

        return $values;
    }

    public static function typeList($value, array $params, array $field)
    {
        if (!is_array($value)) {
            return false;
        }

        if (isset($field['fields'])) {
            foreach ($value as $key => $item) {
                foreach ($field['fields'] as $subKey => $subField) {
                    $subKey = trim($subKey, '.');
                    $subValue = isset($item[$subKey]) ? $item[$subKey] : null;
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

    // HTML5 attributes (min, max and range are handled inside the types)

    public static function validateRequired($value, $params)
    {
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
        return is_bool($value) || $value == 1 || $value == 0;
    }

    public static function validateBool($value, $params)
    {
        return is_bool($value) || $value == 1 || $value == 0;
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
        return is_float(filter_var($value, FILTER_VALIDATE_FLOAT));
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
        return is_numeric($value) && (int) $value == $value;
    }

    protected static function filterInt($value, $params)
    {
        return (int) $value;
    }

    public static function validateArray($value, $params)
    {
        return is_array($value) || ($value instanceof \ArrayAccess
            && $value instanceof \Traversable
            && $value instanceof \Countable);
    }

    public static function validateJson($value, $params)
    {
        return (bool) (json_decode($value));
    }
}
