<?php

/**
 * @package    Grav\Common\Twig\Sandbox
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Twig\Sandbox;

use Grav\Common\Twig\Node\ContentIncludeNode;
use Grav\Common\Twig\TwigEnvironment;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\Node\IncludeNode;
use Twig\Node\ModuleNode;
use Twig\Node\Node;
use Twig\NodeVisitor\NodeVisitorInterface;

/**
 * Compiles `{% include %}` in editor-authored content (the sources
 * GravSourcePolicy sandboxes) to ContentIncludeNode, which gives the partials
 * listed under `security.twig_sandbox.trusted_partials` a trusted render on
 * Grav's own variables. See TwigEnvironment::includeFromContent().
 *
 * Registered next to the SandboxExtension. Being an extension of its own also
 * changes the compiled class names, so content compiled before it existed is
 * compiled again rather than reused from the cache.
 */
final class ContentIncludeExtension extends AbstractExtension implements NodeVisitorInterface
{
    /** @var bool */
    private $inContent = false;

    public function getNodeVisitors(): array
    {
        return [$this];
    }

    public function enterNode(Node $node, Environment $env): Node
    {
        if ($node instanceof ModuleNode) {
            $this->inContent = $env instanceof TwigEnvironment
                && GravSourcePolicy::isSandboxedName($node->getSourceContext()?->getName() ?? '');
        }

        return $node;
    }

    public function leaveNode(Node $node, Environment $env): ?Node
    {
        // `embed` extends IncludeNode and is left alone: it runs sandboxed.
        if (!$this->inContent || IncludeNode::class !== \get_class($node)) {
            return $node;
        }

        $include = new ContentIncludeNode(
            $node->getNode('expr'),
            $node->hasNode('variables') ? $node->getNode('variables') : null,
            $node->getAttribute('only'),
            $node->getAttribute('ignore_missing'),
            $node->getTemplateLine()
        );
        if (null !== $tag = $node->getNodeTag()) {
            $include->setNodeTag($tag);
        }
        if (null !== $source = $node->getSourceContext()) {
            $include->setSourceContext($source);
        }

        return $include;
    }

    public function getPriority(): int
    {
        // Before the sandbox's visitor (0), which then checks the tag and wraps
        // the template name of the new node as it would Twig's.
        return -10;
    }
}
