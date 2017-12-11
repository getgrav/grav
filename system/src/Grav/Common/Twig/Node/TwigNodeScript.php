<?php
/**
 * @package    Grav.Common.Twig
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Twig\Node;

class TwigNodeScript extends \Twig_Node implements \Twig_NodeOutputInterface
{
    protected $tagName = 'script';

    /**
     * TwigNodeScript constructor.
     * @param \Twig_NodeInterface|null $body
     * @param \Twig_Node_Expression|null $file
     * @param \Twig_Node_Expression|null $group
     * @param \Twig_Node_Expression|null $priority
     * @param \Twig_Node_Expression|null $attributes
     * @param int $lineno
     * @param string|null $tag
     */
    public function __construct(
        \Twig_NodeInterface $body = null,
        \Twig_Node_Expression $file = null,
        \Twig_Node_Expression $group = null,
        \Twig_Node_Expression $priority = null,
        \Twig_Node_Expression $attributes = null,
        $lineno,
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

        if ($this->getNode('attributes') !== null) {
            $compiler
                ->write('$attributes = ')
                ->subcompile($this->getNode('attributes'))
                ->raw(";\n")
                ->write("if (\$attributes !== null && !is_array(\$attributes)) {\n")
                ->indent()
                ->write("throw new UnexpectedValueException('{% {$this->tagName} with x %}: x is not an array');\n")
                ->outdent()
                ->write("}\n");
        } else {
            $compiler->write('$attributes = [];' . "\n");
        }

         if ($this->getNode('group') !== null) {
             $compiler
                 ->write('$group = ')
                 ->subcompile($this->getNode('group'))
                 ->raw(";\n")
                 ->write("if (\$group !== null && !is_string(\$group)) {\n")
                 ->indent()
                 ->write("throw new UnexpectedValueException('{% {$this->tagName} in x %}: x is not a string');\n")
                 ->outdent()
                 ->write("}\n");
         } else {
            $compiler->write('$group = null;' . "\n");
         }

        if ($this->getNode('priority') !== null) {
            $compiler
                ->write('$priority = (int)(')
                ->subcompile($this->getNode('priority'))
                ->raw(");\n");
        } else {
            $compiler->write('$priority = null;' . "\n");
        }

        $compiler->write("\$assets = \\Grav\\Common\\Grav::instance()['assets'];\n");

        if ($this->getNode('file') !== null) {
            $compiler
                ->write('$file = ')
                ->subcompile($this->getNode('file'))
                ->write(";\n")
                ->write("\$pipeline = !empty(\$attributes['pipeline']);\n")
                ->write("\$loading = !empty(\$attributes['defer']) ? 'defer' : (!empty(\$attributes['async']) ? 'async' : null);\n")
                ->write("\$assets->addJs(\$file, \$priority, \$pipeline, \$loading, \$group);\n");
        } else {
            $compiler
                ->write("ob_start();\n")
                ->subcompile($this->getNode('body'))
                ->write("\$content = ob_get_clean();")
                ->write("\$assets->addInlineJs(\$content, \$priority, \$group, \$attributes);\n");
        }
    }
}
