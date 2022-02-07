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
use Twig\Node\Node;

/**
 * Class TwigNodeTryCatch
 * @package Grav\Common\Twig\Node
 */
class TwigNodeTryCatch extends Node
{
    /**
     * TwigNodeTryCatch constructor.
     * @param Node $try
     * @param Node|null $catch
     * @param int $lineno
     * @param string|null $tag
     */
    public function __construct(Node $try, Node $catch = null, $lineno = 0, $tag = null)
    {
        $nodes = ['try' => $try, 'catch' => $catch];
        $nodes = array_filter($nodes);

        parent::__construct($nodes, [], $lineno, $tag);
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

        $compiler->write('try {');

        $compiler
            ->indent()
            ->subcompile($this->getNode('try'))
            ->outdent()
            ->write('} catch (\Exception $e) {' . "\n")
            ->indent()
            ->write('if (isset($context[\'grav\'][\'debugger\'])) $context[\'grav\'][\'debugger\']->addException($e);' . "\n")
            ->write('$context[\'e\'] = $e;' . "\n");

        if ($this->hasNode('catch')) {
            $compiler->subcompile($this->getNode('catch'));
        }

        $compiler
            ->outdent()
            ->write("}\n");
    }
}
