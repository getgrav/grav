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

class TwigNodeTryCatch extends Node
{
    /**
     * TwigNodeTryCatch constructor.
     * @param Node $try
     * @param Node|null $catch
     * @param int $lineno
     * @param string|null $tag
     */
    public function __construct(
        Node $try,
        Node $catch = null,
        $lineno = 0,
        $tag = null
    )
    {
        parent::__construct(['try' => $try, 'catch' => $catch], [], $lineno, $tag);
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
            ->write('try {')
        ;

        $compiler
            ->indent()
            ->subcompile($this->getNode('try'))
        ;

        if ($this->hasNode('catch') && null !== $this->getNode('catch')) {
            $compiler
                ->outdent()
                ->write('} catch (\Exception $e) {' . "\n")
                ->indent()
                ->write('if (isset($context[\'grav\'][\'debugger\'])) $context[\'grav\'][\'debugger\']->addException($e);' . "\n")
                ->write('$context[\'e\'] = $e;' . "\n")
                ->subcompile($this->getNode('catch'))
            ;
        }

        $compiler
            ->outdent()
            ->write("}\n");
    }
}
