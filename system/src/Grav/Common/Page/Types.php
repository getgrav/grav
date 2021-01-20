<?php

/**
 * @package    Grav\Common\Page
 *
 * @copyright  Copyright (C) 2015 - 2020 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Page;

use Grav\Common\Data\Blueprint;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use InvalidArgumentException;
use RocketTheme\Toolbox\ArrayTraits\ArrayAccess;
use RocketTheme\Toolbox\ArrayTraits\Constructor;
use RocketTheme\Toolbox\ArrayTraits\Countable;
use RocketTheme\Toolbox\ArrayTraits\Export;
use RocketTheme\Toolbox\ArrayTraits\Iterator;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;
use function is_string;

/**
 * Class Types
 * @package Grav\Common\Page
 */
class Types implements \ArrayAccess, \Iterator, \Countable
{
    use ArrayAccess, Constructor, Iterator, Countable, Export;

    /** @var array */
    protected $items;
    /** @var array */
    protected $systemBlueprints;

    /**
     * @param string $type
     * @param Blueprint|null $blueprint
     */
    public function register($type, $blueprint = null)
    {
        if (!isset($this->items[$type])) {
            $this->items[$type] = [];
        } elseif (!$blueprint) {
            return;
        }

        if (!$blueprint && $this->systemBlueprints) {
            $blueprint = $this->systemBlueprints[$type] ?? $this->systemBlueprints['default'];
        }

        if ($blueprint) {
            array_unshift($this->items[$type], $blueprint);
        }
    }

    /**
     * @param string $uri
     */
    public function scanBlueprints($uri)
    {
        if (!is_string($uri)) {
            throw new InvalidArgumentException('First parameter must be URI');
        }

        if (null === $this->systemBlueprints) {
            $this->systemBlueprints = $this->findBlueprints('blueprints://pages');

            // Register default by default.
            $this->register('default');

            $this->register('external');
        }

        foreach ($this->findBlueprints($uri) as $type => $blueprint) {
            $this->register($type, $blueprint);
        }
    }

    /**
     * @param string $uri
     */
    public function scanTemplates($uri)
    {
        if (!is_string($uri)) {
            throw new InvalidArgumentException('First parameter must be URI');
        }

        $options = [
            'compare' => 'Filename',
            'pattern' => '|\.html\.twig$|',
            'filters' => [
                'value' => '|\.html\.twig$|'
            ],
            'value' => 'Filename',
            'recursive' => false
        ];

        foreach (Folder::all($uri, $options) as $type) {
            $this->register($type);
        }

        $modular_uri = rtrim($uri, '/') . '/modular';
        if (is_dir($modular_uri)) {
            foreach (Folder::all($modular_uri, $options) as $type) {
                $this->register('modular/' . $type);
            }
        }
    }

    /**
     * @return array
     */
    public function pageSelect()
    {
        $list = [];
        foreach ($this->items as $name => $file) {
            if (strpos($name, '/')) {
                continue;
            }
            $list[$name] = ucfirst(str_replace('_', ' ', $name));
        }
        ksort($list);

        return $list;
    }

    /**
     * @return array
     */
    public function modularSelect()
    {
        $list = [];
        foreach ($this->items as $name => $file) {
            if (strpos($name, 'modular/') !== 0) {
                continue;
            }
            $list[$name] = ucfirst(trim(str_replace('_', ' ', basename($name))));
        }
        ksort($list);

        return $list;
    }

    /**
     * @param string $uri
     * @return array
     */
    private function findBlueprints($uri)
    {
        $options = [
            'compare' => 'Filename',
            'pattern' => '|\.yaml$|',
            'filters' => [
                'key' => '|\.yaml$|'
                ],
            'key' => 'SubPathName',
            'value' => 'PathName',
        ];

        /** @var UniformResourceLocator $locator */
        $locator = Grav::instance()['locator'];
        if ($locator->isStream($uri)) {
            $options['value'] = 'Url';
        }

        return Folder::all($uri, $options);
    }
}
