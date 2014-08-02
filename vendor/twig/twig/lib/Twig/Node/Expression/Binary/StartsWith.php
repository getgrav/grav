<?php

/*
 * This file is part of Twig.
 *
 * (c) 2013 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
class Twig_Node_Expression_Binary_StartsWith extends Twig_Node_Expression_Binary
{
    public function compile(Twig_Compiler $compiler)
    {
        $compiler
            ->raw('(0 === strpos(')
            ->subcompile($this->getNode('left'))
            ->raw(', ')
            ->subcompile($this->getNode('right'))
            ->raw('))')
        ;
    }

    public function operator(Twig_Compiler $compiler)
    {
        return $compiler->raw('');
    }
}
