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
use Twig\Node\Node;

/**
 * Class TwigNodeSandboxGuard
 *
 * Added to trusted templates that were compiled without the sandbox checks
 * (see SourceSandboxNodeVisitor). Twig calls ensureSecurityChecked() before it
 * renders a template or one of its blocks, so this refuses to run such a
 * template while the sandbox is switched on for everything. Templates loaded
 * while the sandbox is on get a fully checked class instead, so this only
 * fires for an instance that was loaded before, which fails closed.
 *
 * @package Grav\Common\Twig\Node
 */
#[YieldReady]
class TwigNodeSandboxGuard extends Node
{
    /**
     * @param Compiler $compiler
     * @return void
     */
    public function compile(Compiler $compiler): void
    {
        $compiler
            ->raw("\n")
            ->write("public function ensureSecurityChecked(): void\n")
            ->write("{\n")
            ->indent()
            ->write("if (\$this->sandbox->isSandboxed()) {\n")
            ->indent()
            ->write("throw new \\Twig\\Sandbox\\SecurityError(sprintf('Template \"%s\" was loaded as a trusted template and cannot be rendered while the sandbox is enabled.', \$this->getTemplateName()), -1, \$this->source);\n")
            ->outdent()
            ->write("}\n")
            ->outdent()
            ->write("}\n")
        ;
    }
}
