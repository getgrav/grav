<?php

/**
 * @package    Grav\Common\Twig
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Twig\Node;

use Twig\Attribute\YieldReady;
use Twig\Compiler;
use Twig\Node\IncludeNode;

/**
 * An `{% include %}` written in editor-authored content.
 *
 * Compiles to TwigEnvironment::includeFromContent(), which renders a trusted
 * partial as a trusted render on Grav's own variables and anything else with
 * the sandbox on. Swapped in for Twig's IncludeNode by ContentIncludeExtension,
 * in sandboxed sources only. The template-name expression and the `with` value
 * are compiled exactly as Twig compiles them, so the sandbox's own checks on
 * them (and the `include` tag check) stay in place.
 */
#[YieldReady]
class ContentIncludeNode extends IncludeNode
{
    public function compile(Compiler $compiler): void
    {
        $compiler
            ->addDebugInfo($this)
            ->write('yield from $this->env->includeFromContent($context, ')
            ->subcompile($this->getNode('expr'))
            ->raw(', ')
        ;

        if ($this->hasNode('variables')) {
            $compiler->subcompile($this->getNode('variables'));
        } else {
            $compiler->raw('null');
        }

        $compiler
            ->raw(', ')
            ->repr($this->getAttribute('only'))
            ->raw(', ')
            ->repr($this->getAttribute('ignore_missing'))
            ->raw(', fn ($template) => $this->load($template, ')
            ->repr($this->getTemplateLine())
            ->raw("));\n")
        ;
    }
}
