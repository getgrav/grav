<?php

/**
 * @package    Grav\Framework\Acl
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Acl;

use ArrayIterator;
use Countable;
use Grav\Common\Inflector;
use IteratorAggregate;
use RuntimeException;
use Traversable;
use function count;

/**
 * Class Action
 * @package Grav\Framework\Acl
 * @implements IteratorAggregate<string,Action>
 */
class Action implements IteratorAggregate, Countable
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
    /** @var array<string,Action> */
    protected $children = [];

    /**
     * @param string $name
     * @param array $action
     */
    public function __construct(string $name, array $action = [])
    {
        $label = $action['label'] ?? null;
        if (!$label) {
            if ($pos = mb_strrpos($name, '.')) {
                $label = mb_substr($name, $pos + 1);
            } else {
                $label = $name;
            }
            $label = Inflector::humanize($label, 'all');
        }

        $this->name = $name;
        $this->type = $action['type'] ?? 'action';
        $this->visible = (bool)($action['visible'] ?? true);
        $this->label = $label;
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
     * @return void
     */
    public function setParent(?Action $parent): void
    {
        $this->parent = $parent;
    }

    /**
     * @return string
     */
    public function getScope(): string
    {
        $pos = mb_strpos($this->name, '.');
        if ($pos) {
            return mb_substr($this->name, 0, $pos);
        }

        return $this->name;
    }

    /**
     * @return int
     */
    public function getLevels(): int
    {
        return mb_substr_count($this->name, '.');
    }

    /**
     * @return bool
     */
    public function hasChildren(): bool
    {
        return !empty($this->children);
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
     * @return void
     */
    public function addChild(Action $child): void
    {
        if (mb_strpos($child->name, "{$this->name}.") !== 0) {
            throw new RuntimeException('Bad child');
        }

        $child->setParent($this);
        $name = mb_substr($child->name, mb_strlen($this->name) + 1);

        $this->children[$name] = $child;
    }

    /**
     * @return Traversable
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->children);
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
    #[\ReturnTypeWillChange]
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
