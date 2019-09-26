<?php

/**
 * @package    Grav\Common\Twig
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Twig\Node;

use Twig\Compiler;
use Twig\Node\Expression\AbstractExpression;
use Twig\Node\Node;


class TwigNodeCache extends Node
{
    /**
     * @param AbstractExpression $key
     * @param AbstractExpression $lifetime
     * @param Node   $body
     * @param integer               $lineno
     * @param string                $tag
     */
    public function __construct(AbstractExpression $key, AbstractExpression $lifetime, Node $body, $lineno, $tag = null)
    {
        parent::__construct(array('key' => $key, 'body' => $body, 'lifetime' => $lifetime), array(), $lineno, $tag);
    }

    /**
     * {@inheritDoc}
     */
    public function compile(Compiler $compiler)
    {

        $compiler
            ->addDebugInfo($this)
            ->write("\$cache = \\Grav\\Common\\Grav::instance()['cache'];\n")
            ->write("\$key = \"twigcache-\" . ")
                ->subcompile($this->getNode('key'))
                ->write(";\n")
            ->write("\$lifetime = ")
                ->subcompile($this->getNode('lifetime'))
                ->write(";\n")
            ->write("\$cache_body = \$cache->fetch(\$key);\n")
            ->write("if (\$cache_body === false) {\n")
            ->indent()
                ->write("ob_start();\n")
                    ->indent()
                        ->subcompile($this->getNode('body'))
                    ->outdent()
                ->write("\n")
                ->write("\$cache_body = ob_get_clean();\n")
                ->write("\$cache->save(\$key, \$cache_body, \$lifetime);\n")
            ->outdent()
            ->write("}\n")
            ->write("echo \$cache_body;\n")
        ;
    }
}
