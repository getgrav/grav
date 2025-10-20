<?php

/**
 * @package    Grav\Common\Recovery
 *
 * @copyright  Copyright (c) 2015 - 2025 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Recovery;

use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use Grav\Common\Yaml;
use RocketTheme\Toolbox\Event\Event;
use function bin2hex;
use function dirname;
use function file_get_contents;
use function file_put_contents;
use function in_array;
use function is_array;
use function is_file;
use function json_decode;
use function json_encode;
use function max;
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
use const E_USER_ERROR;
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
    /** @var bool */
    private $failureCaptured = false;

    /**
     * @param mixed $context Container or root path.
     */
    public function __construct($context = null)
    {
        if ($context instanceof \Grav\Common\Grav) {
            $root = GRAV_ROOT;
        } elseif (is_string($context) && $context !== '') {
            $root = $context;
        } else {
            $root = GRAV_ROOT;
        }

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
        $events = null;
        try {
            $events = Grav::instance()['events'] ?? null;
        } catch (\Throwable $e) {
            $events = null;
        }
        if ($events && method_exists($events, 'addListener')) {
            $events->addListener('onFatalException', [$this, 'onFatalException']);
        }
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

        $this->closeUpgradeWindow();
        $this->failureCaptured = false;
    }

    /**
     * Shutdown handler capturing fatal errors.
     *
     * @return void
     */
    public function handleShutdown(): void
    {
        if ($this->failureCaptured) {
            return;
        }

        $error = $this->resolveLastError();
        if (!$error) {
            return;
        }

        $this->processFailure($error);
    }

    /**
     * Handle uncaught exceptions bubbled to the top-level handler.
     *
     * @param \Throwable $exception
     * @return void
     */
    public function handleException(\Throwable $exception): void
    {
        if ($this->failureCaptured) {
            return;
        }

        $error = [
            'type' => E_ERROR,
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ];

        $this->processFailure($error);
    }

    /**
     * @param Event $event
     * @return void
     */
    public function onFatalException(Event $event): void
    {
        $exception = $event['exception'] ?? null;
        if ($exception instanceof \Throwable) {
            $this->handleException($exception);
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
        Folder::create(dirname($flag));
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
     * @param array $error
     * @return void
     */
    private function processFailure(array $error): void
    {
        $type = (int)($error['type'] ?? 0);
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

        if (!$this->shouldEnterRecovery($context)) {
            return;
        }

        $this->activate($context);
        if ($plugin) {
            $this->quarantinePlugin($plugin, $context);
        }

        $this->failureCaptured = true;
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
    public function disablePlugin(string $slug, array $context = []): void
    {
        $context += [
            'message' => $context['message'] ?? 'Disabled during upgrade preflight',
            'file' => $context['file'] ?? '',
            'line' => $context['line'] ?? null,
            'created_at' => $context['created_at'] ?? time(),
            'plugin' => $context['plugin'] ?? $slug,
        ];

        $this->quarantinePlugin($slug, $context);
    }

    /**
     * @param string $slug
     * @param array $context
     * @return void
     */
    protected function quarantinePlugin(string $slug, array $context): void
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
        return in_array($type, [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE, E_USER_ERROR], true);
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
        return $this->userPath . '/data/recovery.flag';
    }

    /**
     * @return string
     */
    private function windowPath(): string
    {
        return $this->userPath . '/data/recovery.window';
    }

    /**
     * @return array|null
     */
    private function resolveUpgradeWindow(): ?array
    {
        $path = $this->windowPath();
        if (!is_file($path)) {
            return null;
        }

        $decoded = json_decode(file_get_contents($path), true);
        if (!is_array($decoded)) {
            @unlink($path);

            return null;
        }

        $expiresAt = (int)($decoded['expires_at'] ?? 0);
        if ($expiresAt > 0 && $expiresAt < time()) {
            @unlink($path);

            return null;
        }

        return $decoded;
    }

    /**
     * @param array $context
     * @return bool
     */
    private function shouldEnterRecovery(array $context): bool
    {
        $window = $this->resolveUpgradeWindow();
        if (null === $window) {
            return false;
        }

        $scope = $window['scope'] ?? null;
        if ($scope === 'plugin') {
            $expected = $window['plugin'] ?? null;
            if ($expected && ($context['plugin'] ?? null) !== $expected) {
                return false;
            }
        }

        return true;
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

    /**
     * Begin an upgrade window; during this window fatal plugin errors may trigger recovery mode.
     *
     * @param string $reason
     * @param array $metadata
     * @param int $ttlSeconds
     * @return void
     */
    public function markUpgradeWindow(string $reason, array $metadata = [], int $ttlSeconds = 604800): void
    {
        $ttl = max(60, $ttlSeconds);
        $createdAt = time();

        $payload = $metadata + [
            'reason' => $reason,
            'created_at' => $createdAt,
            'expires_at' => $createdAt + $ttl,
        ];

        $path = $this->windowPath();
        Folder::create(dirname($path));
        file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
        $this->failureCaptured = false;
    }

    /**
     * @return bool
     */
    public function isUpgradeWindowActive(): bool
    {
        return $this->resolveUpgradeWindow() !== null;
    }

    /**
     * @return array|null
     */
    public function getUpgradeWindow(): ?array
    {
        return $this->resolveUpgradeWindow();
    }

    /**
     * @return void
     */
    public function closeUpgradeWindow(): void
    {
        $window = $this->windowPath();
        if (is_file($window)) {
            @unlink($window);
        }
    }

}
