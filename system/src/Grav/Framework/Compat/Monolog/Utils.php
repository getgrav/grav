<?php

/**
 * Backport of Monolog\Utils providing DEFAULT_JSON_FLAGS for older Monolog versions.
 *
 * This is a trimmed copy of the Monolog 1.x Utils class with a compatible constant so
 * that Grav 1.7 can interoperate with code targeting Monolog 3.
 */

namespace Grav\Framework\Compat\Monolog;

if (!class_exists(\Monolog\Utils::class, false)) {
    class Utils
    {
        public const DEFAULT_JSON_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

        /**
         * @internal
         */
        public static function getClass($object)
        {
            $class = \get_class($object);

            return 'c' === $class[0] && 0 === strpos($class, "class@anonymous\0") ? get_parent_class($class).'@anonymous' : $class;
        }

        /**
         * Makes sure if a relative path is passed in it is turned into an absolute path
         *
         * @param string $streamUrl stream URL or path without protocol
         *
         * @return string
         */
        public static function canonicalizePath($streamUrl)
        {
            $prefix = '';
            if ('file://' === substr($streamUrl, 0, 7)) {
                $streamUrl = substr($streamUrl, 7);
                $prefix = 'file://';
            }

            if (false !== strpos($streamUrl, '://')) {
                return $streamUrl;
            }

            if (substr($streamUrl, 0, 1) === '/' || substr($streamUrl, 1, 1) === ':' || substr($streamUrl, 0, 2) === '\\\\') {
                return $prefix.$streamUrl;
            }

            $streamUrl = getcwd() . '/' . $streamUrl;

            return $prefix.$streamUrl;
        }

        /**
         * Return the JSON representation of a value
         *
         * @param  mixed $data
         * @param  int   $encodeFlags
         * @param  bool  $ignoreErrors
         * @return string
         */
        public static function jsonEncode($data, $encodeFlags = null, $ignoreErrors = false)
        {
            if (null === $encodeFlags) {
                $encodeFlags = self::DEFAULT_JSON_FLAGS;
                if (defined('JSON_PRESERVE_ZERO_FRACTION')) {
                    $encodeFlags |= JSON_PRESERVE_ZERO_FRACTION;
                }
                if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
                    $encodeFlags |= JSON_INVALID_UTF8_SUBSTITUTE;
                }
                if (defined('JSON_PARTIAL_OUTPUT_ON_ERROR')) {
                    $encodeFlags |= JSON_PARTIAL_OUTPUT_ON_ERROR;
                }
            }

            if ($ignoreErrors) {
                $json = @json_encode($data, $encodeFlags);
                if (false === $json) {
                    return 'null';
                }

                return $json;
            }

            $json = json_encode($data, $encodeFlags);
            if (false === $json) {
                $json = self::handleJsonError(json_last_error(), $data);
            }

            return $json;
        }

        /**
         * Handle a json_encode failure.
         *
         * @param  int   $code
         * @param  mixed $data
         * @param  int   $encodeFlags
         * @return string
         */
        public static function handleJsonError($code, $data, $encodeFlags = null)
        {
            if ($code !== JSON_ERROR_UTF8) {
                self::throwEncodeError($code, $data);
            }

            if (is_string($data)) {
                self::detectAndCleanUtf8($data);
            } elseif (is_array($data)) {
                array_walk_recursive($data, [self::class, 'detectAndCleanUtf8']);
            } else {
                self::throwEncodeError($code, $data);
            }

            if (null === $encodeFlags) {
                $encodeFlags = self::DEFAULT_JSON_FLAGS;
                if (defined('JSON_PRESERVE_ZERO_FRACTION')) {
                    $encodeFlags |= JSON_PRESERVE_ZERO_FRACTION;
                }
                if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
                    $encodeFlags |= JSON_INVALID_UTF8_SUBSTITUTE;
                }
                if (defined('JSON_PARTIAL_OUTPUT_ON_ERROR')) {
                    $encodeFlags |= JSON_PARTIAL_OUTPUT_ON_ERROR;
                }
            }

            $json = json_encode($data, $encodeFlags);

            if ($json === false) {
                self::throwEncodeError(json_last_error(), $data);
            }

            return $json;
        }

        /**
         * @param  int   $code
         * @param  mixed $data
         * @throws \RuntimeException
         */
        private static function throwEncodeError($code, $data)
        {
            switch ($code) {
                case JSON_ERROR_DEPTH:
                    $msg = 'Maximum stack depth exceeded';
                    break;
                case JSON_ERROR_STATE_MISMATCH:
                    $msg = 'Underflow or the modes mismatch';
                    break;
                case JSON_ERROR_CTRL_CHAR:
                    $msg = 'Unexpected control character found';
                    break;
                case JSON_ERROR_UTF8:
                    $msg = 'Malformed UTF-8 characters, possibly incorrectly encoded';
                    break;
                default:
                    $msg = 'Unknown error';
            }

            throw new \RuntimeException('JSON encoding failed: '.$msg.'. Encoding: '.var_export($data, true));
        }

        /**
         * @param mixed $data
         */
        public static function detectAndCleanUtf8(&$data)
        {
            if (is_string($data) && !preg_match('//u', $data)) {
                $data = preg_replace_callback(
                    '/[\x80-\xFF]+/',
                    static function ($m) { return utf8_encode($m[0]); },
                    $data
                );
                $data = str_replace(
                    ['¤', '¦', '¨', '´', '¸', '¼', '½', '¾'],
                    ['€', 'Š', 'š', 'Ž', 'ž', 'Œ', 'œ', 'Ÿ'],
                    $data
                );
            }
        }
    }

    class_alias(__NAMESPACE__ . '\Utils', \Monolog\Utils::class);
}
