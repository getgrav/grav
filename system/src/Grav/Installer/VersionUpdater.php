<?php

namespace Grav\Installer;

use DirectoryIterator;

/**
 * Class Update_1_7_0_20201120_1
 * @package Grav\Installer\updates
 */
final class VersionUpdater
{
    /** @var string */
    private $name;
    /** @var string */
    private $path;
    /** @var Versions */
    private $versions;
    /** @var VersionUpdate[] */
    private $updates;

    /**
     * VersionUpdater constructor.
     * @param string $name
     * @param string $path
     * @param Versions $versions
     */
    public function __construct(string $name, string $path, Versions $versions)
    {
        $this->name = $name;
        $this->path = $path;
        $this->versions = $versions;

        $this->loadUpdates();
    }

    /**
     * Pre-installation methods.
     */
    public function preflight(): void
    {
        foreach ($this->updates as $revision => $update) {
            $update->preflight($this);
        }
    }

    /**
     * Post-installation methods.
     */
    public function postflight(): void
    {
        $versions = $this->getVersions();

        foreach ($this->updates as $revision => $update) {
            $update->postflight($this);

            $versions->setSchema($this->name, $revision);
            $versions->save();
        }
    }

    /**
     * @return Versions
     */
    public function getVersions(): Versions
    {
        return $this->versions;
    }

    /**
     * @param string|null $name
     * @return string|null
     */
    public function getExtensionVersion(string $name = null): ?string
    {
        return $this->versions->getVersion($name ?? $this->name);
    }

    /**
     * @param string|null $name
     * @return string|null
     */
    public function getExtensionSchema(string $name = null): ?string
    {
        return $this->versions->getSchema($name ?? $this->name);
    }

    /**
     * @param string|null $name
     * @return array
     */
    public function getExtensionHistory(string $name = null): array
    {
        return $this->versions->getHistory($name ?? $this->name);
    }

    protected function loadUpdates(): void
    {
        $schema = $this->getExtensionSchema();
        $iterator = new DirectoryIterator($this->path);
        foreach ($iterator as $item) {
            if (!$item->isFile() || $item->getExtension() !== 'php') {
                continue;
            }

            $revision = $item->getBasename('.php');
            if (!$schema || version_compare($revision, $schema, '>')) {
                $this->updates[$revision] = new VersionUpdate($item->getRealPath(), $this);
            }
        }

        uksort($this->updates, 'version_compare');
    }
}
