<?php

namespace Grav\Installer;

use Closure;

/**
 * Class VersionUpdate
 * @package Grav\Installer
 */
final class VersionUpdate
{
    /** @var string */
    private $revision;
    /** @var string */
    private $version;
    /** @var string */
    private $date;
    /** @var string */
    private $patch;
    /** @var VersionUpdater */
    private $updater;
    /** @var callable[] */
    private $methods;

    public function __construct(string $file, VersionUpdater $updater)
    {
        $name = basename($file, '.php');

        $this->revision = $name;
        [$this->version, $this->date, $this->patch] = explode('_', $name);
        $this->updater = $updater;
        $this->methods = require $file;
    }

    public function getRevision(): string
    {
        return $this->revision;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function getDate(): string
    {
        return $this->date;
    }

    public function getPatch(): string
    {
        return $this->date;
    }

    public function getUpdater(): VersionUpdater
    {
        return $this->updater;
    }

    /**
     * Run right before installation.
     */
    public function preflight(VersionUpdater $updater): void
    {
        $method = $this->methods['preflight'] ?? null;
        if ($method instanceof Closure) {
            $method->call($this);
        }
    }

    /**
     * Runs right after installation.
     */
    public function postflight(VersionUpdater $updater): void
    {
        $method = $this->methods['postflight'] ?? null;
        if ($method instanceof Closure) {
            $method->call($this);
        }
    }
}
