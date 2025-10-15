<?php

/**
 * @package    Grav\Common\Recovery
 *
 * @copyright  Copyright (c) 2015 - 2025 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Recovery;

use Grav\Common\Filesystem\Folder;
use Grav\Common\Yaml;
use function bin2hex;
use function dirname;
use function file_get_contents;
use function file_put_contents;
use function in_array;
use function is_array;
use function is_file;
use function json_decode;
use function json_encode;
use function md5;
use function preg_match;
use function random_bytes;
use function uniqid;
use function time;
use function trim;
use function unlink;
use const E_COMPILE_ERROR;
use const E_CORE_ERROR;
use const E_ERROR;
use const E_PARSE;
use const GRAV_ROOT;
use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_SLASHES;

/**
 * Handles recovery flag lifecycle and plugin quarantine during fatal errors.
 */
class RecoveryManager
{
    /** @var bool */
    private $registered = false;
    /** @var string */
    private $rootPath;
    /** @var string */
    private $userPath;

    public function __construct(?string $rootPath = null)
    {
        $root = $rootPath ?? GRAV_ROOT;
        $this->rootPath = rtrim($root, DIRECTORY_SEPARATOR);
        $this->userPath = $this->rootPath . '/user';
    }

    /**
     * Register shutdown handler to capture fatal errors at runtime.
     *
     * @return void
     */
    public function registerHandlers(): void
    {
        if ($this->registered) {
            return;
        }

        register_shutdown_function([$this, 'handleShutdown']);
        $this->registered = true;
    }

    /**
     * Check if recovery mode flag is active.
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return is_file($this->flagPath());
    }

    /**
     * Remove recovery flag.
     *
     * @return void
     */
    public function clear(): void
    {
        $flag = $this->flagPath();
        if (is_file($flag)) {
            @unlink($flag);
        }
    }

    /**
     * Shutdown handler capturing fatal errors.
     *
     * @return void
     */
    public function handleShutdown(): void
    {
        $error = $this->resolveLastError();
        if (!$error) {
            return;
        }

        $type = $error['type'] ?? 0;
        if (!$this->isFatal($type)) {
            return;
        }

        $file = $error['file'] ?? '';
        $plugin = $this->detectPluginFromPath($file);
        $context = [
            'created_at' => time(),
            'message' => $error['message'] ?? '',
            'file' => $file,
            'line' => $error['line'] ?? null,
            'type' => $type,
            'plugin' => $plugin,
        ];

        $this->activate($context);
        if ($plugin) {
            $this->quarantinePlugin($plugin, $context);
        }
    }

    /**
     * Activate recovery mode and record context.
     *
     * @param array $context
     * @return void
     */
    public function activate(array $context): void
    {
        $flag = $this->flagPath();
        if (empty($context['token'])) {
            $context['token'] = $this->generateToken();
        }
        if (!is_file($flag)) {
            file_put_contents($flag, json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
        } else {
            // Merge context if flag already exists.
            $existing = json_decode(file_get_contents($flag), true);
            if (is_array($existing)) {
                $context = $context + $existing;
                if (empty($context['token'])) {
                    $context['token'] = $this->generateToken();
                }
            }
            file_put_contents($flag, json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
        }
    }

    /**
     * Return last recorded recovery context.
     *
     * @return array|null
     */
    public function getContext(): ?array
    {
        $flag = $this->flagPath();
        if (!is_file($flag)) {
            return null;
        }

        $decoded = json_decode(file_get_contents($flag), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param string $slug
     * @param array $context
     * @return void
     */
    private function quarantinePlugin(string $slug, array $context): void
    {
        $slug = trim($slug);
        if ($slug === '') {
            return;
        }

        $configPath = $this->userPath . '/config/plugins/' . $slug . '.yaml';
        Folder::create(dirname($configPath));

        $configuration = is_file($configPath) ? Yaml::parse(file_get_contents($configPath)) : [];
        if (!is_array($configuration)) {
            $configuration = [];
        }

        if (($configuration['enabled'] ?? true) === false) {
            return;
        }

        $configuration['enabled'] = false;
        $yaml = Yaml::dump($configuration);
        file_put_contents($configPath, $yaml);

        $quarantineFile = $this->userPath . '/data/upgrades/quarantine.json';
        Folder::create(dirname($quarantineFile));

        $quarantine = [];
        if (is_file($quarantineFile)) {
            $decoded = json_decode(file_get_contents($quarantineFile), true);
            if (is_array($decoded)) {
                $quarantine = $decoded;
            }
        }

        $quarantine[$slug] = [
            'slug' => $slug,
            'disabled_at' => time(),
            'message' => $context['message'] ?? '',
            'file' => $context['file'] ?? '',
            'line' => $context['line'] ?? null,
        ];

        file_put_contents($quarantineFile, json_encode($quarantine, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    }

    /**
     * Determine if error type is fatal.
     *
     * @param int $type
     * @return bool
     */
    private function isFatal(int $type): bool
    {
        return in_array($type, [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE], true);
    }

    /**
     * Attempt to derive plugin slug from file path.
     *
     * @param string $file
     * @return string|null
     */
    private function detectPluginFromPath(string $file): ?string
    {
        if (!$file) {
            return null;
        }

        if (preg_match('#/user/plugins/([^/]+)/#', $file, $matches)) {
            return $matches[1] ?? null;
        }

        return null;
    }

    /**
     * @return string
     */
    private function flagPath(): string
    {
        return $this->rootPath . '/system/recovery.flag';
    }

    /**
     * @return string
     */
    protected function generateToken(): string
    {
        try {
            return bin2hex($this->randomBytes(10));
        } catch (\Throwable $e) {
            return md5(uniqid('grav-recovery', true));
        }
    }

    /**
     * @param int $length
     * @return string
     */
    protected function randomBytes(int $length): string
    {
        return random_bytes($length);
    }

    /**
     * @return array|null
     */
    protected function resolveLastError(): ?array
    {
        return error_get_last();
    }
}
