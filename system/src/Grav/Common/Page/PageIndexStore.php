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
    private const SCHEMA = 1;

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
     * Rebuild the store from scratch with the given payloads.
     *
     * Writes into a temporary file and renames it over the live one so that
     * concurrent readers keep a consistent view. Returns false on any failure,
     * in which case the caller should fall back to the classic cache format.
     *
     * @param string $cacheId
     * @param iterable<string,string> $payloads path => serialized page
     * @return bool
     */
    public function rebuild(string $cacheId, iterable $payloads): bool
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

            $pdo->exec('BEGIN');
            $insert = $pdo->prepare('INSERT INTO pages (path, payload) VALUES (?, ?)');
            foreach ($payloads as $path => $payload) {
                $insert->execute([$path, $payload]);
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
