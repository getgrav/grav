<?php

/**
 * @package    Grav\Common\Twig
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Twig\Node;

use Grav\Common\Twig\Sandbox\PageWriteGuard;
use Twig\Compiler;
use Twig\Node\Expression\AbstractExpression;
use Twig\Node\Node;

/**
 * The arguments of a method call, passed through PageWriteGuard::checkArguments()
 * with the object and method name the call was given.
 */
final class PageWriteCheckNode extends AbstractExpression
{
    /**
     * @param string      $object    The local holding the object (PageWriteCaptureNode).
     * @param string|null $methodVar The local holding a dynamic method name, or null.
     * @param mixed       $method    The method name when it is a constant.
     */
    public function __construct(Node $arguments, string $object, ?string $methodVar, mixed $method, int $lineno)
    {
        parent::__construct(['arguments' => $arguments], ['object' => $object, 'method_var' => $methodVar, 'method' => $method], $lineno);
    }

    public function compile(Compiler $compiler): void
    {
        $compiler->raw('\\' . PageWriteGuard::class . '::checkArguments($this->env, $this->source, $' . $this->getAttribute('object') . ', ');

        if (null !== $var = $this->getAttribute('method_var')) {
            $compiler->raw('$' . $var);
        } else {
            $compiler->repr($this->getAttribute('method'));
        }

        $compiler
            ->raw(', ')
            ->subcompile($this->getNode('arguments'))
            ->raw(', ')
            ->repr($this->getTemplateLine())
            ->raw(')')
        ;
    }
}
