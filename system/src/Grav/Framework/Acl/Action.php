<?php

/**
 * @package    Grav\Framework\Acl
 *
 * @copyright  Copyright (C) 2015 - 2020 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Acl;

/**
 * Class Action
 * @package Grav\Framework\Acl
 */
class Action implements \IteratorAggregate, \Countable
{
    /** @var string */
    public $name;
    /** @var string */
    public $type;
    /** @var bool */
    public $visible;
    /** @var string|null */
    public $label;
    /** @var array */
    public $params;

    /** @var Action|null */
    protected $parent;
    /** @var Action[] */
    protected $children = [];

    /**
     * @param string $name
     * @param array $action
     */
    public function __construct(string $name, array $action = [])
    {
        $this->name = $name;
        $this->type = $action['type'] ?? 'action';
        $this->visible = (bool)($action['visible'] ?? true);
        $this->label = $action['label'] ?? null;
        unset($action['type'], $action['label']);
        $this->params = $action;

        // Include compact rules.
        if (isset($action['letters'])) {
            foreach ($action['letters'] as $letter => $data) {
                $data['letter'] = $letter;
                $childName = $this->name . '.' . $data['action'];
                unset($data['action']);
                $child = new Action($childName, $data);
                $this->addChild($child);
            }
        }
    }

    /**
     * @return array
     */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * @param string $name
     * @return mixed|null
     */
    public function getParam(string $name)
    {
        return $this->params[$name] ?? null;
    }

    /**
     * @return Action|null
     */
    public function getParent(): ?Action
    {
        return $this->parent;
    }

    /**
     * @param Action|null $parent
     */
    public function setParent(?Action $parent): void
    {
        $this->parent = $parent;
    }

    /**
     * @return Action[]
     */
    public function getChildren(): array
    {
        return $this->children;
    }

    /**
     * @param string $name
     * @return Action|null
     */
    public function getChild(string $name): ?Action
    {
        return $this->children[$name] ?? null;
    }

    /**
     * @param Action $child
     */
    public function addChild(Action $child): void
    {
        if (strpos($child->name, "{$this->name}.") !== 0) {
            throw new \RuntimeException('Bad child');
        }

        $child->setParent($this);
        $name = substr($child->name, strlen($this->name) + 1);

        $this->children[$name] = $child;
    }

    /**
     * @return \Traversable
     */
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->children);
    }

    /**
     * @return int
     */
    public function count(): int
    {
        return count($this->children);
    }

    /**
     * @return array
     */
    public function __debugInfo()
    {
        return [
            'name' => $this->name,
            'type' => $this->type,
            'label' => $this->label,
            'params' => $this->params,
            'actions' => $this->children
        ];
    }
}
