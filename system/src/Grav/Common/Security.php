<?php

/**
 * @package    Grav\Common
 *
 * @copyright  Copyright (c) 2015 - 2025 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common;

use Exception;
use Grav\Common\Config\Config;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Page\Pages;
use Rhukster\DomSanitizer\DOMSanitizer;
use function chr;
use function count;
use function is_array;
use function is_string;

/**
 * Class Security
 * @package Grav\Common
 */
class Security
{
    /**
     * @param string $filepath
     * @param array|null $options
     * @return string|null
     */
    public static function detectXssFromSvgFile(string $filepath, ?array $options = null): ?string
    {
        if (file_exists($filepath) && Grav::instance()['config']->get('security.sanitize_svg')) {
            $content = file_get_contents($filepath);

            return static::detectXss($content, $options);
        }

        return null;
    }

    /**
     * Sanitize SVG string for XSS code
     *
     * @param string $svg
     * @return string
     */
    public static function sanitizeSvgString(string $svg): string
    {
        if (Grav::instance()['config']->get('security.sanitize_svg')) {
            $sanitizer = new DOMSanitizer(DOMSanitizer::SVG);
            $sanitizer->addDisallowedAttributes(['href', 'xlink:href']);
            $sanitized = $sanitizer->sanitize($svg);
            if (is_string($sanitized)) {
                $svg = $sanitized;
            }
        }

        return $svg;
    }

    /**
     * Sanitize SVG for XSS code
     *
     * @param string $file
     * @return void
     */
    public static function sanitizeSVG(string $file): void
    {
        if (file_exists($file) && Grav::instance()['config']->get('security.sanitize_svg')) {
            $sanitizer = new DOMSanitizer(DOMSanitizer::SVG);
            $sanitizer->addDisallowedAttributes(['href', 'xlink:href']);
            $original_svg = file_get_contents($file);
            $clean_svg = $sanitizer->sanitize($original_svg);

            // Quarantine bad SVG files and throw exception
            if ($clean_svg !== false ) {
                file_put_contents($file, $clean_svg);
            } else {
                $quarantine_file = Utils::basename($file);
                $quarantine_dir = 'log://quarantine';
                Folder::mkdir($quarantine_dir);
                file_put_contents("$quarantine_dir/$quarantine_file", $original_svg);
                unlink($file);
                throw new Exception('SVG could not be sanitized, it has been moved to the logs/quarantine folder');
            }
        }
    }

    /**
     * Detect XSS code in Grav pages
     *
     * @param Pages $pages
     * @param bool $route
     * @param callable|null $status
     * @return array
     */
    public static function detectXssFromPages(Pages $pages, $route = true, ?callable $status = null)
    {
        $routes = $pages->getList(null, 0, true);

        // Remove duplicate for homepage
        unset($routes['/']);

        $list = [];

        // This needs Symfony 4.1 to work
        $status && $status([
            'type' => 'count',
            'steps' => count($routes),
        ]);

        foreach (array_keys($routes) as $route) {
            $status && $status([
                'type' => 'progress',
            ]);

            try {
                $page = $pages->find($route);
                if ($page->exists()) {
                    // call the content to load/cache it
                    $header = (array) $page->header();
                    $content = $page->value('content');

                    $data = ['header' => $header, 'content' => $content];
                    $results = static::detectXssFromArray($data);

                    if (!empty($results)) {
                        $list[$page->rawRoute()] = $results;
                    }
                }
            } catch (Exception) {
                continue;
            }
        }

        return $list;
    }

