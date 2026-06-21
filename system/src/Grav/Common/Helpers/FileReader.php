<?php

/**
 * @package    Grav\Common\Helpers
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Helpers;

use Grav\Common\Config\Config;
use Grav\Common\Grav;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

use function count;
use function explode;
use function in_array;
use function is_file;
use function is_string;
use function pathinfo;
use function rawurldecode;
use function realpath;
use function rtrim;
use function str_contains;
use function strlen;
use function strncmp;
use function strtolower;

use const DIRECTORY_SEPARATOR;
use const PATHINFO_EXTENSION;

/**
 * Reads a file via a Grav stream URI (e.g. `theme://foo.md`) and returns its
 * contents, with the same hardening regardless of who's calling — the Twig
 * `read_file()` function and the `[read-file]` shortcode both delegate here
 * so there's exactly one place to audit.
 *
 * The defence is layered:
 *   1. Reject obviously hostile inputs (null bytes, URL encoding, backslashes,
 *      `..` segments) — cheap string checks, not the security boundary.
 *   2. Require a Grav stream URI and an allow-listed scheme.
 *   3. Require an allow-listed file extension (text/content formats only).
 *   4. Resolve via the locator, then verify the file's `realpath()` is
 *      contained inside `realpath()` of one of the stream's roots — this
 *      is the actual security boundary, immune to encoding tricks because
 *      every form of traversal collapses to a single canonical path.
 *   5. Enforce a max file size.
 *
 * Behaviour is configurable under `security.read_file.*` in
 * `system/config/security.yaml`.
 */
final class FileReader
{
    /**
     * Read a file by stream URI. Returns false on any rejection — never
     * throws — so callers can render a fallback without try/catch.
     *
     * @param string $uri Grav stream URI, e.g. `theme://foo.md`.
     * @return false|string  File contents on success, false on rejection.
     */
    public static function read($uri)
    {
        if (!is_string($uri) || $uri === '') {
            return false;
        }

        // (1) Cheap input hygiene. None of these are the security boundary —
        // the canonical containment check below is — but they short-circuit
        // obvious abuse early and keep error reporting clean.
        if (str_contains($uri, "\0") || str_contains($uri, '\\')) {
            return false;
        }
        // No URL encoding inside a filesystem helper. Collapses %2e%2e%2f,
        // %252e%252e%252f, etc. — every encoded `..` fails to round-trip.
        if (rawurldecode($uri) !== $uri) {
            return false;
        }

        $grav = Grav::instance();
        /** @var UniformResourceLocator $locator */
        $locator = $grav['locator'];

        // (2) Stream-only. Raw filesystem paths are never accepted.
        if (!$locator->isStream($uri)) {
            return false;
        }

        $parts = explode('://', $uri, 2);
        if (count($parts) !== 2) {
            return false;
        }
        [$scheme, $streamPath] = $parts;

        // Reject any `..` *segment*. Substring matching is fooled by `....//`,
        // mixed separators, etc.; segment-wise comparison is bypass-proof for
        // any string that already passed the URL-decode round-trip above.
        foreach (explode('/', $streamPath) as $segment) {
            if ($segment === '..') {
                return false;
            }
        }

        /** @var Config $config */
        $config = $grav['config'];

        $allowedStreams = (array) $config->get('security.read_file.allowed_streams', [
            'theme', 'themes', 'page', 'user-data'
        ]);
        if (!in_array($scheme, $allowedStreams, true)) {
            return false;
        }

        // (3) Extension allow-list. Default set is text/content formats only.
        $extension = strtolower((string) pathinfo($streamPath, PATHINFO_EXTENSION));
        $allowedExtensions = (array) $config->get('security.read_file.allowed_extensions', [
            'md', 'markdown', 'txt', 'html', 'htm', 'json', 'csv', 'xml', 'svg'
        ]);
        if ($extension === '' || !in_array($extension, $allowedExtensions, true)) {
            return false;
        }

        // (4) Resolve and verify canonical containment. The trailing
        // separator in the strncmp is essential — without it,
        // `/var/grav/user/themes/quark` would prefix-match
        // `/var/grav/user/themes/quark-evil`.
        $resolved = $locator->findResource($uri);
        if (!$resolved) {
            return false;
        }
        $realFile = realpath($resolved);
        if ($realFile === false || !is_file($realFile)) {
            return false;
        }

        $contained = false;
        foreach ($locator->findResources($scheme . '://', true, true) as $root) {
            $realRoot = realpath($root);
            if ($realRoot === false) {
                continue;
            }
            $realRoot = rtrim($realRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            if (strncmp($realFile . DIRECTORY_SEPARATOR, $realRoot, strlen($realRoot)) === 0) {
                $contained = true;
                break;
            }
        }
        if (!$contained) {
            return false;
        }

        // (5) Size cap (bytes). Set to 0 to disable.
        $maxSize = (int) $config->get('security.read_file.max_size', 1048576);
        if ($maxSize > 0) {
            $size = filesize($realFile);
            if ($size === false || $size > $maxSize) {
                return false;
            }
        }

        return file_get_contents($realFile);
    }
}
