<?php

/**
 * @package    Grav\Common\Page
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Page;

use Throwable;

/**
 * Per-page storage for the regular pages index.
 *
 * Instead of serializing every Page object into one cache blob that has to be
 * unserialized in full on every request, each page is stored as its own row in
 * a single-file SQL database, and Pages::get() hydrates only the pages a
 * request actually touches - the same shape Flex pages use for their index.
 *
 * The native pdo_sqlite driver is used when available, with the pure-PHP
 * YetiSQL engine as a fallback; when neither exists the caller keeps the
 * classic full-blob behavior. Rebuilds write a temporary file and atomically
 * rename it into place, so concurrent readers never see a partial index.
 */
final class PageIndexStore
{
    /** Meta schema version for the store file itself. */
    private const SCHEMA = 2;

    /** @var \PDO|\YetiDevWorks\YetiSQL\PDO */
    private $pdo;

    /** @var string */
    private $file;

    /** @var string 'sqlite' or 'yetisql' */
    private $engine;

    /** @var object|null Prepared point-read statement, created on first read. */
    private $readStatement = null;

    /**
     * @param object $pdo
     * @param string $file
     * @param string $engine
     */
    private function __construct($pdo, string $file, string $engine)
    {
        $this->pdo = $pdo;
        $this->file = $file;
        $this->engine = $engine;
    }

    /**
     * Open (or prepare to create) the store for the given directory and name.
     * Returns null when no supported database engine is available.
     *
     * @param string $dir Directory for index files (created if missing).
     * @param string $name Stable name for this index (differs per language/page dirs).
     * @return self|null
     */
    public static function open(string $dir, string $name): ?self
    {
        $engine = self::detectEngine();
        if ($engine === null) {
            return null;
        }

        if (!is_dir($dir) && !@mkdir($dir, 0777, true) && !is_dir($dir)) {
            return null;
        }

        // Engine-specific extension: the two engines use different file formats
        // and must never try to read each other's files.
        $file = $dir . '/' . $name . ($engine === 'sqlite' ? '.sqlite' : '.ysql');

        $pdo = self::connect($engine, $file);
        if ($pdo === null) {
            return null;
        }

        return new self($pdo, $file, $engine);
    }

    /**
     * @return string|null
     */
    private static function detectEngine(): ?string
    {
        // Explicit override, mainly for testing the fallback engine and for
        // hosts where the native driver misbehaves.
        $forced = getenv('GRAV_PAGES_INDEX_ENGINE');
        if ($forced === 'sqlite' || $forced === 'yetisql') {
            if ($forced === 'sqlite' && extension_loaded('pdo_sqlite')) {
                return 'sqlite';
            }
            if ($forced === 'yetisql' && class_exists(\YetiDevWorks\YetiSQL\PDO::class)) {
                return 'yetisql';
            }

            return null;
        }

        if (extension_loaded('pdo_sqlite')) {
            return 'sqlite';
        }
        if (class_exists(\YetiDevWorks\YetiSQL\PDO::class)) {
            return 'yetisql';
        }

        return null;
    }

