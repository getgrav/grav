<?php

/**
 * @package    Grav\Common\Twig\Sandbox
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Twig\Sandbox;

/**
 * Default Twig-sandbox allowlists and config-redaction paths.
 *
 * These lived inline in `system/config/security.yaml` until the 2026-08-12
 * security-config audit moved them here. Keeping the ~300-line security-critical
 * baseline in code (not in a user-editable YAML file) fixes a real hazard: Grav
 * merges leaf lists by REPLACEMENT, so any site that copied a list into
 * `user/config/security.yaml` to tweak it froze that list against every later
 * core change — including security TIGHTENINGS (e.g. GHSA-mc5q-6hpj-rp7j dropped
 * dump/serialize filters that a frozen copy would still expose).
 *
 * The `security.twig_sandbox.allowed_*` config keys still exist and are now
 * purely ADDITIVE over these defaults (a site can only widen the policy, never
 * silently drop a core entry it never touched). `security.twig_sandbox.denied_*`
 * is the explicit way to tighten below the defaults — see
 * Security::buildTwigSandboxPolicy(). The union/subtraction is the single
 * source of truth consumed by both the sandbox policy build and the admin
 * "Twig in Content" diagnostic.
 *
 * Every entry here is documented in the security.yaml comment block and in the
 * original advisory trail; do not loosen without the same scrutiny an advisory
 * fix would get.
 */
final class SandboxDefaults
{
    /**
     * Dot-notation config prefixes redacted from sandboxed renders.
     * (GHSA-j274-39qw-32c9, GHSA-p597-crqc-m349)
     *
     * @return list<string>
     */
    public static function configDeniedPaths(): array
    {
        return [
            'plugins',
            'streams',
            'security',
            'backups',
            'scheduler',
            'system.cache.redis.password',
        ];
    }

    /**
     * @return list<string>
     */
    public static function tags(): array
    {
        return [
            'apply',
            'autoescape',
            'block',
            'deprecated',
            'do',
            'embed',
            'extends',
            'for',
            'from',
            'if',
            'import',
            'include',
            'macro',
            'sandbox',
            'set',
            'spaceless',
            'types',
            'use',
            'verbatim',
            'with',
        ];
    }

    /**
     * `raw` is intentionally absent: it is the one filter that defeats Twig
     * autoescape, so `{{ x|raw }}` on render-time-only data is the sole XSS
     * vector the save-time content scan can't see. Dropping it keeps that scan
     * complete. Trusted theme templates (.html.twig) are unsandboxed and can
     * still use `raw`.
     *
     * @return list<string>
     */
    public static function filters(): array
    {
        return [
            // Twig core
            'abs',
            'batch',
            'capitalize',
            'column',
            'convert_encoding',
            'country_name',
            'currency_name',
            'currency_symbol',
            'data_uri',
            'date',
            'date_modify',
            'default',
            'e',
            'escape',
            'filter',
            'first',
            'format',
            'format_currency',
            'format_date',
            'format_datetime',
            'format_number',
            'format_time',
            'html_to_markdown',
            'inline_css',
            'inky_to_html',
            'join',
            'json_encode',
            'keys',
            'language_name',
            'last',
            'length',
            'locale_name',
            'lower',
            'map',
            'markdown_to_html',
            'merge',
            'nl2br',
            'number_format',
            'reduce',
            'replace',
            'reverse',
            'round',
            'slice',
            'slug',
            'sort',
            'split',
            'striptags',
            'timezone_name',
            'title',
            'trim',
            'u',
            'upper',
            'url_encode',
            // Grav extras (safe)
            'absolute_url',
            'array',
            'array_diff',
            'array_unique',
            'base32_decode',
            'base32_encode',
            'base64_decode',
            'base64_encode',
            'basename',
            'bool',
            'chunk_split',
            'contains',
            'count',
            'defined',
            'dirname',
            'ends_with',
            'fieldName',
            'float',
            'get_type',
            'int',
            'json_decode',
            'ksort',
            'ltrim',
            'markdown',
            'md5',
            'modulus',
            'nicecron',
            'nicefilesize',
            'nicenumber',
            'nicetime',
            'of_type',
            'pad',
            'parent_field',
            'print_r',
            'randomize',
            'regex_replace',
            'replace_last',
            'rtrim',
            'safe_email',
            'safe_truncate',
            'safe_truncate_html',
            'sort_by_key',
            'starts_with',
            'string',
            't',
            'ta',
            'tl',
            'truncate',
            'truncate_html',
            'wordcount',
            'yaml',
            'yaml_decode',
            'yaml_encode',
        ];
    }

    /**
     * Safe subset only. `include`/`source`/`template_from_string`/`constant`,
     * `evaluate`/`evaluate_twig`/`svg_image`/`read_file`/`redirect_me`/
     * `http_response_code` and the FilesystemExtension probes are deliberately
     * absent. `read_file` is hardened but stays off so editor page content can't
     * pull arbitrary files; theme templates and the `[read-file]` shortcode
     * reach it through the same hardening. dump/debug are gated by
     * $env->isDebug() and are no-ops in production.
     *
     * @return list<string>
     */
    public static function functions(): array
    {
        return [
            // Twig core (safe subset)
            'attribute',
            'block',
            'cycle',
            'date',
            'max',
            'min',
            'parent',
            'random',
            'range',
            // Grav extras (safe subset)
            'array',
            'array_diff',
            'array_intersect',
            'array_key_exists',
            'array_key_value',
            'array_unique',
            'authorize',
            'body_class',
            'count',
            'cron',
            'debug',
            'dump',
            'get_cookie',
            'get_type',
            'gist',
            'header_var',
            'is_array',
            'is_countable',
            'is_iterable',
            'is_null',
            'is_numeric',
            'is_object',
            'is_string',
            'isajaxrequest',
            'json_decode',
            'md5',
            'media_directory',
            'nicefilesize',
            'nicenumber',
            'nicetime',
            'of_type',
            'random_string',
            'regex_filter',
            'regex_match',
            'regex_replace',
            'regex_split',
            'repeat',
            'string',
            't',
            'ta',
            'theme_var',
            'tl',
            'unique_id',
            'url',
            'vardump',
            'xss',
        ];
    }

