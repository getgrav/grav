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

    public function scanBlueprints($uri)
    {
        if (!is_string($uri)) {
            throw new \InvalidArgumentException('First parameter must be URI');
        }

        $this->items = $this->findBlueprints($uri) + $this->items;
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

        if (!$this->systemBlueprints) {
            $this->systemBlueprints = $this->findBlueprints('blueprints://pages');
        }

        // register default by default
        $this->register('default');

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

        $list = Folder::all($uri, $options);

        return $list;
    }
}
