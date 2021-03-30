<?php

/**
 * @package    Grav\Installer
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Installer;

use Symfony\Component\Yaml\Yaml;
use function is_array;
use function is_string;

/**
 * Grav Versions
 *
 * NOTE: This class can be initialized during upgrade from an older version of Grav. Make sure it runs there!
 */
final class Versions
{
    /** @var string */
    protected $filename;
    /** @var array */
    protected $items;
    /** @var bool */
    protected $updated = false;

    /** @var self[] */
    protected static $instance;

    /**
     * @param string|null $filename
     * @return self
     */
    public static function instance(string $filename = null): self
    {
        $filename = $filename ?? USER_DIR . 'config/versions.yaml';

        if (!isset(self::$instance[$filename])) {
            self::$instance[$filename] = new self($filename);
        }

        return self::$instance[$filename];
    }

    /**
     * @return bool True if the file was updated.
     */
    public function save(): bool
    {
        if (!$this->updated) {
            return false;
        }

        file_put_contents($this->filename, Yaml::dump($this->items, 4, 2));

        $this->updated = false;

        return true;
    }

    /**
     * @return array
     */
    public function getAll(): array
    {
        return $this->items;
    }

    /**
     * @return array|null
     */
    public function getGrav(): ?array
    {
        return $this->get('core/grav');
    }

    /**
     * @return array
     */
    public function getPlugins(): array
    {
        return $this->get('plugins', []);
    }

    /**
     * @param string $name
     * @return array|null
     */
    public function getPlugin(string $name): ?array
    {
        return $this->get("plugins/{$name}");
    }

    /**
     * @return array
     */
    public function getThemes(): array
    {
        return $this->get('themes', []);
    }

    /**
     * @param string $name
     * @return array|null
     */
    public function getTheme(string $name): ?array
    {
        return $this->get("themes/{$name}");
    }

    /**
     * @param string $extension
     * @return array|null
     */
    public function getExtension(string $extension): ?array
    {
        return $this->get($extension);
    }

    /**
     * @param string $extension
     * @param array|null $value
     */
    public function setExtension(string $extension, ?array $value): void
    {
        if (null !== $value) {
            $this->set($extension, $value);
        } else {
            $this->undef($extension);
        }
    }

    /**
     * @param string $extension
     * @return string|null
     */
    public function getVersion(string $extension): ?string
    {
        $version = $this->get("{$extension}/version", null);

        return is_string($version) ? $version : null;
    }

    /**
     * @param string $extension
     * @param string|null $version
     */
    public function setVersion(string $extension, ?string $version): void
    {
        $this->updateHistory($extension, $version);
    }

    /**
     * NOTE: Updates also history.
     *
     * @param string $extension
     * @param string|null $version
     */
    public function updateVersion(string $extension, ?string $version): void
    {
        $this->set("{$extension}/version", $version);
        $this->updateHistory($extension, $version);
    }

    /**
     * @param string $extension
     * @return string|null
     */
    public function getSchema(string $extension): ?string
    {
        $version = $this->get("{$extension}/schema", null);

        return is_string($version) ? $version : null;
    }

    /**
     * @param string $extension
     * @param string|null $schema
     */
    public function setSchema(string $extension, ?string $schema): void
    {
        if (null !== $schema) {
            $this->set("{$extension}/schema", $schema);
        } else {
            $this->undef("{$extension}/schema");
        }
    }

    /**
     * @param string $extension
     * @return array
     */
    public function getHistory(string $extension): array
    {
        $name = "{$extension}/history";
        $history = $this->get($name, []);

        // Fix for broken Grav 1.6 history
        if ($extension === 'grav') {
            $history = $this->fixHistory($history);
        }

        return $history;
    }

    /**
     * @param string $extension
     * @param string|null $version
     */
    public function updateHistory(string $extension, ?string $version): void
    {
        $name = "{$extension}/history";
        $history = $this->getHistory($extension);
        $history[] = ['version' => $version, 'date' => gmdate('Y-m-d H:i:s')];
        $this->set($name, $history);
    }

    /**
     * Clears extension history. Useful when creating skeletons.
     *
     * @param string $extension
     */
    public function removeHistory(string $extension): void
    {
        $this->undef("{$extension}/history");
    }

    /**
     * @param array $history
     * @return array
     */
    private function fixHistory(array $history): array
    {
        if (isset($history['version'], $history['date'])) {
            $fix = [['version' => $history['version'], 'date' => $history['date']]];
            unset($history['version'], $history['date']);
            $history = array_merge($fix, $history);
        }

        return $history;
    }

    /**
     * Get value by using dot notation for nested arrays/objects.
     *
     * @param string $name Slash separated path to the requested value.
     * @param mixed $default Default value (or null).
     * @return mixed Value.
     */
    private function get(string $name, $default = null)
    {
        $path = explode('/', $name);
        $current = $this->items;

        foreach ($path as $field) {
            if (is_array($current) && isset($current[$field])) {
                $current = $current[$field];
            } else {
                return $default;
            }
        }

        return $current;
    }

    /**
     * Set value by using dot notation for nested arrays/objects.
     *
     * @param string $name Slash separated path to the requested value.
     * @param mixed $value New value.
     */
    private function set(string $name, $value): void
    {
        $path = explode('/', $name);
        $current = &$this->items;

        foreach ($path as $field) {
            // Handle arrays and scalars.
            if (!is_array($current)) {
                $current = [$field => []];
            } elseif (!isset($current[$field])) {
                $current[$field] = [];
            }
            $current = &$current[$field];
        }

        $current = $value;
        $this->updated = true;
    }

    /**
     * Unset value by using dot notation for nested arrays/objects.
     *
     * @param string $name Dot separated path to the requested value.
     */
    private function undef(string $name): void
    {
        $path = $name !== '' ? explode('/', $name) : [];
        if (!$path) {
            return;
        }

        $var = array_pop($path);
        $current = &$this->items;

        foreach ($path as $field) {
            if (!is_array($current) || !isset($current[$field])) {
                return;
            }
            $current = &$current[$field];
        }

        unset($current[$var]);
        $this->updated = true;
    }

    private function __construct(string $filename)
    {
        $this->filename = $filename;
        $content = is_file($filename) ? file_get_contents($filename) : null;
        if (false === $content) {
            throw new \RuntimeException('Versions file cannot be read');
        }
        $this->items = $content ? Yaml::parse($content) : [];
    }
}
