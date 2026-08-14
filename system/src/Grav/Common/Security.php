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
use Grav\Common\Data\Validation;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Page\Interfaces\PageInterface;
use Grav\Common\Page\Medium\Medium;
use Grav\Common\Page\Pages;
use Grav\Common\Twig\Sandbox\GravSecurityPolicy;
use Grav\Common\Twig\Sandbox\SandboxDefaults;
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
 *
 * ---------------------------------------------------------------------------
 * ON detectXss() AND ITS RELATIVES: THESE ARE HEURISTICS, NOT A BOUNDARY
 * ---------------------------------------------------------------------------
 *
 * `detectXss()` and everything built on it (`detectXssFromArray()`,
 * `detectXssFromPages()`, `detectXssInEditorContent()`, `detectXssFromSvgFile()`)
 * are a **denylist**: a list of patterns that have historically indicated an XSS
 * attempt. They are deliberately noisy, they produce false positives, and — this
 * is the important part — **they will never be complete.** A denylist over an
 * unbounded input space cannot be. Browsers keep adding parsing quirks, and any
 * sufficiently motivated payload will eventually find one the patterns miss.
 *
 * They are therefore **defense in depth, not a security boundary**:
 *
 *   - They exist to catch careless and opportunistic content early, and to give
 *     operators a scanning tool (`bin/grav security`) for auditing existing
 *     content. Both are genuinely useful and both stay.
 *   - They are NOT what makes Grav safe against XSS. **Escaping at output is.**
 *     Twig autoescaping, the Twig sandbox, and the DOM sanitizer for SVG are the
 *     actual controls. Any code path that would be unsafe if `detectXss()`
 *     returned null must be fixed at its output sink, not by adding a pattern
 *     here.
 *
 * Consequences for anyone reading this because they found a bypass:
 *
 *   A new string that slips past these patterns is **not, on its own, a
 *   vulnerability**, and the Grav project does not issue security advisories for
 *   one. See the "What we do not publish an advisory for" section of SECURITY.md.
 *   Bypasses are expected by design — that is what a denylist is.
 *
 *   What IS a vulnerability, and what we very much want reported, is content
 *   that reaches an output sink **unescaped**. If you have a payload that renders
 *   without escaping, report that with the rendered sink — the bug is at the
 *   sink, and adding a pattern here would only have hidden it.
 *
 * Patches that tighten these patterns are still welcome and still get merged.
 * They just ship as ordinary hardening in the normal release, credited in the
 * CHANGELOG, rather than as an advisory.
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
     * Heuristically flag a string that *looks like* an XSS attempt.
     *
     * This is a denylist and it is **advisory**. It does not catch all XSS, it
     * will never catch all XSS, and it produces false positives because it flags
     * potentially dangerous tags and attributes without understanding their
     * context. Treat a null return as "nothing obvious matched", never as
     * "this string is safe to emit unescaped".
     *
     * **Not a security boundary.** See the class docblock above before reporting
     * a bypass: escaping at output is the control that actually protects Grav,
     * and a payload that evades these patterns is not by itself a vulnerability.
     * A payload that reaches an output sink unescaped is, and that is the bug we
     * want to hear about.
     *
     * @param string|null $string The string to run XSS detection logic on
     * @param array|null $options
     * @return string|null       Type of XSS vector if the given `$string` may contain XSS, null otherwise.
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
        // `xmlns` was historically folded into the `on_events` regex, so callers
        // (and the security.xss_enabled config) only know about `on_events`.
        // Default `xmlns` to follow it unless a caller explicitly opts out — the
        // render-time output scan in Page::processTwig sets it false so legit
        // rendered <svg xmlns=...> no longer blanks the page.
        if (!array_key_exists('xmlns', $enabled_rules)) {
            $enabled_rules['xmlns'] = $enabled_rules['on_events'] ?? false;
        }
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

        // Every pattern below carries the /u modifier, and PCRE refuses to run a
        // /u pattern against a subject that is not valid UTF-8: preg_match()
        // returns false (not 0) with PREG_BAD_UTF8_ERROR. The loop at the bottom
        // only tested truthiness, so a single stray byte anywhere in the value
        // turned every rule into "clean" and the payload saved unflagged
        // (GHSA-q2j8-x8hf-63ch). The preg_replace() cleanup just below fails the
        // same way, silently blanking the string it returns.
        //
        // Substituting the invalid sequences with U+FFFD is exactly what a browser
        // does when it decodes the same bytes, so the detector goes on to inspect
        // the markup the parser will actually build, and no new false positives
        // appear for legitimately mis-encoded content.
        if (!preg_match('//u', $string)) {
            $previous = mb_substitute_character();
            mb_substitute_character(0xFFFD);
            $string = mb_convert_encoding($string, 'UTF-8', 'UTF-8');
            mb_substitute_character($previous);
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
            // Match any attribute starting with "on" (must be preceded by an
            // attribute boundary: whitespace, NUL, quote or slash). We deliberately
            // do NOT try to match the attribute value itself — the previous regex
            // required quotes-or-spaces around the `=` sign and was bypassed by
            // unquoted handlers like `<img src=x onerror=alert(1)>`
            // (GHSA-9695-8fr9-hw5q, also exploited by GHSA-c2q3-p4jr-c55f and
            // GHSA-w8cg-7jcj-4vv2). Detecting the attribute name + `=` is enough
            // for a tripwire; trade-off is occasional false positives when an
            // unrelated `on*=` substring appears inside another attribute's value.
            //
            // The tag-body scan is quote-aware: instead of `[^>]*?` (which stops
            // at the FIRST literal `>` and so never saw a handler hidden behind a
            // `>` embedded in a quoted attribute value, e.g.
            // `<img src=x title=">" onerror=alert(1)>` — GHSA-269c-h76q-8cxw), it
            // consumes quoted strings as whole units so a `>` inside quotes is
            // treated as data, exactly as the HTML parser treats it. Only a bare,
            // unquoted `>` terminates the tag body.
            //
            // The boundary before the handler name accepts either a real
            // separator char (whitespace/NUL/quote/slash) OR a complete quoted
            // attribute value, because a quoted value butted straight against the
            // next attribute needs no separator: after a quoted value the HTML
            // parser reconsumes any other char in the before-attribute-name state,
            // so `<img title="y"onerror=alert(1)>` still fires (GHSA-269c-h76q-8cxw
            // follow-up). Without the quoted-string alternative the `[...]` class
            // could never land on that adjacency because the `*?` consumes the
            // whole `"y"` unit and overshoots the closing quote.
            //
            // A quote opens a quoted-value state ONLY when it directly follows
            // `=`, exactly as the HTML tokenizer does. The GHSA-269c pattern
            // treated ANY quote as a delimiter, so a lone unpaired quote inside
            // an UNQUOTED value (`<img src=x" onerror=alert(1)>`) — a plain value
            // char to the browser — became an unterminated string the tag-body
            // scan could not advance past, hiding the following ` on...=` handler
            // and returning null. Anchoring the quoted-value alternatives to
            // `=\s*"..."` consumes such a quote as an ordinary char instead, so
            // the scan still reaches the handler. (GHSA-vfmf-q6x9-cw96)
            'on_events' => '#<(?:=\s*"[^"]*"|=\s*\'[^\']*\'|[^>])*?(?:[\s\x00-\x20\"\'\/]|=\s*"[^"]*"|=\s*\'[^\']*\')on\s*[a-z]+\s*=#iu',

            // xmlns namespace declarations. Split out from on_events (which it
            // historically shared a regex with) so the render-time output scan
            // can suppress it independently: every legitimate rendered inline
            // <svg xmlns=...> / <math xmlns=...> carries one, so leaving it on
            // for post-render HTML blanks pages that merely display an icon. It
            // stays on by default for raw-input sanitization (it follows the
            // on_events toggle below). Same quote-aware tag-body scan as on_events.
            'xmlns' => '#<(?:=\s*"[^"]*"|=\s*\'[^\']*\'|[^>])*?(?:[\s\x00-\x20\"\'\/]|=\s*"[^"]*"|=\s*\'[^\']*\')xmlns\s*=#iu',

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
                // Skip testing 'on_events'/'xmlns' against stripped version to avoid false
                // positives with tags like <caption>, <button>, <section> that end with 'on'
                // or contain 'on'
                if ($name === 'on_events' || $name === 'xmlns') {
                    if (static::patternMatches($regex, (string) $string) || static::patternMatches($regex, $orig)) {
                        return $name;
                    }
                } else {
                    if (static::patternMatches($regex, (string) $string) || static::patternMatches($regex, (string) $stripped) || static::patternMatches($regex, $orig)) {
                        return $name;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Run one detector pattern, failing closed.
     *
     * preg_match() has two falsy returns that a truthiness test cannot tell
     * apart: 0 ("no match") and false ("could not evaluate"). Reading the second
     * as the first is how the detector was made to pass a live payload, so every
     * pattern goes through here rather than being called inline.
     *
     * The failure left after detectXss() normalizes encoding is
     * PREG_JIT_STACKLIMIT_ERROR: the quote-aware tag-body scan added for
     * GHSA-269c-h76q-8cxw exhausts the JIT stack on a single tag body of roughly
     * 10KB or more, so `<img aaa...aaa onerror=alert(1)>` came back false and
     * read as "no XSS found" — with no invalid byte involved, so the JSON API's
     * incidental UTF-8 rejection does not catch it either. The interpreter
     * handles the same subject correctly, so retry once with the JIT off. PCRE
     * caches compiled patterns by their full string, hence the inert `(?:)`
     * prefix to force a recompile under the new setting (it must not contain the
     * delimiter).
     *
     * Anything still unanswerable (backtrack or recursion limits) counts as a
     * hit. This detector is a tripwire and is allowed false positives; it is not
     * allowed to report "clean" for a string it never actually examined.
     */
    private static function patternMatches(string $regex, string $subject): bool
    {
        $result = preg_match($regex, $subject);
        if ($result !== false) {
            return (bool) $result;
        }

        if (preg_last_error() === PREG_JIT_STACKLIMIT_ERROR) {
            $jit = ini_get('pcre.jit');
            ini_set('pcre.jit', '0');
            $result = preg_match($regex[0] . '(?:)' . substr($regex, 1), $subject);
            ini_set('pcre.jit', (string) $jit);
            if ($result !== false) {
                return (bool) $result;
            }
        }

        return true;
    }

    /**
     * Save-time XSS backstop for editor-authored content Twig (GHSA-2c4f-86xc-cr74).
     *
     * The blueprint validator (checkSafety) only inspects the raw page source,
     * so a payload assembled at render time — `{{ "on" ~ "error" }}`,
     * `<s{{ "c"~"r"~"i"~"p"~"t" }}>` — passes it and then resolves to live
     * markup. This renders the sandboxed content-Twig pass on the raw content in
     * isolation and runs the detector on the result, so an assembled tag/attr is
     * caught before the page is ever stored.
     *
     * Enforced at save (PageObject::onBeforeSave and the API validator), not at
     * render. By construction it only ever sees the editor's own output: no
     * shortcodes or plugin `onPageContent*` listeners have run, so trusted
     * plugin/theme markup can never trip it and there is no per-request cost or
     * output-blanking. Superadmins (security.xss_whitelist) are exempt, matching
     * Validation::checkSafety on the raw source.
     *
     * @param string $rawContent Raw editor-authored page body (pre-render).
     * @param PageInterface $page The page being saved (render + gate context).
     * @return string|null Rule name that fired, or null if clean / not applicable.
     */
    public static function detectXssInEditorContent(string $rawContent, PageInterface $page): ?string
    {
        if ($rawContent === '') {
            return null;
        }

        /** @var Config $config */
        $config = Grav::instance()['config'];

        // Only content that Twig will actually process at render time can carry a
        // render-time-assembled payload; anything else the raw-source validator
        // already covers. Mirrors the render-time decision exactly, including the
        // modular branch, so this check can never end up stricter than the render
        // it is meant to protect. (GHSA-fg8g-663r-f366)
        if (!static::willProcessContentTwig($page)) {
            return null;
        }

        // Mirror checkSafety's trust boundary: a user who may store literal
        // dangerous markup (superadmin, per security.xss_whitelist) is not blocked
        // from storing the assembled equivalent.
        $user = Grav::instance()['user'] ?? null;
        if (Validation::authorize($config->get('security.xss_whitelist', 'admin.super'), $user)) {
            return null;
        }

        // Render the editor Twig in isolation through the same sandboxed path used
        // at display time. No content events are fired, so the output contains the
        // editor's markup and nothing else. A render/sandbox failure is not an XSS
        // verdict — the raw-source validator still ran — so fail open on throw.
        try {
            $twig = Grav::instance()['twig'];
            // A JSON API save may never have rendered a page, so the Twig
            // environment isn't built yet; init() is idempotent (no-op once set).
            $twig->init();
            // Body only. A module's trusted modular template is theme-authored,
            // not editor input, so rendering it here would let an inline event
            // handler in a theme's own markup fail an otherwise clean save.
            $rendered = $twig->processPage($page, $rawContent, false);
        } catch (\Throwable) {
            return null;
        }

        return is_string($rendered) ? static::detectXss($rendered) : null;
    }

    /**
     * Will Grav process editor-authored Twig in this page's content at render time?
     *
     * Single source of truth for that decision, shared by the two render paths
     * (Page::content(), PageContentTrait::processContent()) and by the save-time
     * guard in detectXssInEditorContent(). Modules render their body Twig
     * unconditionally — a modular template is theme-controlled and renders its
     * children with Twig — so they ignore the security.twig_content.process_enabled
     * gate. Keeping the boolean in one place stops the save-time check drifting
     * stricter than the render, which is what let a module store an assembled
     * payload unchecked while the gate was off. (GHSA-fg8g-663r-f366)
     *
     * @param PageInterface $page
     * @return bool
     */
    public static function willProcessContentTwig(PageInterface $page): bool
    {
        /** @var Config $config */
        $config = Grav::instance()['config'];
        $gate = (bool) $config->get('security.twig_content.process_enabled', false);

        return ($gate && $page->shouldProcess('twig')) || $page->isModule();
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
     * Stream URI of the file-backed ring buffer of recent Twig-content security
     * events. Lives under log:// (the logs/ folder) so it survives
     * `bin/grav clear` and cache TTL eviction, unlike anything in cache://.
     */
    private const TWIG_CONTENT_EVENTS_URI = 'log://twig-content-events.json';

    /** @var int How many recent Twig-content events the ring buffer retains. */
    private const TWIG_CONTENT_EVENTS_CAP = 50;

    /**
     * Build (or return cached) Twig sandbox SecurityPolicy from security.twig_sandbox.* config.
     * Cached per-request, invalidated when the config hash changes.
     */
    public static function buildTwigSandboxPolicy(): SecurityPolicyInterface
    {
        /** @var Config $config */
        $config = Grav::instance()['config'];

        // Effective allowlists = built-in defaults (SandboxDefaults, in code)
        // UNION the user's additive `security.twig_sandbox.allowed_*` entries.
        // The defaults live in code, not YAML, so a site's user config can only
        // WIDEN the policy and can never silently freeze a core entry against a
        // later security tightening (see SandboxDefaults). Tightening below the
        // defaults is explicit via `denied_*`, applied after normalization below.
        //
        // The friendly shapes here — flat lists for tags/filters/functions, the
        // list-of-rows shape for methods/properties — are exactly what the
        // onBuildTwigSandboxPolicy event hands to plugins, so a plugin appends
        // entries the same way they're written in security.yaml.
        $rawTags       = self::mergeSandboxAllow('tags', $config);
        $rawFilters    = self::mergeSandboxAllow('filters', $config);
        $rawFunctions  = self::mergeSandboxAllow('functions', $config);
        $rawMethods    = self::mergeSandboxAllow('methods', $config);
        $rawProperties = self::mergeSandboxAllow('properties', $config);
        $configAccess  = (bool) $config->get('security.twig_content.config_access', false);

        // denied_* entries win over defaults, user additions, AND plugin event
        // additions, so they are captured for the cache key and applied last.
        $deniedTags       = (array) ($config->get('security.twig_sandbox.denied_tags', []) ?? []);
        $deniedFilters    = (array) ($config->get('security.twig_sandbox.denied_filters', []) ?? []);
        $deniedFunctions  = (array) ($config->get('security.twig_sandbox.denied_functions', []) ?? []);
        $deniedMethods    = (array) ($config->get('security.twig_sandbox.denied_methods', []) ?? []);
        $deniedProperties = (array) ($config->get('security.twig_sandbox.denied_properties', []) ?? []);

        $cacheKey = md5(serialize([
            $rawTags, $rawFilters, $rawFunctions, $rawMethods, $rawProperties, $configAccess,
            $deniedTags, $deniedFilters, $deniedFunctions, $deniedMethods, $deniedProperties,
        ]));
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

        // Explicit tightening: `denied_*` removes members regardless of where
        // they came from (default, user addition, or plugin event). This is the
        // supported way to tighten below the shipped defaults now that the
        // defaults live in code — deleting a line from user config no longer
        // works, because the default is re-supplied from SandboxDefaults.
        $tags       = self::subtractStringList($tags, $deniedTags);
        $filters    = self::subtractStringList($filters, $deniedFilters);
        $functions  = self::subtractStringList($functions, $deniedFunctions);
        $methods    = self::subtractMethodsMap($methods, $deniedMethods, true);
        $properties = self::subtractMethodsMap($properties, $deniedProperties, false);

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
     * Merge the built-in default allowlist for a sandbox list `$type` with the
     * user's additive `security.twig_sandbox.allowed_{$type}` entries. Returns
     * the raw as-authored shape (flat list for tags/filters/functions, the
     * list-of-rows shape for methods/properties) so the merged result can flow
     * straight into the onBuildTwigSandboxPolicy event and the existing
     * normalizers. Defaults come first so their comments/order are preserved;
     * de-duplication happens later during normalization.
     *
     * @param string $type One of tags|filters|functions|methods|properties.
     * @return array<int,mixed>
     */
    private static function mergeSandboxAllow(string $type, ?Config $config): array
    {
        $defaults = SandboxDefaults::all()[$type] ?? [];
        $user = (array) ($config?->get("security.twig_sandbox.allowed_{$type}", []) ?? []);

        return array_merge($defaults, $user);
    }

    /**
     * Effective flat allowlist (tags|filters|functions) after defaults ∪ user
     * additions − user denials. Plugin event additions are intentionally NOT
     * included: this feeds the informational "Twig in Content" scan, which
     * mirrors what an operator can see in config, and matches the pre-audit
     * behaviour of reading the config lists directly.
     *
     * @return list<string>
     */
    public static function effectiveSandboxList(string $type, ?Config $config = null): array
    {
        if ($config === null) {
            try {
                $config = Grav::instance()['config'];
            } catch (Exception) {
                $config = null;
            }
        }

        $union = self::mergeSandboxAllow($type, $config);
        $denied = (array) ($config?->get("security.twig_sandbox.denied_{$type}", []) ?? []);

        // Defaults and a subset user list overlap, so de-dup (case-insensitively,
        // first spelling wins) to return a proper set.
        $out = [];
        $seen = [];
        foreach (self::subtractStringList(self::normalizeStringList($union), $denied) as $member) {
            $lc = strtolower($member);
            if (!isset($seen[$lc])) {
                $seen[$lc] = true;
                $out[] = $member;
            }
        }

        return $out;
    }

    /**
     * Effective config-redaction prefixes = built-in defaults ∪ the user's
     * additive `security.twig_sandbox.config_denied_paths`. Like the allowlists,
     * the defaults live in code so user config can only ADD paths to redact,
     * never silently un-redact a shipped one by editing the list.
     *
     * @return list<string>
     */
    public static function effectiveConfigDeniedPaths(?Config $config = null): array
    {
        if ($config === null) {
            try {
                $config = Grav::instance()['config'];
            } catch (Exception) {
                $config = null;
            }
        }

        $user = (array) ($config?->get('security.twig_sandbox.config_denied_paths', []) ?? []);
        $out = [];
        foreach (array_merge(SandboxDefaults::configDeniedPaths(), $user) as $path) {
            if (is_string($path) && $path !== '' && !in_array($path, $out, true)) {
                $out[] = $path;
            }
        }

        return $out;
    }

    /**
     * Upgrade planner: given a site's existing `security.twig_sandbox` USER
     * config (the subtree from user/config/security.yaml), compute the `denied_*`
     * additions needed so the new additive-defaults model reproduces the site's
     * EXACT pre-upgrade effective policy.
     *
     * Before the 2026-08-12 audit, the default allowlists lived inline in the
     * shipped security.yaml and Grav merges leaf lists by REPLACEMENT, so a site
     * that wrote its own `allowed_*` list replaced the defaults wholesale — its
     * effective policy was exactly that list. Now the defaults live in code and
     * `allowed_*` is additive, so on upgrade those defaults would silently come
     * back. Denying precisely the defaults the site's list OMITTED restores the
     * old effective set with zero behaviour change: anything that was blocked
     * before stays blocked, anything allowed before stays allowed.
     *
     * Pure and side-effect free so the installer step stays thin and this is
     * unit-testable. Returns only the `denied_*` keys that need entries; a site
     * that never customised the allowlists yields an empty array (no-op).
     *
     * @param array<string,mixed> $userSandbox The user's `security.twig_sandbox` subtree.
     * @return array<string, array<int,mixed>> denied_<type> => additions.
     */
    public static function planSandboxDefaultsMigration(array $userSandbox): array
    {
        $out = [];
        $defaults = SandboxDefaults::all();

        foreach (['tags', 'filters', 'functions'] as $type) {
            if (!isset($userSandbox["allowed_{$type}"]) || !is_array($userSandbox["allowed_{$type}"])) {
                continue; // list untouched → additive default already reproduces it
            }
            $userSet = self::lowerSet($userSandbox["allowed_{$type}"]);
            $missing = [];
            foreach ($defaults[$type] as $member) {
                if (!isset($userSet[strtolower($member)])) {
                    $missing[] = $member;
                }
            }
            if ($missing) {
                $out["denied_{$type}"] = $missing;
            }
        }

        foreach (['methods' => true, 'properties' => false] as $type => $lowercase) {
            if (!isset($userSandbox["allowed_{$type}"]) || !is_array($userSandbox["allowed_{$type}"])) {
                continue;
            }
            $defMap = self::normalizeMethodsMap($defaults[$type], $lowercase);
            $userMap = self::normalizeMethodsMap($userSandbox["allowed_{$type}"], $lowercase);
            $rows = [];
            foreach ($defMap as $class => $defMembers) {
                $missing = array_values(array_diff($defMembers, $userMap[$class] ?? []));
                if ($missing) {
                    $rows[] = ['class' => $class, 'methods' => implode(', ', $missing)];
                }
            }
            if ($rows) {
                $out["denied_{$type}"] = $rows;
            }
        }

        return $out;
    }

    /**
     * Describe the effective Twig-sandbox policy for a read-only admin view:
     * for each list, the built-in defaults (from code), the user's additive
     * `allowed_*` entries, the user's `denied_*` tightenings, and the resulting
     * effective set. Config-derived only — it deliberately omits plugin
     * `onBuildTwigSandboxPolicy` additions and the `config_access` strip, so it
     * shows what an operator controls through configuration, matching the
     * "Twig in Content" diagnostic. Not used to build the live policy.
     *
     * @return array<string,mixed>
     */
    public static function describeEffectiveSandbox(?Config $config = null): array
    {
        if ($config === null) {
            try {
                $config = Grav::instance()['config'];
            } catch (Exception) {
                $config = null;
            }
        }

        $defaults = SandboxDefaults::all();
        $out = [
            'enabled' => (bool) ($config?->get('security.twig_sandbox.enabled', true) ?? true),
            'config_access' => (bool) ($config?->get('security.twig_content.config_access', false) ?? false),
            'lists' => [],
        ];

        foreach (['tags', 'filters', 'functions'] as $type) {
            $out['lists'][$type] = [
                'defaults' => $defaults[$type],
                'added' => array_values(self::normalizeStringList((array) ($config?->get("security.twig_sandbox.allowed_{$type}", []) ?? []))),
                'denied' => array_values(self::normalizeStringList((array) ($config?->get("security.twig_sandbox.denied_{$type}", []) ?? []))),
                'effective' => self::effectiveSandboxList($type, $config),
            ];
        }

        foreach (['methods' => true, 'properties' => false] as $type => $lowercase) {
            $added = (array) ($config?->get("security.twig_sandbox.allowed_{$type}", []) ?? []);
            $denied = (array) ($config?->get("security.twig_sandbox.denied_{$type}", []) ?? []);
            $effective = self::subtractMethodsMap(
                self::normalizeMethodsMap(array_merge($defaults[$type], $added), $lowercase),
                $denied,
                $lowercase
            );
            $out['lists'][$type] = [
                'defaults' => self::normalizeMethodsMap($defaults[$type], $lowercase),
                'added' => self::normalizeMethodsMap($added, $lowercase),
                'denied' => self::normalizeMethodsMap($denied, $lowercase),
                'effective' => $effective,
            ];
        }

        $out['config_denied_paths'] = [
            'defaults' => SandboxDefaults::configDeniedPaths(),
            'added' => array_values(self::normalizeStringList((array) ($config?->get('security.twig_sandbox.config_denied_paths', []) ?? []))),
            'effective' => self::effectiveConfigDeniedPaths($config),
        ];

        return $out;
    }

    /**
     * Remove every member named in $denied (case-insensitively) from a flat
     * normalized allowlist.
     *
     * @param list<string> $list
     * @param array<int,mixed> $denied
     * @return list<string>
     */
    private static function subtractStringList(array $list, array $denied): array
    {
        if (!$denied) {
            return $list;
        }
        $deniedSet = self::lowerSet($denied);
        $out = [];
        foreach ($list as $v) {
            if (!isset($deniedSet[strtolower($v)])) {
                $out[] = $v;
            }
        }

        return $out;
    }

    /**
     * Remove denied members from a normalized class => [members] allowlist map.
     * A denied row of `'*'` (or containing `*`) removes the whole class. This
     * runs after normalization so it also overrides plugin event additions.
     *
     * @param array<class-string, list<string>> $map
     * @param array<int,mixed> $deniedRows
     * @return array<class-string, list<string>>
     */
    private static function subtractMethodsMap(array $map, array $deniedRows, bool $lowercase): array
    {
        if (!$deniedRows) {
            return $map;
        }
        $denied = self::normalizeMethodsMap($deniedRows, $lowercase);
        foreach ($denied as $class => $members) {
            if (!isset($map[$class])) {
                continue;
            }
            if (in_array('*', $members, true)) {
                unset($map[$class]);
                continue;
            }
            $remove = $lowercase ? array_map('strtolower', $members) : $members;
            $map[$class] = array_values(array_diff($map[$class], $remove));
            if (!$map[$class]) {
                unset($map[$class]);
            }
        }

        return $map;
    }

    /**
     * Log a Twig sandbox violation via the security log channel. Called from the
     * SecurityError handler in Twig::processPage() / processString().
     */
    public static function logTwigSandboxViolation(string $rule, string $token, string $className = '', string $extra = ''): void
    {
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

            // Mirror the event into the structured ring buffer the Admin reads.
            self::recordTwigContentEvent('sandbox_' . $rule, $route, $token, $className, $hint);
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
     * Normalize a configured `process` array to Grav's field defaults before it
     * drives content rendering: ensure `markdown` is present (defaulting to on,
     * Grav's default) and default `twig` from the security gate via
     * applyTwigContentDefault(). An explicit value for either key always wins.
     *
     * This guards a config-merge foot-gun: a PARTIAL `system.pages.process`
     * override (e.g. just `twig: false`) replaces the whole map at merge time —
     * the blueprint models `process` as a single `checkboxes` field, so it's
     * replaced wholesale rather than deep-merged — which silently drops the
     * default `markdown: true` and leaves every page rendering raw Markdown
     * source. Re-injecting the markdown default here heals such a site purely
     * from core, with no edit to the site's YAML. Per-page `process` frontmatter
     * is layered on top of this by the page classes, so it still overrides.
     *
     * @param array<string,mixed> $process Configured process array (may be empty).
     * @return array<string,mixed> Same array with `markdown` and `twig` defaulted when unset.
     */
    public static function applyProcessDefaults(array $process): array
    {
        if (!array_key_exists('markdown', $process)) {
            $process['markdown'] = true;
        }

        return self::applyTwigContentDefault($process);
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
     * The named "Twig in Content" profiles. These collapse the two underlying
     * security.twig_content keys (process_enabled = the master gate;
     * editor_enabled = whether any editor may opt a page in, vs. only super /
     * admin.pages_twig holders) into one human choice. `custom` is not a stored
     * value — it's the label for any flag combination a named profile can't
     * represent (today: gate off while editor_enabled is on).
     */
    public const TWIG_CONTENT_PROFILE_OFF = 'off';
    public const TWIG_CONTENT_PROFILE_TRUSTED = 'trusted';
    public const TWIG_CONTENT_PROFILE_ALL = 'all';
    public const TWIG_CONTENT_PROFILE_CUSTOM = 'custom';

    /**
     * Derive the named profile from the two underlying flags.
     *
     *   process=false, editor=false → off
     *   process=true,  editor=false → trusted (super / admin.pages_twig only)
     *   process=true,  editor=true  → all (any editor may enable Twig per page)
     *   process=false, editor=true  → custom (the gate dominates, so editor=true
     *                                  is inert — not a state a named profile sets)
     */
    public static function twigContentProfileFromFlags(bool $processEnabled, bool $editorEnabled): string
    {
        if (!$processEnabled) {
            return $editorEnabled ? self::TWIG_CONTENT_PROFILE_CUSTOM : self::TWIG_CONTENT_PROFILE_OFF;
        }

        return $editorEnabled ? self::TWIG_CONTENT_PROFILE_ALL : self::TWIG_CONTENT_PROFILE_TRUSTED;
    }

    /**
     * The current profile, computed from live config. Drives the admin profile
     * selector's displayed value (data-default@-style resolver).
     */
    public static function twigContentProfile(): string
    {
        try {
            $config = Grav::instance()['config'];
            return self::twigContentProfileFromFlags(
                (bool) $config->get('security.twig_content.process_enabled', false),
                (bool) $config->get('security.twig_content.editor_enabled', false)
            );
        } catch (\Throwable) {
            return self::TWIG_CONTENT_PROFILE_OFF;
        }
    }

    /**
     * The profile options to show. The three named profiles are always offered;
     * `custom` is appended only when the live config is in a custom state, so
     * the selector can show (and preserve) it without inviting users to pick it.
     *
     * @return array<string,string> profile key => human label
     */
    public static function twigContentProfileOptions(): array
    {
        $options = [
            self::TWIG_CONTENT_PROFILE_OFF     => 'Off',
            self::TWIG_CONTENT_PROFILE_TRUSTED => 'Trusted roles only',
            self::TWIG_CONTENT_PROFILE_ALL     => 'All editors',
        ];

        if (self::twigContentProfile() === self::TWIG_CONTENT_PROFILE_CUSTOM) {
            $options[self::TWIG_CONTENT_PROFILE_CUSTOM] = 'Custom';
        }

        return $options;
    }

    /**
     * The {process_enabled, editor_enabled} a named profile expands to, or null
     * for `custom` (which is never written — the underlying keys are left as-is,
     * per the plan's BC rule: a hand-edited odd combo is preserved, not rewritten).
     *
     * @return array{process_enabled:bool,editor_enabled:bool}|null
     */
    public static function twigContentFlagsForProfile(string $profile): ?array
    {
        return match ($profile) {
            self::TWIG_CONTENT_PROFILE_OFF     => ['process_enabled' => false, 'editor_enabled' => false],
            self::TWIG_CONTENT_PROFILE_TRUSTED => ['process_enabled' => true,  'editor_enabled' => false],
            self::TWIG_CONTENT_PROFILE_ALL     => ['process_enabled' => true,  'editor_enabled' => true],
            default => null,
        };
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

            $hint = 'Enable security.twig_content.process_enabled to allow Twig processing in page content.';

            $grav['log.security']->warning(
                sprintf('[TwigContentGate] blocked source=%s route=%s', $source, $route),
                [
                    'source' => $source,
                    'route'  => $route,
                    'hint'   => $hint,
                ]
            );

            // Mirror the event into the structured ring buffer the Admin reads.
            // `token` carries the source (content|frontmatter) so the report can
            // distinguish a body-content gate hit from a frontmatter one.
            self::recordTwigContentEvent('gate_blocked', $route, $source, '', $hint);
        } catch (Exception) {
            // Never let a logging failure break rendering.
        }
    }

    /**
     * Append a structured Twig-content security event to the file-backed ring
     * buffer that the Admin "Twig in Content" report reads. Written alongside
     * (never instead of) the human-readable log.security line so the audit
     * trail in logs/security.log is preserved.
     *
     * The buffer is a small capped JSON array, newest-first. Each write does a
     * read → prepend → truncate-to-cap → atomic publish (tmp + rename) under an
     * advisory file lock so concurrent renders don't clobber each other.
     *
     * @param string $type  One of gate_blocked|sandbox_{tag,filter,function,method,property}|xss_blanked.
     * @param string $route Route of the offending page.
     * @param string $token The blocked token (tag/filter/function/method/property name, XSS marker, or gate source).
     * @param string $class Owning class name for method/property rules; '' otherwise.
     * @param string $hint  Plain-text remediation hint (the same string logged to security.log).
     */
    private static function recordTwigContentEvent(string $type, string $route, string $token, string $class, string $hint): void
    {
        try {
            $grav = Grav::instance();
            if (!$grav->offsetExists('locator')) {
                return;
            }
            /** @var UniformResourceLocator $locator */
            $locator = $grav['locator'];
            $file = $locator->findResource(self::TWIG_CONTENT_EVENTS_URI, true, true);
            if (!is_string($file) || $file === '') {
                return;
            }

            // The log:// directory may not exist yet on a fresh site; this can
            // be the first thing to write there.
            $dir = dirname($file);
            if (!is_dir($dir)) {
                Folder::mkdir($dir);
            }

            $record = [
                'type'      => $type,
                'route'     => $route,
                'token'     => $token,
                'class'     => $class,
                'hint'      => $hint,
                'timestamp' => time(),
            ];

            // Serialize the read-modify-write across concurrent requests with an
            // advisory lock on a sidecar file, then publish via atomic rename.
            $lock = @fopen($file . '.lock', 'c');
            if ($lock === false) {
                return;
            }
            try {
                @flock($lock, LOCK_EX);

                $events = [];
                if (is_file($file)) {
                    $raw = @file_get_contents($file);
                    if (is_string($raw) && $raw !== '') {
                        $decoded = json_decode($raw, true);
                        if (is_array($decoded)) {
                            $events = $decoded;
                        }
                    }
                }

                array_unshift($events, $record);
                if (count($events) > self::TWIG_CONTENT_EVENTS_CAP) {
                    $events = array_slice($events, 0, self::TWIG_CONTENT_EVENTS_CAP);
                }

                $json = json_encode($events, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                if ($json !== false) {
                    $tmp = $file . '.tmp';
                    if (@file_put_contents($tmp, $json) !== false) {
                        if (!@rename($tmp, $file)) {
                            @unlink($tmp);
                        }
                    }
                }
            } finally {
                @flock($lock, LOCK_UN);
                @fclose($lock);
            }
        } catch (\Throwable) {
            // Diagnostics must never break rendering.
        }
    }

    /**
     * Return the recent Twig-content security events from the ring buffer,
     * newest first. Consumed by the api plugin's "Twig in Content" report.
     *
     * @param int $limit Cap the number returned; 0 returns all retained events.
     * @return array<int,array{type:string,route:string,token:string,class:string,hint:string,timestamp:int}>
     */
    public static function recentTwigContentEvents(int $limit = 0): array
    {
        try {
            $grav = Grav::instance();
            if (!$grav->offsetExists('locator')) {
                return [];
            }
            /** @var UniformResourceLocator $locator */
            $locator = $grav['locator'];
            // Resolve via the same (absolute, first) lookup the writer uses so a
            // negatively-cached "not found" from an earlier absent-file read
            // can't mask a file the writer has since created.
            $file = $locator->findResource(self::TWIG_CONTENT_EVENTS_URI, true, true);
            if (!is_string($file) || !is_file($file)) {
                return [];
            }
            $raw = @file_get_contents($file);
            if (!is_string($raw) || $raw === '') {
                return [];
            }
            $events = json_decode($raw, true);
            if (!is_array($events)) {
                return [];
            }
            if ($limit > 0 && count($events) > $limit) {
                $events = array_slice($events, 0, $limit);
            }

            return array_values($events);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Clear the Twig-content events ring buffer. Used after the operator has
     * resolved the flagged issues (e.g. via the Admin report's dismiss action).
     *
     * @return bool True if the buffer file was removed or already absent.
     */
    public static function clearTwigContentEvents(): bool
    {
        try {
            $grav = Grav::instance();
            if (!$grav->offsetExists('locator')) {
                return false;
            }
            /** @var UniformResourceLocator $locator */
            $locator = $grav['locator'];
            $file = $locator->findResource(self::TWIG_CONTENT_EVENTS_URI, true, true);
            if (!is_string($file) || !is_file($file)) {
                return true;
            }

            return @unlink($file);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Remove ring-buffer events that an allowlist addition has just resolved, so
     * the Admin report no longer shows a block the operator has already fixed.
     * Matches on the violation type (`sandbox_{$rule}`) and token; method/property
     * rules also match the owning class. Comparisons are case-insensitive to
     * mirror Twig's member matching (and PHP's case-insensitive class names).
     *
     * @param string $rule  tag|filter|function|method|property (the `sandbox_` suffix).
     * @param string $token The allowed token (tag/filter/function/method/property name).
     * @param string $class Owning class for method/property rules; '' for the flat lists.
     * @return int Number of events removed.
     */
    public static function resolveTwigContentEvents(string $rule, string $token, string $class = ''): int
    {
        if ($rule === '' || $token === '') {
            return 0;
        }

        try {
            $grav = Grav::instance();
            if (!$grav->offsetExists('locator')) {
                return 0;
            }
            /** @var UniformResourceLocator $locator */
            $locator = $grav['locator'];
            $file = $locator->findResource(self::TWIG_CONTENT_EVENTS_URI, true, true);
            if (!is_string($file) || !is_file($file)) {
                return 0;
            }

            $type = 'sandbox_' . $rule;

            // Serialize the read-modify-write with the same advisory lock the
            // writer uses, then publish atomically via rename.
            $lock = @fopen($file . '.lock', 'c');
            if ($lock === false) {
                return 0;
            }
            $removed = 0;
            try {
                @flock($lock, LOCK_EX);

                $raw = @file_get_contents($file);
                if (!is_string($raw) || $raw === '') {
                    return 0;
                }
                $events = json_decode($raw, true);
                if (!is_array($events)) {
                    return 0;
                }

                $kept = [];
                foreach ($events as $event) {
                    $matches = is_array($event)
                        && ($event['type'] ?? '') === $type
                        && strcasecmp((string) ($event['token'] ?? ''), $token) === 0
                        && ($class === '' || strcasecmp((string) ($event['class'] ?? ''), $class) === 0);
                    if ($matches) {
                        $removed++;
                        continue;
                    }
                    $kept[] = $event;
                }

                if ($removed === 0) {
                    return 0;
                }

                if ($kept === []) {
                    @unlink($file);
                } else {
                    $json = json_encode(array_values($kept), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                    if ($json !== false) {
                        $tmp = $file . '.tmp';
                        if (@file_put_contents($tmp, $json) !== false) {
                            if (!@rename($tmp, $file)) {
                                @unlink($tmp);
                            }
                        }
                    }
                }
            } finally {
                @flock($lock, LOCK_UN);
                @fclose($lock);
            }

            return $removed;
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Find pages whose content contains raw Twig markers (`{{` / `{%`) that
     * will NOT be rendered — i.e. they would leak verbatim to visitors. A page
     * leaks when it has markers and is non-modular and its effective content
     * Twig is off: either the per-page request flag is off, OR the master gate
     * (security.twig_content.process_enabled) is off. Mirrors the traversal of
     * detectXssFromPages().
     *
     * @param Pages $pages
     * @param callable|null $status Optional progress callback (count/progress).
     * @return array<string,array{route:string,requested:bool,gate:bool,reason:string}>
     *         Keyed by raw route. reason is 'gate_off' when the master gate is
     *         off (the dominant cause), else 'page_off'.
     */
    public static function detectTwigLeaksFromPages(Pages $pages, ?callable $status = null): array
    {
        $gateEnabled = false;
        try {
            $gateEnabled = (bool) Grav::instance()['config']->get('security.twig_content.process_enabled', false);
        } catch (Exception) {
            // Treat an unreadable config as gate-off.
        }

        $routes = $pages->getList(null, 0, true);
        unset($routes['/']);

        $list = [];

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
                if (!$page || !$page->exists()) {
                    continue;
                }

                $leak = self::detectTwigLeakForPage($page, $gateEnabled);
                if ($leak !== null) {
                    $list[$page->rawRoute()] = $leak;
                }
            } catch (Exception) {
                continue;
            }
        }

        return $list;
    }

    /**
     * Decide whether a single page would leak raw Twig markers to visitors, and
     * why. Shared by detectTwigLeaksFromPages() (whole-site scan) and the api
     * plugin's per-page editor banner. Returns null when the page is fine —
     * no markers, modular/theme Twig, or its content Twig will actually render.
     *
     * @param PageInterface|mixed $page A page object exposing header()/value()/
     *        modularTwig()/shouldProcess()/route()/rawRoute().
     * @param bool|null $gateEnabled Pass the resolved master-gate value to avoid
     *        re-reading config per page; null reads it from config.
     * @return array{route:string,requested:bool,gate:bool,reason:string}|null
     */
    public static function detectTwigLeakForPage($page, ?bool $gateEnabled = null): ?array
    {
        if ($gateEnabled === null) {
            $gateEnabled = false;
            try {
                $gateEnabled = (bool) Grav::instance()['config']->get('security.twig_content.process_enabled', false);
            } catch (Exception) {
                // Treat an unreadable config as gate-off.
            }
        }

        if (!$page || !method_exists($page, 'value')) {
            return null;
        }

        // Populate the page's process array (gate default applied) the same way
        // detectXssFromPages() warms content.
        if (method_exists($page, 'header')) {
            $page->header();
        }
        $content = (string) $page->value('content');

        if (!str_contains($content, '{{') && !str_contains($content, '{%')) {
            return null;
        }

        // Modular/theme Twig bypasses the gate and renders normally, so it never
        // leaks; skip it.
        if (method_exists($page, 'modularTwig') && $page->modularTwig()) {
            return null;
        }

        // Content Twig renders only when BOTH the per-page request flag AND the
        // master gate are on; either off leaks the raw markers.
        $requested = (bool) $page->shouldProcess('twig');
        if ($requested && $gateEnabled) {
            return null;
        }

        $route = method_exists($page, 'route') ? $page->route() : null;
        $rawRoute = method_exists($page, 'rawRoute') ? $page->rawRoute() : null;

        return [
            'route'     => (string) ($route ?? $rawRoute ?? 'unknown'),
            'requested' => $requested,
            'gate'      => $gateEnabled,
            'reason'    => $gateEnabled ? 'page_off' : 'gate_off',
        ];
    }

    /**
     * Heuristically extract the Twig tags, filters, functions, and object-method
     * calls referenced in a chunk of page content. Used by the Admin "scan
     * content for Twig the sandbox will block" action and by the migrate-grav
     * allowlist suggester (getgrav/grav-plugin-migrate-grav#11) so both produce
     * identical results.
     *
     * This is a lexical approximation (regex over the `{{ }}` / `{% %}` islands),
     * not a full parse: it can over-report (a function-like name inside a string
     * literal) and under-report (dynamically-built names). It is only ever used
     * to *suggest* review — the precise, authoritative signal is the render-time
     * sandbox block captured in recentTwigContentEvents(). Never wire a
     * one-click allowlist add directly off this output without review.
     *
     * `methods` are the `obj.name(...)` calls (e.g. the `page.media['x'].cropResize(..)`
     * media chain) that feed security.twig_sandbox.allowed_methods; locally
     * declared/imported macro names are excluded from `functions`.
     *
     * @return array{tags:list<string>,filters:list<string>,functions:list<string>,methods:list<string>}
     */
    public static function extractTwigTokens(string $content): array
    {
        $tags = [];
        $filters = [];
        $functions = [];
        $methods = [];

        // Macro names declared (or imported) in this content are local callables,
        // not sandbox-checked functions; collect them so they're excluded below.
        $macros = [];
        if (preg_match_all('/\{%-?\s*macro\s+([a-zA-Z_]\w*)\s*\(/', $content, $mm)) {
            foreach ($mm[1] as $name) {
                $macros[strtolower($name)] = true;
            }
        }
        if (preg_match_all('/\{%-?\s*from\b[^%]*\bimport\b([^%]*)%\}/', $content, $im)) {
            foreach ($im[1] as $clause) {
                if (preg_match_all('/[a-zA-Z_]\w*/', $clause, $names)) {
                    foreach ($names[0] as $name) {
                        if (strtolower($name) !== 'as') {
                            $macros[strtolower($name)] = true;
                        }
                    }
                }
            }
        }

        // Pull out every Twig island: {{ ... }} and {% ... %} (and {%- -%}).
        if (preg_match_all('/\{\{(.*?)\}\}|\{%-?(.*?)-?%\}/s', $content, $islands, PREG_SET_ORDER)) {
            foreach ($islands as $island) {
                $isStatement = ($island[2] ?? '') !== '' || str_starts_with($island[0], '{%');
                $expr = $island[1] !== '' ? $island[1] : ($island[2] ?? '');

                // Statement tag name: first word after {% .
                if ($isStatement && preg_match('/^\s*(\w+)/', $expr, $m)) {
                    $tags[] = strtolower($m[1]);
                }

                // Filters: `| name` (optionally `| name(...)`). Excludes `||`.
                if (preg_match_all('/\|\s*(\w+)/', $expr, $fm)) {
                    foreach ($fm[1] as $name) {
                        $filters[] = $name;
                    }
                }

                // Functions: `name(` not preceded by a member/`|`/word char, so
                // `page.media(` (method) and `x|filter(` (filter args) are skipped.
                if (preg_match_all('/(?<![\w.|])([a-zA-Z_]\w*)\s*\(/', $expr, $cm)) {
                    foreach ($cm[1] as $name) {
                        if (!isset($macros[strtolower($name)])) {
                            $functions[] = $name;
                        }
                    }
                }

                // Object methods: `.name(` — the member-call idiom the function
                // capture deliberately skips. Seeds allowed_methods.
                if (preg_match_all('/\.([a-zA-Z_]\w*)\s*\(/', $expr, $om)) {
                    foreach ($om[1] as $name) {
                        $methods[] = $name;
                    }
                }
            }
        }

        return [
            'tags'      => array_values(array_unique($tags)),
            'filters'   => array_values(array_unique($filters)),
            'methods'   => array_values(array_unique($methods)),
            'functions' => array_values(array_unique($functions)),
        ];
    }

    /**
     * Scan all page content for Twig tags/filters/functions that the sandbox
     * allowlists do NOT currently permit — i.e. constructs that will be blocked
     * if/when the page runs editor Twig. Informational: it shows operators what
     * their content needs before they enable the gate, complementing the precise
     * render-time blocks in recentTwigContentEvents().
     *
     * @param Pages $pages
     * @param callable|null $status Optional progress callback (count/progress).
     * @return array{
     *   tags:array<string,list<string>>,
     *   filters:array<string,list<string>>,
     *   functions:array<string,list<string>>
     * } Each map is token => list of routes using it.
     */
    public static function scanContentTwigUsage(Pages $pages, ?callable $status = null): array
    {
        $config = null;
        try {
            $config = Grav::instance()['config'];
        } catch (Exception) {
            // No config → treat every used token as not-allowed.
        }

        // Effective lists = built-in defaults ∪ user additions − user denials.
        // Reading the config keys alone would report every default-allowed token
        // as "not allowed" now that the defaults live in code (SandboxDefaults).
        $allowedTags = self::lowerSet(self::effectiveSandboxList('tags', $config));
        $allowedFilters = self::lowerSet(self::effectiveSandboxList('filters', $config));
        $allowedFunctions = self::lowerSet(self::effectiveSandboxList('functions', $config));

        // Tags Twig always provides that are never sandbox-checked / always safe.
        $structuralTags = ['endif', 'else', 'elseif', 'endfor', 'endblock', 'endset', 'endmacro', 'endapply', 'endautoescape', 'endembed', 'endfilter', 'endspaceless', 'endwith', 'endsandbox', 'endverbatim', 'endcache', 'in', 'as'];
        foreach ($structuralTags as $t) {
            $allowedTags[$t] = true;
        }

        $out = ['tags' => [], 'filters' => [], 'functions' => []];

        $routes = $pages->getList(null, 0, true);
        unset($routes['/']);

        $status && $status(['type' => 'count', 'steps' => count($routes)]);

        foreach (array_keys($routes) as $route) {
            $status && $status(['type' => 'progress']);
            try {
                $page = $pages->find($route);
                if (!$page || !$page->exists()) {
                    continue;
                }
                if (method_exists($page, 'modularTwig') && $page->modularTwig()) {
                    continue;
                }
                $page->header();
                $content = (string) $page->value('content');
                if (!str_contains($content, '{{') && !str_contains($content, '{%')) {
                    continue;
                }

                $routeLabel = (string) ($page->route() ?? $page->rawRoute() ?? $route);
                $tokens = self::extractTwigTokens($content);

                foreach ($tokens['tags'] as $tag) {
                    if (!isset($allowedTags[strtolower($tag)])) {
                        $out['tags'][$tag][] = $routeLabel;
                    }
                }
                foreach ($tokens['filters'] as $filter) {
                    if (!isset($allowedFilters[strtolower($filter)])) {
                        $out['filters'][$filter][] = $routeLabel;
                    }
                }
                foreach ($tokens['functions'] as $function) {
                    if (!isset($allowedFunctions[strtolower($function)])) {
                        $out['functions'][$function][] = $routeLabel;
                    }
                }
            } catch (Exception) {
                continue;
            }
        }

        // De-dup routes per token.
        foreach ($out as $type => $map) {
            foreach ($map as $token => $routeList) {
                $out[$type][$token] = array_values(array_unique($routeList));
            }
        }

        return $out;
    }

    /**
     * @param array<int,mixed> $values
     * @return array<string,true>
     */
    private static function lowerSet(array $values): array
    {
        $set = [];
        foreach ($values as $value) {
            if (is_string($value) && $value !== '') {
                $set[strtolower($value)] = true;
            }
        }

        return $set;
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
     * The `@media_actions` sentinel expands to Medium::ALLOWED_ACTIONS — the
     * single, already-curated list of safe chainable media actions (resize,
     * cropResize, lightbox, format, the player attributes, …) that Grav also
     * trusts from editor-authored image URLs (image.jpg?cropResize=100,100).
     * Media class entries reference the sentinel instead of restating the
     * action names, so a new documented action added to ALLOWED_ACTIONS (a
     * requirement for querystring support anyway) automatically becomes
     * callable under the sandbox — the two allowlists can never drift, and no
     * one has to hunt down missing media methods one at a time. Expanded once
     * here at policy-build time (the policy is memoized per request), so there
     * is no per-render cost. Only the concrete safe actions are added — the
     * dangerous surface (save, set, copy, delete, toArray, filepath, …) is
     * absent from ALLOWED_ACTIONS and therefore stays blocked.
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
            if (!is_string($m)) {
                continue;
            }
            $m = trim($m);
            if ($m === '') {
                continue;
            }
            // Sentinel is a methods-only concept; ignore it in property lists
            // ($lowercase is false only for the case-sensitive property map).
            if ($lowercase && strtolower($m) === '@media_actions') {
                foreach (Medium::ALLOWED_ACTIONS as $action) {
                    $clean[] = strtolower($action);
                }
                continue;
            }
            $clean[] = $lowercase ? strtolower($m) : $m;
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
