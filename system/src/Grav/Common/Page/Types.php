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

    public function register($type, $blueprint = null)
    {
        if ($blueprint || empty($this->items[$type])) {
            $this->items[$type] = $blueprint;
        }
    }

    public function scanBlueprints($path)
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

        $this->items = Folder::all($path, $options) + $this->items;
    }

    public function scanTemplates($path)
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

        foreach (Folder::all($path, $options) as $type) {
            $this->register($type);
        }
        if (file_exists($path . 'modular/')) {
            foreach (Folder::all($path . 'modular/', $options) as $type) {
                $this->register('modular/' . $type);
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
            $list[basename($name)] = trim(ucfirst(strtr(basename($name), '_', ' ')));
        }
        ksort($list);
        return $list;
    }
}
