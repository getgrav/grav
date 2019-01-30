<?php

/**
 * @package    Grav\Common\Twig
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Twig\Node;

class TwigNodeRender extends \Twig_Node implements \Twig_NodeCaptureInterface
{
    protected $tagName = 'render';

    /**
     * @param \Twig_Node_Expression $object
     * @param \Twig_Node_Expression|null $layout
     * @param \Twig_Node_Expression|null $context
     * @param int $lineno
     * @param string|null $tag
     */
    public function __construct(
        \Twig_Node_Expression $object,
        ?\Twig_Node_Expression $layout,
        ?\Twig_Node_Expression $context,
        $lineno,
        $tag = null
    )
    {
        parent::__construct(['object' => $object, 'layout' => $layout, 'context' => $context], [], $lineno, $tag);
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
        $compiler->write('$object = ')->subcompile($this->getNode('object'))->raw(';' . PHP_EOL);

        $layout = $this->getNode('layout');
        if ($layout) {
            $compiler->write('$layout = ')->subcompile($layout)->raw(';' . PHP_EOL);
        } else {
            $compiler->write('$layout = null;' . PHP_EOL);
        }

        $context = $this->getNode('context');
        if ($context) {
            $compiler->write('$attributes = ')->subcompile($context)->raw(';' . PHP_EOL);
        } else {
            $compiler->write('$attributes = null;' . PHP_EOL);
        }

        $compiler
            ->write('$html = $object->render($layout, $attributes ?? []);' . PHP_EOL)
            ->write('$block = $context[\'block\'] ?? null;' . PHP_EOL)
            ->write('if ($block instanceof \Grav\Framework\ContentBlock\ContentBlock && $html instanceof \Grav\Framework\ContentBlock\ContentBlock) {' . PHP_EOL)
            ->indent()
            ->write('$block->addBlock($html);' . PHP_EOL)
            ->write('echo $html->getToken();' . PHP_EOL)
            ->outdent()
            ->write('} else {' . PHP_EOL)
            ->indent()
            ->write('echo (string)$html;' . PHP_EOL)
            ->outdent()
            ->write('}' . PHP_EOL)
        ;
    }
}