    /**
     * Detect XSS in an array or strings such as $_POST or $_GET
     *
     * @param array $array      Array such as $_POST or $_GET
     * @param array|null $options Extra options to be passed.
     * @param string $prefix    Prefix for returned values.
     * @return array            Returns flatten list of potentially dangerous input values, such as 'data.content'.
     */
    public static function detectXssFromArray(array $array, string $prefix = '', ?array $options = null)
    {
        if (null === $options) {
            $options = static::getXssDefaults();
        }

        $list = [[]];
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $list[] = static::detectXssFromArray($value, $prefix . $key . '.', $options);
            }
            if ($result = static::detectXss($value, $options)) {
                $list[] = [$prefix . $key => $result];
            }
        }

        return array_merge(...$list);
    }

    /**
     * Determine if string potentially has a XSS attack. This simple function does not catch all XSS and it is likely to
     *
     * return false positives because of it tags all potentially dangerous HTML tags and attributes without looking into
     * their content.
     *
     * @param string|null $string The string to run XSS detection logic on
     * @param array|null $options
     * @return string|null       Type of XSS vector if the given `$string` may contain XSS, false otherwise.
     *
     * Copies the code from: https://github.com/symphonycms/xssfilter/blob/master/extension.driver.php#L138
     */
    public static function detectXss($string, ?array $options = null): ?string
    {
        // Skip any null or non string values
        if (null === $string || !is_string($string) || empty($string)) {
            return null;
        }

        if (null === $options) {
            $options = static::getXssDefaults();
        }

        $enabled_rules = (array)($options['enabled_rules'] ?? null);
        $dangerous_tags = (array)($options['dangerous_tags'] ?? null);
        if (!$dangerous_tags) {
            $enabled_rules['dangerous_tags'] = false;
        }
        $invalid_protocols = (array)($options['invalid_protocols'] ?? null);
        if (!$invalid_protocols) {
            $enabled_rules['invalid_protocols'] = false;
        }
        $enabled_rules = array_filter($enabled_rules, static fn($val) => !empty($val));
        if (!$enabled_rules) {
            return null;
        }

        // Keep a copy of the original string before cleaning up
        $orig = $string;

        // URL decode
        $string = urldecode($string);

        // Convert Hexadecimals
        $string = (string)preg_replace_callback('!(&#|\\\)[xX]([0-9a-fA-F]+);?!u', static fn($m) => chr(hexdec((string) $m[2])), $string);

        // Clean up entities
        $string = preg_replace('!(&#[0-9]+);?!u', '$1;', $string);

        // Decode entities
        $string = html_entity_decode((string) $string, ENT_NOQUOTES | ENT_HTML5, 'UTF-8');

        // Strip whitespace characters
        $string = preg_replace('!\s!u', ' ', $string);
        $stripped = preg_replace('!\s!u', '', (string) $string);

        // Set the patterns we'll test against
        $patterns = [
            // Match any attribute starting with "on" or xmlns (must be preceded by whitespace/special chars)
            // Allow optional whitespace between 'on' and event name to catch obfuscation attempts
            'on_events' => '#(<[^>]+[\s\x00-\x20\"\'\/])(on\s*[a-z]+|xmlns)\s*=[\s|\'\"].*[\s|\'\"]>#iUu',

            // Match javascript:, livescript:, vbscript:, mocha:, feed: and data: protocols
            'invalid_protocols' => '#(' . implode('|', array_map('preg_quote', $invalid_protocols, ['#'])) . ')(:|\&\#58)\S.*?#iUu',

            // Match -moz-bindings
            'moz_binding' => '#-moz-binding[a-z\x00-\x20]*:#u',

            // Match style attributes
            'html_inline_styles' => '#(<[^>]+[a-z\x00-\x20\"\'\/])(style=[^>]*(url\:|x\:expression).*)>?#iUu',

            // Match potentially dangerous tags
            'dangerous_tags' => '#</*(' . implode('|', array_map('preg_quote', $dangerous_tags, ['#'])) . ')[^>]*>?#ui'
        ];

        // Iterate over rules and return label if fail
        foreach ($patterns as $name => $regex) {
            if (!empty($enabled_rules[$name])) {
                // Skip testing 'on_events' against stripped version to avoid false positives
                // with tags like <caption>, <button>, <section> that end with 'on' or contain 'on'
                if ($name === 'on_events') {
                    if (preg_match($regex, (string) $string) || preg_match($regex, $orig)) {
                        return $name;
                    }
                } else {
                    if (preg_match($regex, (string) $string) || preg_match($regex, (string) $stripped) || preg_match($regex, $orig)) {
                        return $name;
                    }
                }
            }
        }

        return null;
    }

    public static function getXssDefaults(): array
    {
        /** @var Config $config */
        $config = Grav::instance()['config'];

        return [
            'enabled_rules' => $config->get('security.xss_enabled'),
            'dangerous_tags' => array_map('trim', $config->get('security.xss_dangerous_tags')),
            'invalid_protocols' => array_map('trim', $config->get('security.xss_invalid_protocols')),
        ];
    }

    /** @var string|null Cached regex pattern for dangerous functions in Twig blocks */
    private static ?string $dangerousTwigFunctionsPattern = null;

    /** @var string|null Cached regex pattern for dangerous properties */
    private static ?string $dangerousTwigPropertiesPattern = null;

    /** @var string|null Cached regex pattern for dangerous function calls */
    private static ?string $dangerousFunctionCallsPattern = null;

    /** @var string|null Cached regex pattern for string concatenation bypass */
    private static ?string $dangerousJoinPattern = null;

    /**
     * Get compiled dangerous Twig patterns (cached for performance)
     *
     * @return array{functions: string, properties: string, calls: string, join: string}
     */
    private static function getDangerousTwigPatterns(): array
    {
        if (self::$dangerousTwigFunctionsPattern === null) {
            // Dangerous Twig functions and methods that should be blocked
            $bad_twig_functions = [
                // Twig internals
                'twig_array_map', 'twig_array_filter', 'call_user_func', 'call_user_func_array',
                'forward_static_call', 'forward_static_call_array',
                // Twig environment manipulation
                'registerUndefinedFunctionCallback', 'registerUndefinedFilterCallback',
                'undefined_functions', 'undefined_filters',
                // File operations
                'read_file', 'file_get_contents', 'file_put_contents', 'fopen', 'fread', 'fwrite',
                'fclose', 'readfile', 'file', 'fpassthru', 'fgetcsv', 'fputcsv', 'ftruncate',
                'fputs', 'fgets', 'fgetc', 'fflush', 'flock', 'glob', 'rename', 'copy', 'unlink',
                'rmdir', 'mkdir', 'symlink', 'link', 'chmod', 'chown', 'chgrp', 'touch', 'tempnam',
                'parse_ini_file', 'highlight_file', 'show_source',
                // Code execution
                'exec', 'shell_exec', 'system', 'passthru', 'popen', 'proc_open', 'proc_close',
                'proc_terminate', 'proc_nice', 'proc_get_status', 'pcntl_exec', 'pcntl_fork',
                'pcntl_signal', 'pcntl_alarm', 'pcntl_setpriority', 'eval', 'assert',
                'create_function', 'preg_replace', 'preg_replace_callback', 'ob_start',
                // Dynamic evaluation
                'evaluate_twig', 'evaluate',
                // Serialization
                'unserialize', 'serialize', 'var_export', 'token_get_all',
                // Network functions (SSRF)
                'curl_init', 'curl_exec', 'curl_multi_exec', 'fsockopen', 'pfsockopen',
                'socket_create', 'stream_socket_client', 'stream_socket_server',
                // Info disclosure
                'phpinfo', 'getenv', 'putenv', 'get_current_user', 'getmyuid', 'getmygid',
                'getmypid', 'get_cfg_var', 'ini_get', 'ini_set', 'ini_alter', 'ini_restore',
                'get_defined_vars', 'get_defined_functions', 'get_defined_constants',
                'get_loaded_extensions', 'get_extension_funcs', 'phpversion', 'php_uname',
                // Reflection
                'ReflectionClass', 'ReflectionFunction', 'ReflectionMethod',
                'ReflectionProperty', 'ReflectionObject',
                // Include/require
                'include', 'include_once', 'require', 'require_once',
                // Callback arrays
                'array_map', 'array_filter', 'array_reduce', 'array_walk', 'array_walk_recursive',
                'usort', 'uasort', 'uksort', 'iterator_apply',
                // Output manipulation
                'header', 'headers_sent', 'header_remove', 'http_response_code',
                // Mail
                'mail',
                // Misc dangerous
                'extract', 'parse_str', 'register_shutdown_function', 'register_tick_function',
                'set_error_handler', 'set_exception_handler', 'spl_autoload_register',
                'apache_child_terminate', 'posix_kill', 'posix_setpgid', 'posix_setsid',
                'posix_setuid', 'posix_setgid', 'posix_mkfifo', 'dl',
                // XML (XXE)
                'simplexml_load_file', 'simplexml_load_string', 'DOMDocument', 'XMLReader',
                // Database
                'mysqli_query', 'pg_query', 'sqlite_query',
            ];

            // Dangerous property/method access patterns (regex patterns)
            $bad_twig_properties = [
                // Twig environment access
                'twig\.twig\b', 'grav\.twig\.twig\b', 'twig\.(?:get|add|set)(?:Function|Filter|Extension|Loader|Cache|Runtime)',
                'twig\.addRuntimeLoader',
                // Config modification
                'config\.set\s*\(', 'grav\.config\.set\s*\(', '\.safe_functions', '\.safe_filters',
                '\.undefined_functions', '\.undefined_filters', 'twig_vars\[', 'config\.join\s*\(',
                // Scheduler access
                'grav\.scheduler\b', 'scheduler\.(?:addCommand|save|run|add|remove)\s*\(?',
                // Core escaper
                'core\.setEscaper', 'setEscaper\s*\(',
                // Context access
                '_context\b', '_self\b', '_charset\b',
                // User modification
                'grav\.user\.(?:update|save)\s*\(', 'grav\.accounts\.user\s*\([^)]*\)\.(?:update|save)',
                '\.(?:set|setNested)Property\s*\(',
                // Flex objects
                '(?:get)?[Ff]lexDirectory\s*\(',
                // Locator write mode
                'grav\.locator\.findResource\s*\([^)]*,\s*true',
                // Plugin/theme manipulation
                'grav\.(?:plugins|themes)\.get\s*\(',
                // Session manipulation
                'session\.(?:set|setFlash)\s*\(',
                // Cache manipulation
                'cache\.(?:delete|clear|purge)',
                // Backups and GPM
                'grav\.(?:backups|gpm)\b',
            ];

            // Build combined patterns (compile once, use many times)
            $quotedFunctions = array_map(fn($f) => preg_quote($f, '/'), $bad_twig_functions);
            $functionsPattern = implode('|', $quotedFunctions);

            // Pattern for functions in Twig blocks
            self::$dangerousTwigFunctionsPattern = '/(({{\s*|{%\s*)[^}]*?(' . $functionsPattern . ')[^}]*?(\s*}}|\s*%}))/i';

            // Pattern for properties (already regex patterns, just combine)
            $propertiesPattern = implode('|', $bad_twig_properties);
            self::$dangerousTwigPropertiesPattern = '/(({{\s*|{%\s*)[^}]*?(' . $propertiesPattern . ')[^}]*?(\s*}}|\s*%}))/i';

            // Pattern for function calls outside Twig blocks (for nested eval)
            self::$dangerousFunctionCallsPattern = '/\b(' . $functionsPattern . ')\s*\(/i';

            // Pattern for string concatenation bypass attempts
            $suspiciousFragments = ['safe_func', 'safe_filt', 'undefined_', 'scheduler', 'registerUndefined', '_context', 'setEscaper'];
            $fragmentsPattern = implode('|', array_map(fn($f) => preg_quote($f, '/'), $suspiciousFragments));
            self::$dangerousJoinPattern = '/(({{\s*|{%\s*)[^}]*?\[[^\]]*[\'"](' . $fragmentsPattern . ')[\'"][^\]]*\]\s*\|\s*join[^}]*?(\s*}}|\s*%}))/i';
        }

        return [
            'functions' => self::$dangerousTwigFunctionsPattern,
            'properties' => self::$dangerousTwigPropertiesPattern,
            'calls' => self::$dangerousFunctionCallsPattern,
            'join' => self::$dangerousJoinPattern,
        ];
    }

    public static function cleanDangerousTwig(string $string): string
    {
        // Early exit for empty strings or strings without Twig
        if ($string === '' || (strpos($string, '{{') === false && strpos($string, '{%') === false)) {
            return $string;
        }

        // Get cached compiled patterns
        $patterns = self::getDangerousTwigPatterns();

        // Pass 1: Block dangerous functions in Twig blocks
        $string = preg_replace($patterns['functions'], '{# BLOCKED: $1 #}', $string);

        // Pass 2: Block dangerous property access patterns
        $string = preg_replace($patterns['properties'], '{# BLOCKED: $1 #}', $string);

        // Pass 3: Block dangerous function calls (for nested eval bypass)
        $string = preg_replace($patterns['calls'], '{# BLOCKED: $0 #}', $string);

        // Pass 4: Block string concatenation bypass attempts
        $string = preg_replace($patterns['join'], '{# BLOCKED: $1 #}', $string);

        return $string;
    }
}
