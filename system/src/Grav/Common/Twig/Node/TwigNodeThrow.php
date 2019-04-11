<?php

/**
 * @package    Grav\Common\Twig
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Twig\Node;

use Twig\Compiler;
use Twig\Node\Node;

class TwigNodeThrow extends Node
{
    /**
     * TwigNodeThrow constructor.
     * @param int $code
     * @param Node $message
     * @param int $lineno
     * @param string|null $tag
     */
    public function __construct(
        $code,
        Node $message,
        $lineno = 0,
        $tag = null
    )
    {
        parent::__construct(['message' => $message], ['code' => $code], $lineno, $tag);
    }

    /**
     * Compiles the node to PHP.
     *
     * @param Compiler $compiler A Twig_Compiler instance
     * @throws \LogicException
     */
    public function compile(Compiler $compiler)
    {
        $compiler->addDebugInfo($this);

        $compiler
            ->write('throw new \RuntimeException(')
            ->subcompile($this->getNode('message'))
            ->write(', ')
            ->write($this->getAttribute('code') ?: 500)
            ->write(");\n");
    }
}
