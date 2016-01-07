<?php
namespace Grav\Common\Page;

use Grav\Common\Filesystem\Folder;
use RocketTheme\Toolbox\ArrayTraits\ArrayAccess;
use RocketTheme\Toolbox\ArrayTraits\Constructor;
use RocketTheme\Toolbox\ArrayTraits\Countable;
use RocketTheme\Toolbox\ArrayTraits\Export;
use RocketTheme\Toolbox\ArrayTraits\Iterator;

class Types implements \ArrayAccess, \Iterator, \Countable
{
    use ArrayAccess, Constructor, Iterator, Countable, Export;

    protected $items;
    protected $systemBlueprints;

    public function register($type, $blueprint = null)
    {
        if (!$blueprint && $this->systemBlueprints && isset($this->systemBlueprints[$type])) {
            $useBlueprint = $this->systemBlueprints[$type];
        } else {
            $useBlueprint = $blueprint;
        }

        if ($blueprint || empty($this->items[$type])) {
            $this->items[$type] = $useBlueprint;
        }
    }

    public function scanBlueprints($paths)
    {
        $this->items = $this->findBlueprints($paths) + $this->items;
    }

    public function scanTemplates($paths)
    {
        $options = [
            'compare' => 'Filename',
            'pattern' => '|\.html\.twig$|',
            'filters' => [
                'value' => '|\.html\.twig$|'
            ],
            'value' => 'Filename',
            'recursive' => false
        ];

        if (!$this->systemBlueprints) {
            $this->systemBlueprints = $this->findBlueprints('blueprints://pages');
        }

        // register default by default
        $this->register('default');

        foreach ((array) $paths as $path) {
            foreach (Folder::all($path, $options) as $type) {
                $this->register($type);
            }
            $modular_path = rtrim($path, '/') . '/modular';
            if (file_exists($modular_path)) {
                foreach (Folder::all($modular_path, $options) as $type) {
                    $this->register('modular/' . $type);
                }
            }
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

    private function findBlueprints($paths)
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

        $list = [];
        foreach ((array) $paths as $path) {
            $list += Folder::all($path, $options);
        }

        return $list;
    }
}
