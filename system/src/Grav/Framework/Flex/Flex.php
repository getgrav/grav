<?php

declare(strict_types=1);

/**
 * @package    Grav\Framework\Flex
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Flex;

use Grav\Framework\Flex\Interfaces\FlexObjectInterface;

/**
 * Class Flex
 * @package Grav\Framework\Flex
 */
class Flex implements \Countable
{
    /** @var array */
    protected $config;

    /** @var FlexDirectory[] */
    protected $types;

    /**
     * Flex constructor.
     * @param array $types  List of [type => blueprint file, ...]
     * @param array $config
     */
    public function __construct(array $types, array $config)
    {
        $this->config = $config;
        $this->types = [];

        foreach ($types as $type => $blueprint) {
            $this->addDirectory($type, $blueprint);
        }
    }

    /**
     * @param string $type
     * @param string $blueprint
     * @param array  $config
     * @return $this
     */
    public function addDirectory(string $type, string $blueprint, array $config = []) : self
    {
        $config = array_merge_recursive(['enabled' => true], $this->config['object'] ?? [], $config);

        $this->types[$type] = new FlexDirectory($type, $blueprint, $config);

        return $this;
    }

    /**
     * @return array
     */
    public function getDirectories() : array
    {
        return $this->types;
    }

    /**
     * @param string|null $type
     * @return FlexDirectory|null
     */
    public function getDirectory(string $type = null) : ?FlexDirectory
    {
        if (!$type) {
            return reset($this->types) ?: null;
        }

        return $this->types[$type] ?? null;
    }

    public function getObject(string $type, string $key): ?FlexObjectInterface
    {
        $directory = $type ? $this->getDirectory($type) : null;

        return $directory ? $directory->getObject($key) : null;
    }

    /**
     * @return int
     */
    public function count() : int
    {
        return \count($this->types);
    }
}
