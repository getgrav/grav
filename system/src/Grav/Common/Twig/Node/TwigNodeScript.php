<?php

/**
 * @package    Grav\Common\Twig
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Twig\Node;

class TwigNodeScript extends \Twig_Node implements \Twig_NodeCaptureInterface
{
    protected $tagName = 'script';

    /**
     * TwigNodeScript constructor.
     * @param \Twig_Node|null $body
     * @param \Twig_Node_Expression|null $file
     * @param \Twig_Node_Expression|null $group
     * @param \Twig_Node_Expression|null $priority
     * @param \Twig_Node_Expression|null $attributes
     * @param int $lineno
     * @param string|null $tag
     */
    public function __construct(
        \Twig_Node $body = null,
        \Twig_Node_Expression $file = null,
        \Twig_Node_Expression $group = null,
        \Twig_Node_Expression $priority = null,
        \Twig_Node_Expression $attributes = null,
        $lineno = 0,
        $tag = null
    )
    {
        parent::__construct(['body' => $body, 'file' => $file, 'group' => $group, 'priority' => $priority, 'attributes' => $attributes], [], $lineno, $tag);
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

        $compiler->write("\$assets = \\Grav\\Common\\Grav::instance()['assets'];\n");

        if ($this->getNode('attributes') !== null) {
            $compiler
                ->write('$attributes = ')
                ->subcompile($this->getNode('attributes'))
                ->raw(";\n")
                ->write("if (!is_array(\$attributes)) {\n")
                ->indent()
                ->write("throw new UnexpectedValueException('{% {$this->tagName} with x %}: x is not an array');\n")
                ->outdent()
                ->write("}\n");
        } else {
            $compiler->write('$attributes = [];' . "\n");
        }

         if ($this->getNode('group') !== null) {
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

        if ($this->getNode('priority') !== null) {
            $compiler
                ->write("\$attributes['priority'] = (int)(")
                ->subcompile($this->getNode('priority'))
                ->raw(");\n");
        }

        if ($this->getNode('file') !== null) {
            $compiler
                ->write('$assets->addJs(')
                ->subcompile($this->getNode('file'))
                ->raw(", \$attributes);\n");
        } else {
            $compiler
                ->write("ob_start();\n")
                ->subcompile($this->getNode('body'))
                ->write('$content = ob_get_clean();' . "\n")
                ->write("\$assets->addInlineJs(\$content, \$attributes);\n");
        }
    }
}
