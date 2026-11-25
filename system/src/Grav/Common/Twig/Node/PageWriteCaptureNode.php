<?php

/**
 * @package    Grav\Common\Twig
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Twig\Node;

use Twig\Compiler;
use Twig\Node\Expression\AbstractExpression;
use Twig\Node\Node;

/**
 * Evaluates an expression into a PHP local as well as returning it, for the
 * PageWriteCheckNode of the same method call. See PageWriteGuard.
 */
final class PageWriteCaptureNode extends AbstractExpression
{
    public function __construct(Node $expr, string $var)
    {
        parent::__construct(['expr' => $expr], ['var' => $var], $expr->getTemplateLine());
    }

    public function compile(Compiler $compiler): void
    {
        $compiler
            ->raw('($' . $this->getAttribute('var') . ' = ')
            ->subcompile($this->getNode('expr'))
            ->raw(')')
        ;
    }
}
