<?php

/**
 * @package    Grav\Common\Twig
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Twig\Node;

use LogicException;
use Twig\Compiler;
use Twig\Node\Expression\AbstractExpression;
use Twig\Node\Node;
use Twig\Node\NodeCaptureInterface;

/**
 * Class TwigNodeLink
 * @package Grav\Common\Twig\Node
 */
class TwigNodeLink extends Node implements NodeCaptureInterface
{
    /** @var string */
    protected $tagName = 'link';

    /**
     * TwigNodeLink constructor.
     * @param string|null $rel
     * @param AbstractExpression|null $file
     * @param AbstractExpression|null $group
     * @param AbstractExpression|null $priority
     * @param AbstractExpression|null $attributes
     * @param int $lineno
     * @param string|null $tag
     */
    public function __construct(?string $rel, ?AbstractExpression $file, ?AbstractExpression $group, ?AbstractExpression $priority, ?AbstractExpression $attributes, $lineno = 0, $tag = null)
    {
        $nodes = ['file' => $file, 'group' => $group, 'priority' => $priority, 'attributes' => $attributes];
        $nodes = array_filter($nodes);

        parent::__construct($nodes, ['rel' => $rel], $lineno, $tag);
    }

    /**
     * Compiles the node to PHP.
     *
     * @param Compiler $compiler A Twig Compiler instance
     * @return void
     * @throws LogicException
     */
    public function compile(Compiler $compiler): void
    {
        $compiler->addDebugInfo($this);
        if (!$this->hasNode('file')) {
            return;
        }

        $compiler->write("\$assets = \\Grav\\Common\\Grav::instance()['assets'];\n");

        $compiler->write('$attributes = [\'rel\' => \'' . $this->getAttribute('rel') . '\'];' . "\n");
        if ($this->hasNode('attributes')) {
            $compiler
                ->write('$attributes += ')
                ->subcompile($this->getNode('attributes'))
                ->raw(";\n")
                ->write("if (!is_array(\$attributes)) {\n")
                ->indent()
                ->write("throw new UnexpectedValueException('{% {$this->tagName} with x %}: x is not an array');\n")
                ->outdent()
                ->write("}\n");
        }

        if ($this->hasNode('group')) {
            $compiler
                ->write("\$attributes['group'] = ")
                ->subcompile($this->getNode('group'))
                ->raw(";\n")
                ->write("if (!is_string(\$attributes['group'])) {\n")
                ->indent()
                ->write("throw new UnexpectedValueException('{% {$this->tagName} in x %}: x is not a string');\n")
                ->outdent()
                ->write("}\n");
        }

        if ($this->hasNode('priority')) {
            $compiler
                ->write("\$attributes['priority'] = (int)(")
                ->subcompile($this->getNode('priority'))
                ->raw(");\n");
        }

        $compiler
            ->write('$assets->addLink(')
            ->subcompile($this->getNode('file'))
            ->raw(", \$attributes);\n");
    }
}
