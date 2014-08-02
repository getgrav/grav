<?php

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Represents a macro node.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Twig_Node_Macro extends Twig_Node
{
    public function __construct($name, Twig_NodeInterface $body, Twig_NodeInterface $arguments, $lineno, $tag = null)
    {
        parent::__construct(array('body' => $body, 'arguments' => $arguments), array('name' => $name), $lineno, $tag);
    }

    /**
     * Compiles the node to PHP.
     *
     * @param Twig_Compiler $compiler A Twig_Compiler instance
     */
    public function compile(Twig_Compiler $compiler)
    {
        $compiler
            ->addDebugInfo($this)
            ->write(sprintf("public function get%s(", $this->getAttribute('name')))
        ;

        $count = count($this->getNode('arguments'));
        $pos = 0;
        foreach ($this->getNode('arguments') as $name => $default) {
            $compiler
                ->raw('$_'.$name.' = ')
                ->subcompile($default)
            ;

            if (++$pos < $count) {
                $compiler->raw(', ');
            }
        }

        $compiler
            ->raw(")\n")
            ->write("{\n")
            ->indent()
        ;

        if (!count($this->getNode('arguments'))) {
            $compiler->write("\$context = \$this->env->getGlobals();\n\n");
        } else {
            $compiler
                ->write("\$context = \$this->env->mergeGlobals(array(\n")
                ->indent()
            ;

            foreach ($this->getNode('arguments') as $name => $default) {
                $compiler
                    ->write('')
                    ->string($name)
                    ->raw(' => $_'.$name)
                    ->raw(",\n")
                ;
            }

            $compiler
                ->outdent()
                ->write("));\n\n")
            ;
        }

        $compiler
            ->write("\$blocks = array();\n\n")
            ->write("ob_start();\n")
            ->write("try {\n")
            ->indent()
            ->subcompile($this->getNode('body'))
            ->outdent()
            ->write("} catch (Exception \$e) {\n")
            ->indent()
            ->write("ob_end_clean();\n\n")
            ->write("throw \$e;\n")
            ->outdent()
            ->write("}\n\n")
            ->write("return ('' === \$tmp = ob_get_clean()) ? '' : new Twig_Markup(\$tmp, \$this->env->getCharset());\n")
            ->outdent()
            ->write("}\n\n")
        ;
    }
}
