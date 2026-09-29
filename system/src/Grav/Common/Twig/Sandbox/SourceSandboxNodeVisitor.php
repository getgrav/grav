<?php

/**
 * @package    Grav\Common\Twig\Sandbox
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Twig\Sandbox;

use Grav\Common\Twig\Node\TwigNodeSandboxGuard;
use Twig\Environment;
use Twig\Extension\SandboxExtension;
use Twig\Node\CheckSecurityCallNode;
use Twig\Node\ModuleNode;
use Twig\Node\Node;
use Twig\Node\Nodes;
use Twig\Node\SandboxNode;
use Twig\NodeVisitor\NodeVisitorInterface;

/**
 * Runs Twig's own sandbox node visitor only on templates that can be sandboxed.
 *
 * Twig's SandboxNodeVisitor adds runtime checks to every template it compiles:
 * a tag/filter/function check on render and a `__toString()` check on every
 * print. Each check then asks the source policy whether that template is
 * sandboxed. With GravSourcePolicy the answer only depends on the template
 * name, and trusted theme and plugin files on disk always answer no, so those
 * checks cost time on every request and never block anything.
 *
 * This visitor makes that decision once, when the template compiles. A template
 * keeps the full instrumentation when any of these is true:
 *
 *  - it has no file path (string templates: `@Page:`, `@Var:`, `@EmailVar:`,
 *    `template_from_string()`, anything from an ArrayLoader);
 *  - the SandboxExtension says its source is sandboxed;
 *  - the sandbox is switched on for everything while it compiles
 *    (`{% sandbox %}`, `include(..., sandboxed = true)`), see
 *    TwigEnvironment::getTemplateClass() which gives that case its own class;
 *  - it contains a `{% sandbox %}` tag itself.
 *
 * Any other template is compiled without the checks, plus a guard that refuses
 * to render it while the sandbox is switched on for everything, so a trusted
 * template loaded earlier can never run unchecked inside a sandboxed render.
 *
 * Attribute and method access keeps its own sandbox check in every template,
 * because Twig compiles that from the presence of the SandboxExtension.
 */
final class SourceSandboxNodeVisitor implements NodeVisitorInterface
{
    /** @var bool Whether the module being traversed gets Twig's instrumentation. */
    private bool $instrument = true;

    public function __construct(
        private NodeVisitorInterface $inner,
        private SandboxExtension $sandbox
    ) {
    }

    public function enterNode(Node $node, Environment $env): Node
    {
        if ($node instanceof ModuleNode) {
            $this->instrument = $this->needsInstrumentation($node);
        }

        return $this->instrument ? $this->inner->enterNode($node, $env) : $node;
    }

    public function leaveNode(Node $node, Environment $env): ?Node
    {
        if ($this->instrument) {
            return $this->inner->leaveNode($node, $env);
        }

        if ($node instanceof ModuleNode) {
            $node->setNode('constructor_start', new Nodes([new CheckSecurityCallNode(), $node->getNode('constructor_start')]));
            $node->setNode('class_end', new Nodes([new TwigNodeSandboxGuard(), $node->getNode('class_end')]));
        }

        return $node;
    }

    public function getPriority(): int
    {
        return $this->inner->getPriority();
    }

    private function needsInstrumentation(ModuleNode $node): bool
    {
        $source = $node->getSourceContext();
        if (null === $source || '' === $source->getPath()) {
            return true;
        }

        // Asks the extension's own source policy, and is also true while the
        // sandbox is switched on for everything.
        if ($this->sandbox->isSandboxed($source)) {
            return true;
        }

        return self::containsSandboxTag($node);
    }

    private static function containsSandboxTag(Node $node): bool
    {
        if ($node instanceof SandboxNode) {
            return true;
        }

        foreach ($node as $child) {
            if (self::containsSandboxTag($child)) {
                return true;
            }
        }

        return false;
    }
}