    /**
     * Per-class method allowlist, list-of-rows shape. Method names lowercase
     * (Twig lowercases before the check). `@media_actions` expands to
     * Medium::ALLOWED_ACTIONS at policy-build time so the media action surface
     * can never drift from the URL-trusted list. `offsetget`/`__get`,
     * `toarray` on raw Config/Data, and the dangerous media surface are
     * deliberately absent. (GHSA-mc5q-6hpj-rp7j, GHSA-j274-39qw-32c9)
     *
     * @return list<array{class: string, methods: string}>
     */
    public static function methods(): array
    {
        return [
            ['class' => 'Grav\Common\Grav', 'methods' => 'offsetexists, getversion, theme'],
            ['class' => 'Grav\Common\Config\Config', 'methods' => 'get, value, default, offsetget, offsetexists'],
            ['class' => 'Grav\Common\Twig\Sandbox\SandboxConfig', 'methods' => 'get, toarray, value, offsetget, offsetexists'],
            ['class' => 'Grav\Common\Page\Interfaces\PageInterface', 'methods' => 'content, header, title, menu, slug, route, rawroute, url, path, permalink, template, templateformat, filepath, date, dateformat, modified, media, parent, children, collection, find, findmedia, value, summary, taxonomy, visible, published, publishdate, unpublishdate, routable, language, modular, modulartwig, ismodule, ispage, isfirst, islast, adjacentsibling, prevsibling, nextsibling, metadata, id, order, breadcrumbs, home, tostring, __tostring'],
            ['class' => 'Grav\Common\Page\Pages', 'methods' => 'root, base, dispatch, home, all, children, instances, routes, sort'],
            ['class' => 'Grav\Common\Uri', 'methods' => 'path, url, params, param, query, host, port, scheme, route, base, extension, method, referrer, ip, addr, toarray, __tostring'],
            ['class' => 'Grav\Common\Page\Media', 'methods' => 'images, videos, audios, files, all, get, offsetget, offsetexists, __tostring'],
            ['class' => 'Grav\Common\Media\Interfaces\MediaCollectionInterface', 'methods' => 'images, videos, audios, files, all, get, offsetget, offsetexists, __tostring'],
            ['class' => 'Grav\Common\Page\Medium\Medium', 'methods' => 'url, html, filepath, filename, metadata, srcset, parsedownelement, __tostring, @media_actions'],
            ['class' => 'Grav\Common\Media\Interfaces\MediaLinkInterface', 'methods' => 'html, url, __tostring, parsedownelement, srcset, @media_actions'],
            ['class' => 'Grav\Common\User\Interfaces\UserInterface', 'methods' => 'authorize, authorized, authenticated, username, fullname, email, language, offsetget, offsetexists'],
            ['class' => 'Grav\Common\Taxonomy', 'methods' => 'taxonomy'],
            ['class' => 'Grav\Common\Language\Language', 'methods' => 'getactive, getdefault, getlanguages, getlanguage, enabled'],
            ['class' => 'Grav\Common\Assets', 'methods' => '__tostring, addcss, addjs'],
            ['class' => 'stdClass', 'methods' => '*'],
            ['class' => 'Grav\Common\Data\Data', 'methods' => 'get, value, items, offsetget, offsetexists, __tostring'],
        ];
    }

    /**
     * Per-class property allowlist, list-of-rows shape. Property names are
     * CASE-SENSITIVE. Wildcards on the ArrayAccess media collections and on
     * stdClass are deliberate (a filename/YAML key can't be enumerated); the
     * value still comes back through offsetGet whose methods stay restricted.
     * Fixes #4114.
     *
     * @return list<array{class: string, methods: string}>
     */
    public static function properties(): array
    {
        return [
            ['class' => 'Grav\Common\Page\Interfaces\PageInterface', 'methods' => 'header, media, taxonomy'],
            ['class' => 'Grav\Common\Grav', 'methods' => 'theme'],
            ['class' => 'Grav\Common\Media\Interfaces\MediaCollectionInterface', 'methods' => '*'],
            ['class' => 'Grav\Common\Page\Media', 'methods' => '*'],
            ['class' => 'Grav\Common\Page\Medium\Medium', 'methods' => '*'],
            ['class' => 'stdClass', 'methods' => '*'],
        ];
    }

    /**
     * The full default set keyed by list name, for the effective-policy merge
     * and the admin diagnostic.
     *
     * @return array{tags: list<string>, filters: list<string>, functions: list<string>, methods: list<array{class: string, methods: string}>, properties: list<array{class: string, methods: string}>}
     */
    public static function all(): array
    {
        return [
            'tags' => self::tags(),
            'filters' => self::filters(),
            'functions' => self::functions(),
            'methods' => self::methods(),
            'properties' => self::properties(),
        ];
    }
}
