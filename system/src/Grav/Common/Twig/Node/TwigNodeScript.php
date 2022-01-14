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
 * Class TwigNodeScript
 * @package Grav\Common\Twig\Node
 */
class TwigNodeScript extends Node implements NodeCaptureInterface
{
    /** @var string */
    protected $tagName = 'script';

    /**
     * TwigNodeScript constructor.
     * @param Node|null $body
     * @param string|null $type
     * @param AbstractExpression|null $file
     * @param AbstractExpression|null $group
     * @param AbstractExpression|null $priority
     * @param AbstractExpression|null $attributes
     * @param int $lineno
     * @param string|null $tag
     */
    public function __construct(?Node $body, ?string $type, ?AbstractExpression $file, ?AbstractExpression $group, ?AbstractExpression $priority, ?AbstractExpression $attributes, $lineno = 0, $tag = null)
    {
        $nodes = ['body' => $body, 'file' => $file, 'group' => $group, 'priority' => $priority, 'attributes' => $attributes];
        $nodes = array_filter($nodes);

        parent::__construct($nodes, ['type' => $type], $lineno, $tag);
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

        if ($this->hasNode('attributes')) {
            $compiler
                ->write('$attributes = ')
                ->subcompile($this->getNode('attributes'))
                ->raw(';' . PHP_EOL)
                ->write('if (!is_array($attributes)) {' . PHP_EOL)
                ->indent()
                ->write("throw new UnexpectedValueException('{% {$this->tagName} with x %}: x is not an array');" . PHP_EOL)
                ->outdent()
                ->write('}' . PHP_EOL);
        } else {
            $compiler->write('$attributes = [];' . PHP_EOL);
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

        if ($this->hasNode('file')) {
            // JS file.
            $compiler
                ->write('$file = (string)(')
                ->subcompile($this->getNode('file'))
                ->raw(');' . PHP_EOL);

            $method = $this->getAttribute('type') === 'module' ? 'addJsModule' : 'addJs';

            // Assets support.
            $compiler->write('$assets->' . $method . '($file, [\'group\' => $group, \'priority\' => $priority] + $attributes);' . PHP_EOL);

            $method = $this->getAttribute('type') === 'module' ? 'addModule' : 'addScript';

            // HtmlBlock support.
            $compiler
                ->write('if ($block instanceof \Grav\Framework\ContentBlock\HtmlBlock) {' . PHP_EOL)
                ->indent()
                ->write('$block->' . $method . '([\'src\'=> $file] + $attributes, $priority, $group);' . PHP_EOL)
                ->outdent()
                ->write('}' . PHP_EOL);

        } else {
            // Inline script.
            $compiler
                ->write('ob_start();' . PHP_EOL)
                ->subcompile($this->getNode('body'))
                ->write('$content = ob_get_clean();' . PHP_EOL);

            $method = $this->getAttribute('type') === 'module' ? 'addInlineJsModule' : 'addInlineJs';

            // Assets support.
            $compiler->write('$assets->' . $method . '($content, [\'group\' => $group, \'priority\' => $priority] + $attributes);' . PHP_EOL);

            $method = $this->getAttribute('type') === 'module' ? 'addInlineModule' : 'addInlineScript';

            // HtmlBlock support.
            $compiler
                ->write('if ($block instanceof \Grav\Framework\ContentBlock\HtmlBlock) {' . PHP_EOL)
                ->indent()
                ->write('$block->' . $method . '([\'content\'=> $content] + $attributes, $priority, $group);' . PHP_EOL)
                ->outdent()
                ->write('}' . PHP_EOL);
        }
    }
}
