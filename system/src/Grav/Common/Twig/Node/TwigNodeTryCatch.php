<?php

/**
 * @package    Grav\Common\Twig
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Twig\Node;

class TwigNodeTryCatch extends \Twig_Node
{
    /**
     * TwigNodeTryCatch constructor.
     * @param \Twig_Node $try
     * @param \Twig_Node|null $catch
     * @param int $lineno
     * @param string|null $tag
     */
    public function __construct(
        \Twig_Node $try,
        \Twig_Node $catch = null,
        $lineno = 0,
        $tag = null
    )
    {
        parent::__construct(['try' => $try, 'catch' => $catch], [], $lineno, $tag);
    }

    /**
     * Compiles the node to PHP.
     *
     * @param \Twig_Compiler $compiler A Twig_Compiler instance
     * @throws \LogicException
     */
    public function compile(\Twig_Compiler $compiler)
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
