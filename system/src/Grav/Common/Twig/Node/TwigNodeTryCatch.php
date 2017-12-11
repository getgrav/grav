<?php
/**
 * @package    Grav.Common.Twig
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Twig\Node;

class TwigNodeTryCatch extends \Twig_Node
{
    public function __construct(\Twig_NodeInterface $try, \Twig_NodeInterface $catch = null, $lineno, $tag = null)
    {
        parent::__construct(array('try' => $try, 'catch' => $catch), array(), $lineno, $tag);
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
