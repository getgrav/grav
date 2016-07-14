<?php
/**
 * @package    Grav.Common.Page
 *
 * @copyright  Copyright (C) 2014 - 2016 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Page;

use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use RocketTheme\Toolbox\ArrayTraits\ArrayAccess;
use RocketTheme\Toolbox\ArrayTraits\Constructor;
use RocketTheme\Toolbox\ArrayTraits\Countable;
use RocketTheme\Toolbox\ArrayTraits\Export;
use RocketTheme\Toolbox\ArrayTraits\Iterator;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

class Types implements \ArrayAccess, \Iterator, \Countable
{
    use ArrayAccess, Constructor, Iterator, Countable, Export;

    protected $items;
    protected $systemBlueprints;

    public function register($type, $blueprint = null)
    {
        if (!isset($this->items[$type])) {
            $this->items[$type] = [];
        } elseif (!$blueprint) {
            return;
        }

        if (!$blueprint && $this->systemBlueprints) {
            $blueprint = isset($this->systemBlueprints[$type]) ? $this->systemBlueprints[$type] : $this->systemBlueprints['default'];
        }

        if ($blueprint) {
            array_unshift($this->items[$type], $blueprint);
        }
    }

    public function scanBlueprints($uri)
    {
        if (!is_string($uri)) {
            throw new \InvalidArgumentException('First parameter must be URI');
        }

        if (!$this->systemBlueprints) {
            $this->systemBlueprints = $this->findBlueprints('blueprints://pages');

            // Register default by default.
            $this->register('default');
        }

        foreach ($this->findBlueprints($uri) as $type => $blueprint) {
            $this->register($type, $blueprint);
        }
    }

    public function scanTemplates($uri)
    {
        if (!is_string($uri)) {
            throw new \InvalidArgumentException('First parameter must be URI');
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
        foreach (Folder::all($modular_uri, $options) as $type) {
            $this->register('modular/' . $type);
        }
    }

    public function pageSelect()
    {
        $list = [];
        foreach ($this->items as $name => $file) {
            if (strpos($name, '/')) {
                continue;
            }
            $list[$name] = ucfirst(strtr($name, '_', ' '));
        }
        ksort($list);
        return $list;
    }

    public function modularSelect()
    {
        $list = [];
        foreach ($this->items as $name => $file) {
            if (strpos($name, 'modular/') !== 0) {
                continue;
            }
            $list[$name] = trim(ucfirst(strtr(basename($name), '_', ' ')));
        }
        ksort($list);
        return $list;
    }

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

        $list = Folder::all($uri, $options);

        return $list;
    }
}
