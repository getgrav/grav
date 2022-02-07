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
 * Class TwigNodeLink
 * @package Grav\Common\Twig\Node
 */
class TwigNodeLink extends Node implements NodeCaptureInterface
{
    /** @var string */
    protected $tagName = 'link';

    /**
     * TwigNodeLink constructor.
     * @param string|null $rel
     * @param AbstractExpression|null $file
     * @param AbstractExpression|null $group
     * @param AbstractExpression|null $priority
     * @param AbstractExpression|null $attributes
     * @param int $lineno
     * @param string|null $tag
     */
    public function __construct(?string $rel, ?AbstractExpression $file, ?AbstractExpression $group, ?AbstractExpression $priority, ?AbstractExpression $attributes, $lineno = 0, $tag = null)
    {
        $nodes = ['file' => $file, 'group' => $group, 'priority' => $priority, 'attributes' => $attributes];
        $nodes = array_filter($nodes);

        parent::__construct($nodes, ['rel' => $rel], $lineno, $tag);
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
        if (!$this->hasNode('file')) {
            return;
        }

        $compiler->write('$attributes = [\'rel\' => \'' . $this->getAttribute('rel') . '\'];' . "\n");
        if ($this->hasNode('attributes')) {
            $compiler
                ->write('$attributes += ')
                ->subcompile($this->getNode('attributes'))
                ->raw(';' . PHP_EOL)
                ->write('if (!is_array($attributes)) {' . PHP_EOL)
                ->indent()
                ->write("throw new UnexpectedValueException('{% {$this->tagName} with x %}: x is not an array');" . PHP_EOL)
                ->outdent()
                ->write('}' . PHP_EOL);
        }

        if ($this->hasNode('group')) {
            $compiler
                ->write('$group = ')
                ->subcompile($this->getNode('group'))
                ->raw(';' . PHP_EOL)
                ->write('if (!is_string($group)) {' . PHP_EOL)
                ->indent()
                ->write("throw new UnexpectedValueException('{% {$this->tagName} in x %}: x is not a string');" . PHP_EOL)
                ->outdent()
                ->write('}' . PHP_EOL);
        } else {
            $compiler->write('$group = \'head\';' . PHP_EOL);
        }

        if ($this->hasNode('priority')) {
            $compiler
                ->write('$priority = (int)(')
                ->subcompile($this->getNode('priority'))
                ->raw(');' . PHP_EOL);
        } else {
            $compiler->write('$priority = 10;' . PHP_EOL);
        }

        $compiler->write("\$assets = \\Grav\\Common\\Grav::instance()['assets'];" . PHP_EOL);
        $compiler->write("\$block = \$context['block'] ?? null;" . PHP_EOL);

        $compiler
            ->write('$file = (string)(')
            ->subcompile($this->getNode('file'))
            ->raw(');' . PHP_EOL);

        // Assets support.
        $compiler->write('$assets->addLink($file, [\'group\' => $group, \'priority\' => $priority] + $attributes);' . PHP_EOL);

        // HtmlBlock support.
        $compiler
            ->write('if ($block instanceof \Grav\Framework\ContentBlock\HtmlBlock) {' . PHP_EOL)
            ->indent()
            ->write('$block->addLink([\'href\'=> $file] + $attributes, $priority, $group);' . PHP_EOL)
            ->outdent()
            ->write('}' . PHP_EOL);
    }
}