    /**
     * @param string $engine
     * @param string $file
     * @return object|null
     */
    private static function connect(string $engine, string $file)
    {
        try {
            if ($engine === 'sqlite') {
                $pdo = new \PDO('sqlite:' . $file);
            } else {
                $pdo = new \YetiDevWorks\YetiSQL\PDO('yetisql:' . $file);
            }
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            return $pdo;
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Check that the store contains a complete index for the given cache id,
     * built by this Grav version.
     *
     * @param string $cacheId
     * @return bool
     */
    public function isValid(string $cacheId): bool
    {
        try {
            $stmt = $this->pdo->query('SELECT key, value FROM meta');
            $meta = [];
            foreach ($stmt->fetchAll(\PDO::FETCH_NUM) as [$key, $value]) {
                $meta[$key] = $value;
            }

            return ($meta['cache_id'] ?? null) === $cacheId
                && ($meta['grav_version'] ?? null) === GRAV_VERSION
                && (int)($meta['schema'] ?? 0) === self::SCHEMA
                && ($meta['complete'] ?? null) === '1';
        } catch (Throwable $e) {
            // Missing tables, unreadable file, wrong format: not valid.
            return false;
        }
    }

    /**
     * Read a single page payload.
     *
     * @param string $path
     * @return string|null
     */
    public function read(string $path): ?string
    {
        try {
            if ($this->readStatement === null) {
                $this->readStatement = $this->pdo->prepare('SELECT payload FROM pages WHERE path = ?');
            }
            $this->readStatement->execute([$path]);
            $payload = $this->readStatement->fetchColumn();

            return is_string($payload) ? $payload : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Read all page payloads (used for full hydration, e.g. in admin listings).
     *
     * @return array<string,string> path => payload
     */
    public function readAll(): array
    {
        try {
            $rows = $this->pdo->query('SELECT path, payload FROM pages')->fetchAll(\PDO::FETCH_NUM);

            $result = [];
            foreach ($rows as [$path, $payload]) {
                $result[$path] = $payload;
            }

            return $result;
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * Read the path a route maps to.
     *
     * @param string $route
     * @return string|null
     */
    public function readRoute(string $route): ?string
    {
        try {
            $stmt = $this->pdo->prepare('SELECT path FROM routes WHERE route = ?');
            $stmt->execute([$route]);
            $path = $stmt->fetchColumn();

            return is_string($path) ? $path : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Read the full route map in its original insertion order.
     *
     * @return array<string,string> route => path
     */
    public function readAllRoutes(): array
    {
        try {
            $rows = $this->pdo->query('SELECT route, path FROM routes ORDER BY rowid')->fetchAll(\PDO::FETCH_NUM);

            $result = [];
            foreach ($rows as [$route, $path]) {
                $result[$route] = $path;
            }

            return $result;
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * Read the children list of a parent path.
     *
     * @param string $path
     * @return array|null Null when the parent has no stored entry.
     */
    public function readChildren(string $path): ?array
    {
        return $this->readSerializedRow('children', $path);
    }

    /**
     * Read the precomputed sort orders of a parent path.
     *
     * @param string $path
     * @return array|null Null when the parent has no stored entry.
     */
    public function readSort(string $path): ?array
    {
        return $this->readSerializedRow('sorts', $path);
    }

    /**
     * @param string $table
     * @param string $path
     * @return array|null
     */
    private function readSerializedRow(string $table, string $path): ?array
    {
        try {
            $stmt = $this->pdo->prepare("SELECT payload FROM {$table} WHERE path = ?");
            $stmt->execute([$path]);
            $payload = $stmt->fetchColumn();
            if (!is_string($payload)) {
                return null;
            }

            $value = @unserialize($payload);

            return is_array($value) ? $value : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Read the pages matching a single taxonomy type/value pair, in original
     * page order.
     *
     * @param string $type
     * @param string $value
     * @return array<string,array> path => info
     */
    public function readTaxonomyValue(string $type, string $value): array
    {
        try {
            $stmt = $this->pdo->prepare('SELECT path, payload FROM taxonomy WHERE type = ? AND value = ? ORDER BY rowid');
            $stmt->execute([$type, $value]);

            $result = [];
            foreach ($stmt->fetchAll(\PDO::FETCH_NUM) as [$path, $payload]) {
                $info = @unserialize((string)$payload);
                $result[$path] = is_array($info) ? $info : [];
            }

            return $result;
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * Reconstruct the taxonomy map for the indexed language.
     *
     * @return array<string,array<string,array<string,array>>> type => value => path => info
     */
    public function readTaxonomy(): array
    {
        try {
            $rows = $this->pdo->query('SELECT type, value, path, payload FROM taxonomy ORDER BY rowid')->fetchAll(\PDO::FETCH_NUM);

            $map = [];
            foreach ($rows as [$type, $value, $path, $payload]) {
                $info = @unserialize((string)$payload);
                $map[$type][$value][$path] = is_array($info) ? $info : [];
            }

            return $map;
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * Rebuild the store from scratch with the given sections.
     *
     * Writes into a temporary file and renames it over the live one so that
     * concurrent readers keep a consistent view. Returns false on any failure,
     * in which case the caller should fall back to the classic cache format.
     *
     * @param string $cacheId
     * @param array{pages: iterable<string,string>, routes: array<string,string>, children: array<string,array>, sorts: array<string,array>, taxonomy: array} $sections
     * @return bool
     */
    public function rebuild(string $cacheId, array $sections): bool
    {
        $tmp = $this->file . '.tmp.' . getmypid();

        try {
            $pdo = self::connect($this->engine, $tmp);
            if ($pdo === null) {
                return false;
            }

            if ($this->engine === 'sqlite') {
                // The file is swapped in atomically, so crash-safety journaling
                // during the build is pure overhead.
                $pdo->exec('PRAGMA journal_mode=OFF');
                $pdo->exec('PRAGMA synchronous=OFF');
            }

            $pdo->exec('CREATE TABLE meta (key TEXT PRIMARY KEY, value TEXT NOT NULL)');
            $pdo->exec('CREATE TABLE pages (path TEXT PRIMARY KEY, payload BLOB NOT NULL)');
            $pdo->exec('CREATE TABLE routes (route TEXT PRIMARY KEY, path TEXT NOT NULL)');
            $pdo->exec('CREATE TABLE children (path TEXT PRIMARY KEY, payload BLOB NOT NULL)');
            $pdo->exec('CREATE TABLE sorts (path TEXT PRIMARY KEY, payload BLOB NOT NULL)');
            $pdo->exec('CREATE TABLE taxonomy (type TEXT NOT NULL, value TEXT NOT NULL, path TEXT NOT NULL, payload BLOB NOT NULL)');
            $pdo->exec('CREATE INDEX taxonomy_type_value ON taxonomy (type, value)');

            $pdo->exec('BEGIN');
            $insert = $pdo->prepare('INSERT INTO pages (path, payload) VALUES (?, ?)');
            foreach ($sections['pages'] as $path => $payload) {
                $insert->execute([$path, $payload]);
            }

            $insert = $pdo->prepare('INSERT INTO routes (route, path) VALUES (?, ?)');
            foreach ($sections['routes'] as $route => $path) {
                $insert->execute([(string)$route, (string)$path]);
            }

            $insert = $pdo->prepare('INSERT INTO children (path, payload) VALUES (?, ?)');
            foreach ($sections['children'] as $path => $list) {
                $insert->execute([(string)$path, serialize((array)$list)]);
            }

            $insert = $pdo->prepare('INSERT INTO sorts (path, payload) VALUES (?, ?)');
            foreach ($sections['sorts'] as $path => $orders) {
                $insert->execute([(string)$path, serialize((array)$orders)]);
            }

            $insert = $pdo->prepare('INSERT INTO taxonomy (type, value, path, payload) VALUES (?, ?, ?, ?)');
            foreach ($sections['taxonomy'] as $type => $values) {
                foreach ((array)$values as $value => $paths) {
                    foreach ((array)$paths as $path => $info) {
                        $insert->execute([(string)$type, (string)$value, (string)$path, serialize($info)]);
                    }
                }
            }

            $metaInsert = $pdo->prepare('INSERT INTO meta (key, value) VALUES (?, ?)');
            $metaInsert->execute(['schema', (string)self::SCHEMA]);
            $metaInsert->execute(['cache_id', $cacheId]);
            $metaInsert->execute(['grav_version', GRAV_VERSION]);
            $metaInsert->execute(['complete', '1']);
            $pdo->exec('COMMIT');

            // Close the build connection before the rename.
            $pdo = null;

            if (!@rename($tmp, $this->file)) {
                @unlink($tmp);

                return false;
            }

            // Reconnect to the fresh file for reads within this request.
            $connection = self::connect($this->engine, $this->file);
            if ($connection === null) {
                return false;
            }
            $this->pdo = $connection;
            $this->readStatement = null;

            return true;
        } catch (Throwable $e) {
            @unlink($tmp);

            return false;
        }
    }
}
