<?php

/**
 * @package    Grav\Common\File
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\File;

use RocketTheme\Toolbox\File\MarkdownFile;
use RocketTheme\Toolbox\File\YamlFile;
use function count;
use function function_exists;
use function is_array;

/**
 * Class CompiledMarkdownFile
 * @package Grav\Common\File
 */
class CompiledMarkdownFile extends MarkdownFile
{
    use CompiledFile {
        save as private saveCompiledFile;
    }

    /**
     * Parsed headers keyed by frontmatter hash while Pages scans the tree, null otherwise.
     *
     * 'files' and 'filesCurrent' map a page file to [mtime, size, header key, time recorded]
     * from the previous scan and this one.
     *
     * @var array{previous: array<string,array>, current: array<string,array>, parsed: int, files: array<string,array>, filesCurrent: array<string,array>, time: int}|null
     */
    private static $scan;

    /** @var bool True while this instance holds only a header reused from the previous scan, without reading the file. */
    private $headerOnly = false;

    /** @var bool Set once anything asks for more than the header, so the whole file is read. */
    private $wantsAll = false;

    /** @var string|null Header key decode() computed for the file, while a scan is running. */
    private $scanKey;

    /**
     * Parse frontmatter with libyaml when the yaml extension is installed (`system.pages.frontmatter.native_yaml`).
     *
     * Off by default: libyaml reads unquoted dates as strings and `yes`/`no` as booleans, which
     * changes existing frontmatter where the Symfony parser has always been used.
     *
     * @var bool
     */
    public static $nativeYaml = false;

    /**
     * Start a pages scan.
     *
     * A scan reads every page once and Pages keeps what it needs in its own cache, so during
     * a scan page files skip the per-file compiled cache: writing one PHP file per page made a
     * cold rebuild slow, and including all of them on the next rebuild filled opcache. The
     * expensive part of reading a page is the YAML parse of its frontmatter, so headers parsed
     * by the previous scan are passed in and reused whenever the frontmatter text is identical.
     * With the file records of the previous scan, a file whose time and size are unchanged is
     * not read at all: its header comes straight from the saved headers.
     *
     * @param array<string,array> $headers Headers returned by endScan() of an earlier scan.
     * @param array<string,array> $files File records returned by endScan() of an earlier scan.
     * @return bool False if a scan is already running.
     */
    public static function beginScan(array $headers = [], array $files = []): bool
    {
        if (self::$scan !== null) {
            return false;
        }

        self::$scan = ['previous' => $headers, 'current' => [], 'parsed' => 0, 'files' => $files, 'filesCurrent' => [], 'time' => time()];

        return true;
    }

    /**
     * End a pages scan.
     *
     * @param bool|null $changed Set to true when the returned headers differ from the ones
     *                           passed to beginScan(), so the caller only saves them when needed.
     * @param array|null $files Set to the page files this scan read or reused, with their time,
     *                          size and header key, for the next beginScan().
     * @return array<string,array> Headers used by this scan, keyed by frontmatter hash.
     */
    public static function endScan(?bool &$changed = null, ?array &$files = null): array
    {
        $scan = self::$scan;
        self::$scan = null;
        if ($scan === null) {
            $changed = false;
            $files = [];

            return [];
        }

        $files = $scan['filesCurrent'];
        $changed = $scan['parsed'] > 0 || count($scan['current']) !== count($scan['previous']);

        return $scan['current'];
    }

    /**
     * True when this file holds only its header, reused from the previous scan because the
     * file's time and size are unchanged. The frontmatter text and the markdown are read from
     * the file when they are asked for.
     *
     * @return bool
     * @internal
     */
    public function isHeaderOnly(): bool
    {
        return $this->headerOnly;
    }

    /**
     * @param array|null $var
     * @return array
     */
    public function header(?array $var = null)
    {
        if ($var !== null) {
            $this->loadFull();
        }

        return parent::header($var);
    }

    /**
     * @param string|null $var
     * @return string
     */
    public function markdown($var = null)
    {
        $this->loadFull();

        return parent::markdown($var);
    }

    /**
     * @param string|null $var
     * @return string
     */
    public function frontmatter($var = null)
    {
        $this->loadFull();

        return parent::frontmatter($var);
    }

    /**
     * @param mixed $data
     * @return void
     */
    public function save(mixed $data = null)
    {
        $this->loadFull();

        $this->saveCompiledFile($data);
    }

    /**
     * @return void
     */
    public function free()
    {
        $this->headerOnly = false;
        $this->wantsAll = false;

        parent::free();
    }

    /**
     * Replace a reused header with the whole file, before anything reads or writes the rest of it.
     *
     * @return void
     */
    private function loadFull(): void
    {
        $this->wantsAll = true;
        if ($this->headerOnly) {
            $this->headerOnly = false;
            $this->content = (array)$this->decode($this->raw());
        }
    }

    /**
     * Get setting.
     *
     * With `system.pages.frontmatter.native_yaml` on, and unless the file sets `native` itself,
     * frontmatter follows YamlFile::globalSettings() like the configuration files, so it is parsed
     * with libyaml when the yaml extension is installed. The `compat` fallback keeps its markdown
     * default (on), so a frontmatter that neither parser accepts still falls back to the
     * compatibility parser instead of failing the page.
     *
     * @param string $setting
     * @param mixed $default
     * @return mixed
     */
    public function setting($setting, $default = null)
    {
        if ($setting === 'native' && self::$nativeYaml && !isset($this->settings['native'])) {
            return YamlFile::globalSettings()['native'] ?? $default;
        }

        return parent::setting($setting, $default);
    }

    /**
     * @return bool
     */
    protected function usesCompiledCache(): bool
    {
        return self::$scan === null;
    }

    /**
     * Read the file during a pages scan.
     *
     * When the previous scan recorded the file with the same modification time and size, and
     * the file was not modified in the second that scan read it, its header is reused without
     * opening the file. Otherwise the file is read and decoded, and recorded for the next scan.
     *
     * @return array
     */
    protected function readUncompiled(): array
    {
        if (self::$scan === null) {
            return (array)$this->decode($this->raw());
        }

        $filename = (string)$this->filename;
        $stat = @stat($filename);
        if ($stat !== false && !$this->wantsAll) {
            $known = self::$scan['files'][$filename] ?? null;
            if (is_array($known) && count($known) === 4
                && $known[0] === $stat['mtime'] && $known[1] === $stat['size'] && $known[0] < $known[3]
            ) {
                $header = self::$scan['current'][$known[2]] ?? self::$scan['previous'][$known[2]] ?? null;
                if (is_array($header)) {
                    self::$scan['current'][$known[2]] = $header;
                    self::$scan['filesCurrent'][$filename] = $known;
                    $this->headerOnly = true;

                    return ['header' => $header, 'frontmatter' => null, 'markdown' => null];
                }
            }
        }

        // Take the time and size before reading, so a write during the read shows up as a change next time.
        $this->scanKey = null;
        $content = (array)$this->decode($this->raw());
        if ($stat !== false && $this->scanKey !== null) {
            self::$scan['filesCurrent'][$filename] = [$stat['mtime'], $stat['size'], $this->scanKey, self::$scan['time']];
        }

        return $content;
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
            $this->scanKey = $key;

            return ['header' => $header, 'frontmatter' => $frontmatter, 'markdown' => $m[2]];
        }

        $content = parent::decode($var);
        if (is_array($content['header'])) {
            self::$scan['current'][$key] = $content['header'];
            self::$scan['parsed']++;
            $this->scanKey = $key;
        }

        return $content;
    }
}
