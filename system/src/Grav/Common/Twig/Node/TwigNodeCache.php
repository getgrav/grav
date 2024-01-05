<?php

/**
 * @package    Grav\Common\Twig
 *
 * @copyright  Copyright (c) 2015 - 2024 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Twig\Node;

use Twig\Compiler;
use Twig\Node\Expression\AbstractExpression;
use Twig\Node\Node;
use Twig\Node\NodeOutputInterface;

/**
 * Class TwigNodeCache
 * @package Grav\Common\Twig\Node
 */
class TwigNodeCache extends Node implements NodeOutputInterface
{
    /**
     * @param string    $key       unique name for key
     * @param int       $lifetime  in seconds
     * @param Node      $body
     * @param integer   $lineno
     * @param string|null $tag
     */
    public function __construct(Node $body, ?AbstractExpression $key, ?AbstractExpression $lifetime, array $defaults, int $lineno, string $tag)
    {
        $nodes = ['body' => $body];

        if ($key !== null) {
            $nodes['key'] = $key;
        }

        if ($lifetime !== null) {
            $nodes['lifetime'] = $lifetime;
        }

        parent::__construct($nodes, $defaults, $lineno, $tag);
    }

    public function compile(Compiler $compiler): void
    {
        $compiler->addDebugInfo($this);


        // Generate the cache key
        if ($this->hasNode('key')) {
            $compiler
                ->write('$key = "twigcache-" . ')
                ->subcompile($this->getNode('key'))
                ->raw(";\n");
        } else {
            $compiler
                ->write('$key = ')
                ->string($this->getAttribute('key'))
                ->raw(";\n");
        }

        // Set the cache timeout
        if ($this->hasNode('lifetime')) {
            $compiler
                ->write('$lifetime = ')
                ->subcompile($this->getNode('lifetime'))
                ->raw(";\n");
        } else {
            $compiler
                ->write('$lifetime = ')
                ->write($this->getAttribute('lifetime'))
                ->raw(";\n");
        }

        $compiler
            ->write("\$cache = \\Grav\\Common\\Grav::instance()['cache'];\n")
            ->write("\$cache_body = \$cache->fetch(\$key);\n")
            ->write("if (\$cache_body === false) {\n")
            ->indent()
                ->write("\\Grav\\Common\\Grav::instance()['debugger']->addMessage(\"Cache Key: \$key, Lifetime: \$lifetime\");\n")
                ->write("ob_start();\n")
                    ->indent()
                        ->subcompile($this->getNode('body'))
                    ->outdent()
                ->write("\n")
                ->write("\$cache_body = ob_get_clean();\n")
                ->write("\$cache->save(\$key, \$cache_body, \$lifetime);\n")
            ->outdent()
            ->write("}\n")
            ->write("echo '' === \$cache_body ? '' : new Markup(\$cache_body, \$this->env->getCharset());\n");
    }
}