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
use RocketTheme\Toolbox\Event\Event;
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
            // Match any attribute starting with "on" or xmlns (must be preceded by an
            // attribute boundary: whitespace, NUL, quote or slash). We deliberately
            // do NOT try to match the attribute value itself — the previous regex
            // required quotes-or-spaces around the `=` sign and was bypassed by
            // unquoted handlers like `<img src=x onerror=alert(1)>`
            // (GHSA-9695-8fr9-hw5q, also exploited by GHSA-c2q3-p4jr-c55f and
            // GHSA-w8cg-7jcj-4vv2). Detecting the attribute name + `=` is enough
            // for a tripwire; trade-off is occasional false positives when an
            // unrelated `on*=` substring appears inside another attribute's value.
            'on_events' => '#<[^>]*?[\s\x00-\x20\"\'\/](on\s*[a-z]+|xmlns)\s*=#iu',

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

        // Raw, as-authored allowlists from config (system + user merged). The
        // friendly shapes here — flat lists for tags/filters/functions, the
        // list-of-rows shape for methods/properties — are exactly what the
        // onBuildTwigSandboxPolicy event hands to plugins, so a plugin appends
        // entries the same way they're written in security.yaml.
        $rawTags       = $config->get('security.twig_sandbox.allowed_tags', []);
        $rawFilters    = $config->get('security.twig_sandbox.allowed_filters', []);
        $rawFunctions  = $config->get('security.twig_sandbox.allowed_functions', []);
        $rawMethods    = $config->get('security.twig_sandbox.allowed_methods', []);
        $rawProperties = $config->get('security.twig_sandbox.allowed_properties', []);
        $configAccess  = (bool) $config->get('security.twig_content.config_access', false);

        $cacheKey = md5(serialize([$rawTags, $rawFilters, $rawFunctions, $rawMethods, $rawProperties, $configAccess]));
        if (self::$twigSandboxPolicy !== null && self::$twigSandboxPolicyKey === $cacheKey) {
            return self::$twigSandboxPolicy;
        }

        // Let plugins extend the allowlists for their own safe Twig members so
        // editor-authored page content can use them under the sandbox. A plugin
        // that ships a Twig function subscribes to `onBuildTwigSandboxPolicy`
        // and appends to the relevant list — it is asserting that member is
        // safe to expose to content authors, the same trust boundary as
        // registering it in the first place. Example handler (read-modify-write
        // because the event arguments are returned by value):
        //
        //     $functions = $event['functions'];
        //     $functions[] = 'unite_gallery';
        //     $event['functions'] = $functions;
        //
        //     $methods = $event['methods'];
        //     $methods[] = ['class' => Gallery::class, 'methods' => 'render'];
        //     $event['methods'] = $methods;
        //
        // Fired only on a genuine (re)build, never on the memoized path above:
        // the policy is built once per request and the active plugin set is
        // constant for the request, so the config-derived key memoizes the
        // event's additions correctly with no per-render cost.
        $event = new Event([
            'tags'       => $rawTags,
            'filters'    => $rawFilters,
            'functions'  => $rawFunctions,
            'methods'    => $rawMethods,
            'properties' => $rawProperties,
        ]);
        Grav::instance()->fireEvent('onBuildTwigSandboxPolicy', $event);

        // Method names get lowercased to match Twig's sandbox comparison.
        // Property names are CASE-SENSITIVE and preserved as-authored.
        $tags       = self::normalizeStringList($event['tags']);
        $filters    = self::normalizeStringList($event['filters']);
        $functions  = self::normalizeStringList($event['functions']);
        $methods    = self::normalizeMethodsMap($event['methods'], true);
        $properties = self::normalizeMethodsMap($event['properties'], false);

        // security.twig_content.config_access also closes the `grav.config`
        // back-door: with the toggle off, the injected `config` variable is a
        // deny-all SandboxConfig (handled in Twig::buildSandboxConfig), but
        // `grav.config` and `grav['config']` still resolve to the raw Config
        // service. Strip the Config and Data class entries from the sandbox's
        // method allowlist so any reach into the raw container soft-fails via
        // SecurityError instead of leaking values. The SandboxConfig class
        // entry stays — that's the variable editors are meant to read.
        if (!$configAccess) {
            unset(
                $methods['Grav\\Common\\Config\\Config'],
                $methods['Grav\\Common\\Data\\Data']
            );
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

    /**
     * Options resolver for the `header.process` checkboxes field in the page
     * editor blueprint. Removes the `twig` checkbox when either the master
     * gate is off or the current user lacks permission to enable Twig in
     * content. Wired via `data-options@` in system/blueprints/pages/default.yaml.
     *
     * Visibility rules (any failure → twig option omitted):
     *   - security.twig_content.process_enabled must be true
     *   - security.twig_content.editor_enabled must be true OR the current
     *     user must hold `admin.super` or `admin.pages_twig`
     *
     * @return array<string,string>
     */
    public static function pageProcessOptions(): array
    {
        $options = ['markdown' => 'Markdown'];

        try {
            $grav = Grav::instance();
            /** @var Config $config */
            $config = $grav['config'];

            if ((bool) $config->get('security.twig_content.process_enabled', false) === false) {
                return $options;
            }

            if ((bool) $config->get('security.twig_content.editor_enabled', false) === true) {
                $options['twig'] = 'Twig';
                return $options;
            }

            $user = $grav['user'] ?? null;
            if ($user !== null && (
                $user->authorize('admin.super') === true
                || $user->authorize('admin.pages_twig') === true
            )) {
                $options['twig'] = 'Twig';
            }
        } catch (Exception) {
            // Conservative default: markdown only.
        }

        return $options;
    }

    /**
     * Default the per-page `process.twig` flag from
     * `security.twig_content.process_enabled` when the key isn't explicitly
     * set in the configured `process` array. The security gate is the single
     * source of truth for editor-Twig in content; an explicit value (true or
     * false) in `system.pages.process` or per-page frontmatter still wins.
     *
     * Treats explicit YAML null (`twig: ~`) as "unset" so it inherits the gate.
     *
     * @param array<string,mixed> $process Configured process array (may be empty).
     * @return array<string,mixed> Same array with `twig` populated when it was missing or null.
     */
    public static function applyTwigContentDefault(array $process): array
    {
        if (isset($process['twig'])) {
            return $process;
        }
        try {
            $process['twig'] = (bool) Grav::instance()['config']->get('security.twig_content.process_enabled', false);
        } catch (\Throwable) {
            $process['twig'] = false;
        }
        return $process;
    }

    /**
     * Per-page `process` field defaults for the page editor blueprint.
     * Pulls markdown/twig defaults from `system.pages.process`, defaults
     * `twig` from the security gate when unset, and intersects the result
     * down to the keys advertised by pageProcessOptions() so plugin-
     * contributed keys outside the {markdown, twig} contract don't leak
     * into the form's `default:` block. Wired via `data-default@` in
     * pages/default.yaml.
     *
     * @return array<string,bool>
     */
    public static function pageProcessDefaults(): array
    {
        $defaults = ['markdown' => true, 'twig' => false];

        try {
            $config = Grav::instance()['config'];
            // Apply the gate fallback to the configured value FIRST so an
            // unset twig key inherits from the gate; only then overlay onto
            // the schema seed (markdown defaulting to true).
            $configured = self::applyTwigContentDefault((array) $config->get('system.pages.process', []));
            $merged = array_replace($defaults, $configured);
            // Restrict to the keys pageProcessOptions() actually renders so
            // stray plugin-contributed keys don't appear in the form default.
            // Always keep markdown + twig in the schema even if the current
            // user's options view hides twig — the field still expects both
            // checkboxes' defaults available.
            $allowed = array_unique(array_merge(array_keys(self::pageProcessOptions()), ['markdown', 'twig']));
            $defaults = array_intersect_key($merged, array_flip($allowed));
        } catch (\Throwable) {
            // Conservative default already set above.
        }

        foreach ($defaults as $key => $val) {
            $defaults[$key] = (bool) $val;
        }

        return $defaults;
    }

    /**
     * Log when the security.twig_content.process_enabled gate blocks page-content
     * Twig processing. Called from Page::content() and Page::processFrontmatter()
     * paths. Deduped per-route per-request so a single page render emits one entry.
     */
    public static function logTwigContentGateBlocked(string $route, string $source = 'content'): void
    {
        try {
            $grav = Grav::instance();
            if (!$grav->offsetExists('log.security')) {
                return;
            }

            static $logged = [];
            $key = $source . '|' . $route;
            if (isset($logged[$key])) {
                return;
            }
            $logged[$key] = true;

            $grav['log.security']->warning(
                sprintf('[TwigContentGate] blocked source=%s route=%s', $source, $route),
                [
                    'source' => $source,
                    'route'  => $route,
                    'hint'   => 'Enable security.twig_content.process_enabled to allow Twig processing in page content.',
                ]
            );
        } catch (Exception) {
            // Never let a logging failure break rendering.
        }
    }

    /**
     * Log an XSS hit detected in the *rendered* output of editor-authored Twig
     * content. The blueprint-time validator only inspects the raw source, so a
     * payload assembled at render time (string concatenation, dynamic tag/attr
     * names) slips past it; this is the post-render backstop. (GHSA-2c4f-86xc-cr74)
     *
     * @param string $route Route of the offending page.
     * @param string $found The XSS token detected in the rendered output.
     */
    public static function logTwigContentXssBlocked(string $route, string $found): void
    {
        try {
            $grav = Grav::instance();
            if (!$grav->offsetExists('log.security')) {
                return;
            }

            $grav['log.security']->warning(
                sprintf('[TwigContentXss] blocked route=%s found=%s', $route, $found),
                [
                    'route' => $route,
                    'found' => $found,
                    'hint'  => 'Rendered Twig content produced markup the XSS detector flags. The blueprint validator cannot see render-time-assembled payloads; content was blanked. Disable security.twig_content.xss_scan_output to allow it.',
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
