<?php

/**
 * @package    Grav\Common\Page
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Page;

use Grav\Common\Grav;

/**
 * Helpers for formatting and parsing the numeric order prefix on page folders
 * (e.g. the "02." in "02.about").
 *
 * Default zero-pad width is 2 digits, matching pre-2.0 behavior. The width can
 * be overridden globally via `system.pages.order_digits` (1..6), and individual
 * page folders preserve their original width across saves so existing 3- or
 * 4-digit prefixes survive an edit-and-save round trip.
 */
final class PageOrdering
{
    public const DEFAULT_DIGITS = 2;
    public const MIN_DIGITS = 1;
    public const MAX_DIGITS = 6;

    /** @var int|null Resolved per-request default; null means unresolved. */
    private static ?int $configuredDigits = null;

    /**
     * Format a numeric order as a folder prefix including the trailing dot.
     * Returns '' for empty/zero/null orders (a folder with no prefix).
     *
     * @param int|string|null $order
     * @param int|null        $digits Width to pad to. Null = use configured default.
     */
    public static function prefix($order, ?int $digits = null): string
    {
        if ($order === null || $order === '' || $order === 0 || $order === '0' || $order === false) {
            return '';
        }

        $intOrder = (int) $order;
        if ($intOrder <= 0) {
            return '';
        }

        $width = $digits !== null ? self::clampDigits($digits) : self::defaultDigits();
        $value = (string) $intOrder;

        // str_pad outperforms sprintf for small fixed-width integers and
        // already auto-grows when the value has more digits than $width.
        return str_pad($value, $width, '0', STR_PAD_LEFT) . '.';
    }

    /**
     * Build the full "<prefix><folder>" storage segment.
     *
     * @param int|string|null $order
     */
    public static function key($order, string $folder, ?int $digits = null): string
    {
        $prefix = self::prefix($order, $digits);

        return $prefix !== '' ? $prefix . $folder : $folder;
    }

    /**
     * Parse a folder name into [order, slug, digits].
     * Returns [null, $name, null] when there is no numeric prefix.
     *
     * @return array{0: int|null, 1: string, 2: int|null}
     */
    public static function parse(string $name): array
    {
        if ($name !== '' && preg_match('/^(\d+)\.(.+)$/u', $name, $m) === 1) {
            return [(int) $m[1], $m[2], strlen($m[1])];
        }

        return [null, $name, null];
    }

    /**
     * Extract the digit width of an existing folder name's prefix, or null
     * when the folder has no numeric prefix. Cheap regex over a basename.
     */
    public static function digitsFromFolder(?string $folder): ?int
    {
        if ($folder === null || $folder === '') {
            return null;
        }

        return preg_match('/^(\d+)\./u', $folder, $m) === 1 ? strlen($m[1]) : null;
    }

    /**
     * Configured default zero-pad width, cached per request.
     */
    public static function defaultDigits(): int
    {
        if (self::$configuredDigits !== null) {
            return self::$configuredDigits;
        }

        $digits = self::DEFAULT_DIGITS;
        $grav = Grav::instance();
        if (isset($grav['config'])) {
            $configured = (int) $grav['config']->get('system.pages.order_digits', self::DEFAULT_DIGITS);
            $digits = self::clampDigits($configured);
        }

        return self::$configuredDigits = $digits;
    }

    /**
     * Reset the cached default — for tests or after config reloads.
     */
    public static function resetCache(): void
    {
        self::$configuredDigits = null;
    }

    private static function clampDigits(int $digits): int
    {
        if ($digits < self::MIN_DIGITS) {
            return self::MIN_DIGITS;
        }
        if ($digits > self::MAX_DIGITS) {
            return self::MAX_DIGITS;
        }

        return $digits;
    }
}
