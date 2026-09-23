<?php

/**
 * @package    Grav\Common\File
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\File;

use RocketTheme\Toolbox\File\MarkdownFile;
use function count;
use function function_exists;
use function is_array;

/**
 * Class CompiledMarkdownFile
 * @package Grav\Common\File
 */
class CompiledMarkdownFile extends MarkdownFile
{
    use CompiledFile;

    /**
     * Parsed headers keyed by frontmatter hash while Pages scans the tree, null otherwise.
     *
     * @var array{previous: array<string,array>, current: array<string,array>, parsed: int}|null
     */
    private static $scan;

    /**
     * Start a pages scan.
     *
     * A scan reads every page once and Pages keeps what it needs in its own cache, so during
     * a scan page files skip the per-file compiled cache: writing one PHP file per page made a
     * cold rebuild slow, and including all of them on the next rebuild filled opcache. The
     * expensive part of reading a page is the YAML parse of its frontmatter, so headers parsed
     * by the previous scan are passed in and reused whenever the frontmatter text is identical.
     *
     * @param array<string,array> $headers Headers returned by endScan() of an earlier scan.
     * @return bool False if a scan is already running.
     */
    public static function beginScan(array $headers = []): bool
    {
        if (self::$scan !== null) {
            return false;
        }

        self::$scan = ['previous' => $headers, 'current' => [], 'parsed' => 0];

        return true;
    }

    /**
     * End a pages scan.
     *
     * @param bool|null $changed Set to true when the returned headers differ from the ones
     *                           passed to beginScan(), so the caller only saves them when needed.
     * @return array<string,array> Headers used by this scan, keyed by frontmatter hash.
     */
    public static function endScan(?bool &$changed = null): array
    {
        $scan = self::$scan;
        self::$scan = null;
        if ($scan === null) {
            $changed = false;

            return [];
        }

        $changed = $scan['parsed'] > 0 || count($scan['current']) !== count($scan['previous']);

        return $scan['current'];
    }

    /**
     * @return bool
     */
    protected function usesCompiledCache(): bool
    {
        return self::$scan === null;
    }

    /**
     * Decode RAW string into contents, reusing the header parsed by an earlier scan.
     *
     * @param string $var
     * @return array
     */
    protected function decode($var)
    {
        if (self::$scan === null) {
            return parent::decode($var);
        }

        // Split the file the same way MarkdownFile::decode() does; the header is only reused
        // for byte-identical frontmatter parsed with the same YAML settings.
        $var = (string)preg_replace("/(\r\n|\r)/", "\n", ltrim($var, "\xef\xbb\xbf"));
        if (!preg_match("/^---\n(.+?)\n---\n{0,}(.*)$/uis", ltrim($var), $m)) {
            return parent::decode($var);
        }

        $frontmatter = (string)preg_replace("/\n\t/", "\n    ", $m[1]);
        $native = function_exists('yaml_parse') && $this->setting('native');
        $key = hash('xxh128', $frontmatter) . ($native ? 'n' : 's') . ($this->setting('compat', true) ? 'c' : '');

        $header = self::$scan['current'][$key] ?? self::$scan['previous'][$key] ?? null;
        if (is_array($header)) {
            self::$scan['current'][$key] = $header;

            return ['header' => $header, 'frontmatter' => $frontmatter, 'markdown' => $m[2]];
        }

        $content = parent::decode($var);
        if (is_array($content['header'])) {
            self::$scan['current'][$key] = $content['header'];
            self::$scan['parsed']++;
        }

        return $content;
    }
}
