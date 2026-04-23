<?php

/**
 * @package    Grav\Common
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common;

use Exception;
use Grav\Common\Config\Config;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Page\Pages;
use Grav\Common\Twig\Sandbox\GravSecurityPolicy;
use Rhukster\DomSanitizer\DOMSanitizer;
use RocketTheme\Toolbox\File\YamlFile;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;
use RuntimeException;
use Twig\Sandbox\SecurityPolicyInterface;
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

    /**
     * Names dangerous only when invoked as a function/method/filter.
     * Property access (e.g. {{ page.header }}) is allowed; calls (header(...), obj.header(...), |header) are blocked.
     */
    private const CALLABLE_DANGEROUS_NAMES = [
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
        'simplexml_load_file', 'simplexml_load_string',
        // Database
        'mysqli_query', 'pg_query', 'sqlite_query',
    ];

    /**
     * Names dangerous even as bare references (introspection classes).
     * Match inside a Twig block regardless of call form.
     */
    private const INTROSPECTION_NAMES = [
        'ReflectionClass', 'ReflectionFunction', 'ReflectionMethod',
        'ReflectionProperty', 'ReflectionObject',
        'DOMDocument', 'XMLReader',
    ];

    /** @var array<string, string|null>|null Cached compiled patterns for current whitelist */
    private static ?array $dangerousTwigCompiled = null;

    /** @var string|null Cache key (hash of whitelist config) for compiled patterns */
    private static ?string $dangerousTwigCacheKey = null;

    /**
     * Get compiled dangerous Twig patterns (cached for performance).
     * Recompiled only when the whitelist config changes.
     *
     * @return array{functions: string|null, properties: string|null, join: string|null, whitelist: array}
     */
    private static function getDangerousTwigPatterns(): array
    {
        /** @var Config $config */
        $config = Grav::instance()['config'];
        $whitelist = (array) $config->get('security.twig_filter.whitelist', []);
        $wlFunctions = array_map('strval', (array) ($whitelist['functions'] ?? []));
        $wlFilters = array_map('strval', (array) ($whitelist['filters'] ?? []));
        $wlProperties = array_map('strval', (array) ($whitelist['properties'] ?? []));

        $cacheKey = md5(serialize([$wlFunctions, $wlFilters, $wlProperties]));
        if (self::$dangerousTwigCompiled !== null && self::$dangerousTwigCacheKey === $cacheKey) {
            return self::$dangerousTwigCompiled;
        }

        $callableNames = array_values(array_diff(self::CALLABLE_DANGEROUS_NAMES, $wlFunctions));
        $filterNames = array_values(array_diff(self::CALLABLE_DANGEROUS_NAMES, $wlFilters));
        $introspectionNames = array_values(array_diff(self::INTROSPECTION_NAMES, $wlProperties));

        // Build combined functions/methods/filters pattern (single pass, single regex).
        // Named groups keep capture lookup stable regardless of which branches are present.
        $callAlt = $callableNames ? implode('|', array_map(static fn($f) => preg_quote($f, '/'), $callableNames)) : null;
        $filterAlt = $filterNames ? implode('|', array_map(static fn($f) => preg_quote($f, '/'), $filterNames)) : null;

        $functionsParts = [];
        if ($callAlt !== null) {
            // Bare call: not preceded by '.' or word char, followed by '('
            $functionsParts[] = '(?<![.\w])(?P<bare>' . $callAlt . ')\s*\(';
            // Method call: preceded by '.', followed by '('
            $functionsParts[] = '\.(?P<method>' . $callAlt . ')\s*\(';
        }
        if ($filterAlt !== null) {
            // Filter pipe: preceded by '|', followed by word boundary
            $functionsParts[] = '\|\s*(?P<filter>' . $filterAlt . ')\b';
        }
        $functionsPattern = $functionsParts ? '/(?:' . implode('|', $functionsParts) . ')/i' : null;

        // Introspection names: dangerous even without parens, but require a Twig block context to avoid prose false-positives
        $introAlt = $introspectionNames ? implode('|', array_map(static fn($f) => preg_quote($f, '/'), $introspectionNames)) : null;

        // Property/method access patterns that are dangerous regardless of bare/call form.
        // These are regex fragments applied in-place; the whitelist matches fragments verbatim.
        $propertyFragments = array_values(array_diff([
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
        ], $wlProperties));

        $propertyFragmentsAlt = $propertyFragments ? implode('|', $propertyFragments) : null;
        if ($introAlt !== null) {
            $propertyFragmentsAlt = $propertyFragmentsAlt === null ? '\b(?:' . $introAlt . ')\b' : $propertyFragmentsAlt . '|\b(?:' . $introAlt . ')\b';
        }
        $propertiesPattern = $propertyFragmentsAlt !== null
            ? '/(({{\s*|{%\s*)[^}]*?(' . $propertyFragmentsAlt . ')[^}]*?(\s*}}|\s*%}))/i'
            : null;

        // String-concat bypass: e.g. ['safe_func'~'tions']|join — block array-join of suspicious fragments
        $suspiciousFragments = ['safe_func', 'safe_filt', 'undefined_', 'scheduler', 'registerUndefined', '_context', 'setEscaper'];
        $fragmentsPattern = implode('|', array_map(static fn($f) => preg_quote($f, '/'), $suspiciousFragments));
        $joinPattern = '/(({{\s*|{%\s*)[^}]*?\[[^\]]*[\'"](' . $fragmentsPattern . ')[\'"][^\]]*\]\s*\|\s*join[^}]*?(\s*}}|\s*%}))/i';

        self::$dangerousTwigCompiled = [
            'functions' => $functionsPattern,
            'properties' => $propertiesPattern,
            'join' => $joinPattern,
            'whitelist' => [
                'functions' => $wlFunctions,
                'filters' => $wlFilters,
                'properties' => $wlProperties,
            ],
        ];
        self::$dangerousTwigCacheKey = $cacheKey;

        return self::$dangerousTwigCompiled;
    }

    /**
     * Clean dangerous Twig constructs from a string.
     * Legacy entry point; discards the filtered flag.
     */
    public static function cleanDangerousTwig(string $string): string
    {
        [$cleaned] = self::cleanDangerousTwigWithStatus($string);
        return $cleaned;
    }

    /**
     * Clean dangerous Twig constructs and report whether any filtering occurred.
     *
     * @return array{0: string, 1: bool} [cleaned string, was anything filtered?]
     */
    public static function cleanDangerousTwigWithStatus(string $string): array
    {
        // Early exit: empty or no Twig syntax at all.
        if ($string === '' || (strpos($string, '{{') === false && strpos($string, '{%') === false)) {
            return [$string, false];
        }

        /** @var Config $config */
        $config = Grav::instance()['config'];
        if (!$config->get('security.twig_filter.enabled', true)) {
            return [$string, false];
        }

        $patterns = self::getDangerousTwigPatterns();
        $logEnabled = (bool) $config->get('security.twig_filter.logging', true);
        // When the Twig sandbox is enabled, it is the enforcement layer — demote the
        // regex filter to log-only so callers aren't double-masked and legitimate
        // expressions that the sandbox accepts aren't pre-empted here.
        $rewrite = !(bool) $config->get('security.twig_sandbox.enabled', true);
        $filtered = false;

        $replace = static function (string $match) use ($rewrite): string {
            return $rewrite ? '{# BLOCKED: ' . $match . ' #}' : $match;
        };

        // Pass A: functions / methods / filters (single combined regex).
        if ($patterns['functions'] !== null) {
            if ($logEnabled) {
                $string = preg_replace_callback($patterns['functions'], static function ($m) use (&$filtered, $replace) {
                    $filtered = true;
                    if (!empty($m['bare'])) {
                        $rule = 'functions';
                        $token = $m['bare'];
                    } elseif (!empty($m['method'])) {
                        $rule = 'functions';
                        $token = $m['method'];
                    } else {
                        $rule = 'filters';
                        $token = $m['filter'] ?? '';
                    }
                    self::logTwigBlock($rule, $token, $m[0]);
                    return $replace($m[0]);
                }, $string);
            } elseif ($rewrite) {
                $count = 0;
                $string = preg_replace($patterns['functions'], '{# BLOCKED: $0 #}', $string, -1, $count);
                if ($count > 0) {
                    $filtered = true;
                }
            } else {
                // Logging off and sandbox on: nothing to do here.
                if (preg_match($patterns['functions'], $string)) {
                    $filtered = true;
                }
            }
        }

        // Pass B: dangerous property access patterns (inside Twig blocks).
        if ($patterns['properties'] !== null) {
            if ($logEnabled) {
                $string = preg_replace_callback($patterns['properties'], static function ($m) use (&$filtered, $replace) {
                    $filtered = true;
                    $token = $m[3] ?? '';
                    self::logTwigBlock('properties', $token, $m[1]);
                    return $replace($m[1]);
                }, $string);
            } elseif ($rewrite) {
                $count = 0;
                $string = preg_replace($patterns['properties'], '{# BLOCKED: $1 #}', $string, -1, $count);
                if ($count > 0) {
                    $filtered = true;
                }
            } else {
                if (preg_match($patterns['properties'], $string)) {
                    $filtered = true;
                }
            }
        }

        // Pass C: string-concat bypass via array-join of suspicious fragments.
        if ($logEnabled) {
            $string = preg_replace_callback($patterns['join'], static function ($m) use (&$filtered, $replace) {
                $filtered = true;
                $token = $m[3] ?? '';
                self::logTwigBlock('join', $token, $m[1]);
                return $replace($m[1]);
            }, $string);
        } elseif ($rewrite) {
            $count = 0;
            $string = preg_replace($patterns['join'], '{# BLOCKED: $1 #}', $string, -1, $count);
            if ($count > 0) {
                $filtered = true;
            }
        } else {
            if (preg_match($patterns['join'], $string)) {
                $filtered = true;
            }
        }

        return [$string, $filtered];
    }

    /** @var array<string, bool> Dedup map for log events in a single request */
    private static array $twigLogSeen = [];

    /**
     * Write one structured log entry per unique rule+token per request.
     * Resolves the security log channel lazily — the container is only touched on the first event.
     */
    private static function logTwigBlock(string $rule, string $token, string $match): void
    {
        $key = $rule . ':' . $token;
        if (isset(self::$twigLogSeen[$key])) {
            return;
        }
        self::$twigLogSeen[$key] = true;

        try {
            $grav = Grav::instance();
            if (!$grav->offsetExists('log.security')) {
                return;
            }
            $logger = $grav['log.security'];

            $route = 'unknown';
            if ($grav->offsetExists('page')) {
                $page = $grav['page'];
                if ($page && method_exists($page, 'route')) {
                    $route = (string) ($page->route() ?? 'unknown');
                }
            }

            $excerpt = substr((string) preg_replace('/\s+/', ' ', trim($match)), 0, 120);
            $hint = self::twigWhitelistHint($rule, $token);

            $logger->warning(
                sprintf('[TwigFilter] blocked rule=%s token=%s route=%s', $rule, $token, $route),
                [
                    'rule' => $rule,
                    'token' => $token,
                    'match' => $excerpt,
                    'route' => $route,
                    'hint' => $hint,
                ]
            );
        } catch (Exception) {
            // Never let a logging failure break rendering.
        }
    }

    private static function twigWhitelistHint(string $rule, string $token): string
    {
        return match ($rule) {
            'functions' => "Add '{$token}' to security.twig_filter.whitelist.functions to allow this call",
            'filters' => "Add '{$token}' to security.twig_filter.whitelist.filters to allow this filter",
            'properties' => "Add the matching regex fragment to security.twig_filter.whitelist.properties to allow this access",
            'join' => "Array-join of a suspicious fragment is blocked; refactor the expression",
            default => 'Review the blocked Twig construct',
        };
    }

    /** @var SecurityPolicyInterface|null Cached policy for current request */
    private static ?SecurityPolicyInterface $twigSandboxPolicy = null;

    /** @var string|null Cache key (hash of policy config) */
    private static ?string $twigSandboxPolicyKey = null;

    /**
     * Build (or return cached) Twig sandbox SecurityPolicy from security.twig_sandbox.* config.
     * Cached per-request, invalidated when the config hash changes.
     */
    public static function buildTwigSandboxPolicy(): SecurityPolicyInterface
    {
        /** @var Config $config */
        $config = Grav::instance()['config'];

        $tags       = self::normalizeStringList($config->get('security.twig_sandbox.allowed_tags', []));
        $filters    = self::normalizeStringList($config->get('security.twig_sandbox.allowed_filters', []));
        $functions  = self::normalizeStringList($config->get('security.twig_sandbox.allowed_functions', []));
        // Method names get lowercased to match Twig's sandbox comparison.
        // Property names are CASE-SENSITIVE and preserved as-authored.
        $methods    = self::normalizeMethodsMap($config->get('security.twig_sandbox.allowed_methods', []), true);
        $properties = self::normalizeMethodsMap($config->get('security.twig_sandbox.allowed_properties', []), false);

        $cacheKey = md5(serialize([$tags, $filters, $functions, $methods, $properties]));
        if (self::$twigSandboxPolicy !== null && self::$twigSandboxPolicyKey === $cacheKey) {
            return self::$twigSandboxPolicy;
        }

        self::$twigSandboxPolicy = new GravSecurityPolicy($tags, $filters, $methods, $properties, $functions);
        self::$twigSandboxPolicyKey = $cacheKey;

        return self::$twigSandboxPolicy;
    }

    /**
     * Log a Twig sandbox violation via the security log channel. Called from the
     * SecurityError handler in Twig::processPage() / processString().
     */
    public static function logTwigSandboxViolation(string $rule, string $token, string $className = '', string $extra = ''): void
    {
        try {
            /** @var Config $config */
            $config = Grav::instance()['config'];
            if (!$config->get('security.twig_sandbox.logging', true)) {
                return;
            }

            $grav = Grav::instance();
            if (!$grav->offsetExists('log.security')) {
                return;
            }
            $logger = $grav['log.security'];

            $route = 'unknown';
            if ($grav->offsetExists('page')) {
                $page = $grav['page'];
                if ($page && method_exists($page, 'route')) {
                    $route = (string) ($page->route() ?? 'unknown');
                }
            }

            $hint = self::twigSandboxHint($rule, $token, $className);

            $logger->warning(
                sprintf('[TwigSandbox] blocked rule=%s token=%s route=%s', $rule, $token, $route),
                [
                    'rule' => $rule,
                    'token' => $token,
                    'class' => $className,
                    'route' => $route,
                    'extra' => $extra,
                    'hint' => $hint,
                ]
            );
        } catch (Exception) {
            // Never let a logging failure break rendering.
        }
    }

    private static function twigSandboxHint(string $rule, string $token, string $className): string
    {
        return match ($rule) {
            'tag'      => "To allow this tag, add '{$token}' to security.twig_sandbox.allowed_tags — OR disable the sandbox via security.twig_sandbox.enabled: false.",
            'filter'   => "To allow this filter, add '{$token}' to security.twig_sandbox.allowed_filters — OR disable the sandbox via security.twig_sandbox.enabled: false.",
            'function' => "To allow this function, add '{$token}' to security.twig_sandbox.allowed_functions — OR disable the sandbox via security.twig_sandbox.enabled: false.",
            'method'   => "To allow this method, add '{$token}' under security.twig_sandbox.allowed_methods['{$className}'] — OR disable the sandbox via security.twig_sandbox.enabled: false.",
            'property' => "To allow this property, add '{$token}' under security.twig_sandbox.allowed_properties['{$className}'] — OR disable the sandbox via security.twig_sandbox.enabled: false.",
            default    => 'Review the blocked Twig construct in logs/security.log.',
        };
    }

    /**
     * @param mixed $value
     * @return list<string>
     */
    private static function normalizeStringList($value): array
    {
        if (!is_array($value)) {
            return [];
        }
        $out = [];
        foreach ($value as $v) {
            if (is_string($v) && $v !== '') {
                $out[] = $v;
            }
        }
        return $out;
    }

    /**
     * Normalize a class => [members] map read from config. Accepts two input
     * shapes for operator convenience:
     *
     *   Nested map (hand-edited YAML):
     *     'Grav\Common\Config\Config': [get, toarray]
     *
     *   List-of-rows (admin UI list field; shipped as default):
     *     - class: 'Grav\Common\Config\Config'
     *       methods: 'get, toarray'   # string OR list
     *
     * @param mixed $value
     * @param bool  $lowercase If true, lowercase every name (use for method
     *                         allowlists to match Twig's case-insensitive
     *                         method comparison). Properties are case-sensitive.
     * @return array<class-string, list<string>>
     */
    private static function normalizeMethodsMap($value, bool $lowercase = true): array
    {
        if (!is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $key => $entry) {
            // List-of-rows row: each entry is ['class' => '...', 'methods' => '...']
            if (is_int($key) && is_array($entry) && isset($entry['class'])) {
                $class = (string) $entry['class'];
                $methods = $entry['methods'] ?? [];
                $clean = self::splitMethodNames($methods, $lowercase);
                if ($class !== '' && $clean) {
                    $out[$class] = array_values(array_unique(array_merge($out[$class] ?? [], $clean)));
                }
                continue;
            }

            // Nested-map entry: key is the class, entry is the methods list
            if (is_string($key) && is_array($entry)) {
                $clean = self::splitMethodNames($entry, $lowercase);
                if ($clean) {
                    $out[$key] = array_values(array_unique(array_merge($out[$key] ?? [], $clean)));
                }
            }
        }
        return $out;
    }

    /**
     * Accept a methods list as either an array of strings, a CSV string, or
     * a mix; return a flat list of member names.
     *
     * @param mixed $methods
     * @return list<string>
     */
    private static function splitMethodNames($methods, bool $lowercase = true): array
    {
        if (is_string($methods)) {
            $methods = preg_split('/\s*,\s*/', trim($methods)) ?: [];
        }
        if (!is_array($methods)) {
            return [];
        }
        $clean = [];
        foreach ($methods as $m) {
            if (is_string($m)) {
                $m = trim($m);
                if ($m !== '') {
                    $clean[] = $lowercase ? strtolower($m) : $m;
                }
            }
        }
        return $clean;
    }

    /** @var string|null in-process cache for the nonce key */
    private static ?string $nonceKey = null;

    /**
     * Per-site HMAC key used for CSRF nonce signing, admin rate-limit key hashing,
     * and (when configured) session-name derivation. Backed by a local PHP file
     * outside the Config tree, so sandboxed Twig cannot reach it via
     * `grav.config.get('security.salt')` or `Config::toArray()` (GHSA-3f29-pqwf-v4j4).
     *
     * Migration: if the legacy `security.salt` key is present in the loaded Config
     * (i.e. from an older install's `user/config/security.yaml`), its value is
     * copied into the private file on first call and scrubbed from both the live
     * Config and the on-disk YAML. Existing CSRF nonces and sessions survive the
     * upgrade because the key value is preserved.
     *
     * To rotate the key manually, delete `user/config/security-private.php`; the
     * next request generates a fresh 64-char random value. Rotation invalidates
     * in-flight CSRF nonces and — if `system.session.uniqueness` is set to
     * `security` — existing sessions.
     */
    public static function getNonceKey(): string
    {
        if (self::$nonceKey !== null) {
            return self::$nonceKey;
        }

        $grav = Grav::instance();
        /** @var UniformResourceLocator $locator */
        $locator = $grav['locator'];
        $configFolder = $locator->findResource('config://', true) ?: $locator->findResource('config://', true, true);
        $privateFile = "{$configFolder}/security-private.php";

        if (is_file($privateFile)) {
            $value = @include $privateFile;
            if (is_string($value) && $value !== '') {
                return self::$nonceKey = $value;
            }
            // Corrupt/empty file — fall through to regenerate.
        }

        // One-time migration out of Config for sites upgrading from <= v2.0.0-beta.2.
        /** @var Config $config */
        $config = $grav['config'];
        $legacy = $config->get('security.salt');
        if (is_string($legacy) && $legacy !== '') {
            self::writeNonceKey($privateFile, $legacy);
            $config->set('security.salt', null);

            $securityYaml = "{$configFolder}/security.yaml";
            if (is_file($securityYaml)) {
                $file = YamlFile::instance($securityYaml);
                $content = (array) $file->content();
                if (array_key_exists('salt', $content)) {
                    unset($content['salt']);
                    $file->content($content);
                    $file->save();
                    $file->free();
                }
            }

            return self::$nonceKey = $legacy;
        }

        $generated = bin2hex(random_bytes(32));
        self::writeNonceKey($privateFile, $generated);

        return self::$nonceKey = $generated;
    }

    private static function writeNonceKey(string $path, string $value): void
    {
        $escaped = var_export($value, true);
        $contents = "<?php\n\n// Auto-generated private secret. Do NOT commit to version control.\n// Used for CSRF nonce signing and admin rate-limit hashing. Regenerate by\n// deleting this file; the next request will write a new value.\n\nreturn {$escaped};\n";

        $dir = dirname($path);
        if (!is_dir($dir)) {
            Folder::create($dir);
        }

        // Atomic write: stage to a temp file, fsync via rename.
        $tmp = $path . '.tmp';
        if (@file_put_contents($tmp, $contents, LOCK_EX) === false) {
            throw new RuntimeException('Failed to write nonce key file');
        }
        @chmod($tmp, 0600);
        if (!@rename($tmp, $path)) {
            @unlink($tmp);
            throw new RuntimeException('Failed to commit nonce key file');
        }
    }
}
