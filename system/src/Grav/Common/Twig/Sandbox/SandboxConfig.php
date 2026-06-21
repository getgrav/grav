<?php

/**
 * @package    Grav\Common\Twig\Sandbox
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Twig\Sandbox;

use ArrayAccess;
use Grav\Common\Config\Config;
use function in_array;
use function is_array;
use function strlen;
use function str_replace;
use function str_starts_with;
use function substr;

/**
 * Read-only filtered facade around Grav\Common\Config\Config injected as the
 * `config` Twig variable inside sandboxed renders (editor-authored page
 * content via `@Page:` and string templates via `@Var:`).
 *
 * Trusted theme/plugin/modular templates are NOT sandboxed (see
 * GravSourcePolicy) and continue to receive the unfiltered Config — they
 * keep full access to plugins.*, streams.*, and any other configuration.
 *
 * Denied paths are dot-notation prefixes; any read whose normalized path
 * equals or starts with a denied prefix is treated as missing:
 * `get`/`value` return the supplied default, `offsetExists` returns false,
 * and `toArray` recursively removes the matching subtrees.
 *
 * Defense-in-depth: this class is the only Config-shape object whose
 * `toarray` is allow-listed in the sandbox. The raw Config and Data
 * sandbox entries have `toarray` removed (system/config/security.yaml),
 * so the bulk-dump method is gated even if a real Config leaks into a
 * sandboxed render via another path (e.g. `grav['config']`).
 */
final class SandboxConfig implements ArrayAccess
{
    /** @var list<string> */
    private array $denied;

    /**
     * @param Config $config
     * @param iterable<string> $deniedPaths Dot-notation prefixes to redact.
     */
    public function __construct(
        private readonly Config $config,
        iterable $deniedPaths = []
    ) {
        $denied = [];
        foreach ($deniedPaths as $path) {
            $path = trim((string) $path, " \t.");
            if ($path === '' || in_array($path, $denied, true)) {
                continue;
            }
            $denied[] = $path;
        }
        $this->denied = $denied;
    }

    /**
     * Dot-notation read with denied-prefix filtering.
     *
     * Reading at or above a denied prefix (e.g. `get('')` for the whole tree
     * or `get('plugins')` when `plugins` is denied) returns the default;
     * reading from inside a non-denied subtree that contains denied
     * descendants (e.g. the root tree) returns the array with denied
     * subtrees removed.
     */
    public function get(string $name, mixed $default = null, string $separator = '.'): mixed
    {
        $path = $this->normalize($name, $separator);
        if ($this->isDenied($path)) {
            return $default;
        }

        $value = $this->config->get($name, $default, $separator);

        if (is_array($value) && $this->hasDeniedDescendantsOf($path)) {
            $value = $this->filterArray($value, $path);
        }

        return $value;
    }

    public function value(string $name, mixed $default = null, string $separator = '.'): mixed
    {
        return $this->get($name, $default, $separator);
    }

    /**
     * Full configuration tree with denied subtrees removed.
     */
    public function toArray(): array
    {
        return $this->filterArray($this->config->toArray(), '');
    }

    public function offsetExists(mixed $offset): bool
    {
        $key = $this->normalize((string) $offset, '.');
        if ($this->isDenied($key)) {
            return false;
        }
        return $this->config->offsetExists($offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        $key = $this->normalize((string) $offset, '.');
        if ($this->isDenied($key)) {
            return null;
        }

        $value = $this->config->offsetGet($offset);
        if (is_array($value) && $this->hasDeniedDescendantsOf($key)) {
            $value = $this->filterArray($value, $key);
        }
        return $value;
    }

    /**
     * Read-only facade. Sandbox already blocks set on the security-policy
     * layer; this is a no-op so an accidental call from non-sandboxed code
     * cannot mutate the underlying Config.
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
    }

    public function offsetUnset(mixed $offset): void
    {
    }

    private function normalize(string $name, string $separator): string
    {
        $name = trim($name, " \t.");
        if ($separator !== '.' && $separator !== '') {
            $name = str_replace($separator, '.', $name);
        }
        return $name;
    }

    /**
     * True when $path equals a denied prefix or is a descendant of one.
     */
    private function isDenied(string $path): bool
    {
        if ($path === '') {
            return false;
        }
        $needle = $path . '.';
        foreach ($this->denied as $denied) {
            $haystack = $denied . '.';
            if ($haystack === $needle || str_starts_with($needle, $haystack)) {
                return true;
            }
        }
        return false;
    }

    /**
     * True when any denied path lives inside the subtree rooted at $prefix
     * (i.e. reading $prefix returns an array that still contains denied
     * keys and needs filtering).
     */
    private function hasDeniedDescendantsOf(string $prefix): bool
    {
        if ($prefix === '') {
            return $this->denied !== [];
        }
        $haystack = $prefix . '.';
        foreach ($this->denied as $denied) {
            if (str_starts_with($denied . '.', $haystack)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Walk denied paths and unset matching subtrees within $tree, treating
     * $tree as anchored at $rootPath inside the full config (so a denied
     * path 'plugins' against a tree rooted at 'system' is irrelevant).
     */
    private function filterArray(array $tree, string $rootPath): array
    {
        foreach ($this->denied as $denied) {
            if ($rootPath === '') {
                $relative = $denied;
            } else {
                $rootPrefix = $rootPath . '.';
                if (!str_starts_with($denied . '.', $rootPrefix)) {
                    continue;
                }
                $relative = substr($denied, strlen($rootPrefix));
                if ($relative === '' || $relative === false) {
                    return [];
                }
            }
            $tree = $this->unsetPath($tree, explode('.', $relative));
        }
        return $tree;
    }

    /**
     * @param array         $tree
     * @param list<string>  $segments
     * @return array
     */
    private function unsetPath(array $tree, array $segments): array
    {
        if ($segments === []) {
            return $tree;
        }
        $head = array_shift($segments);
        if ($segments === []) {
            unset($tree[$head]);
            return $tree;
        }
        if (isset($tree[$head]) && is_array($tree[$head])) {
            $tree[$head] = $this->unsetPath($tree[$head], $segments);
        }
        return $tree;
    }
}
