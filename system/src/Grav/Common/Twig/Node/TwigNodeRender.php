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
use Twig\Node\Expression\AbstractExpression;
use Twig\Node\Node;
use Twig\Node\NodeCaptureInterface;

/**
 * Class TwigNodeRender
 * @package Grav\Common\Twig\Node
 */
class TwigNodeRender extends Node implements NodeCaptureInterface
{
    /** @var string */
    protected $tagName = 'render';

    /**
     * @param AbstractExpression $object
     * @param AbstractExpression|null $layout
     * @param AbstractExpression|null $context
     * @param int $lineno
     * @param string|null $tag
     */
    public function __construct(AbstractExpression $object, ?AbstractExpression $layout, ?AbstractExpression $context, $lineno, $tag = null)
    {
        $nodes = ['object' => $object, 'layout' => $layout, 'context' => $context];
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
        $compiler->write('$object = ')->subcompile($this->getNode('object'))->raw(';' . PHP_EOL);

        if ($this->hasNode('layout')) {
            $layout = $this->getNode('layout');
            $compiler->write('$layout = ')->subcompile($layout)->raw(';' . PHP_EOL);
        } else {
            $compiler->write('$layout = null;' . PHP_EOL);
        }

        if ($this->hasNode('context')) {
            $context = $this->getNode('context');
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
            ->write('\Grav\Common\Assets\BlockAssets::registerAssets($html);' . PHP_EOL)
            ->write('echo (string)$html;' . PHP_EOL)
            ->outdent()
            ->write('}' . PHP_EOL)
        ;
    }
}
