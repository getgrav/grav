<?php

/**
 * This file is part of the rybakit/twig-deferred-extension package.
 *
 * (c) Eugene Leonovich <gen.work@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Twig\DeferredExtension;

use Twig\Environment;
use Twig\Node\ModuleNode;
use Twig\Node\Node;
use Twig\NodeVisitor\NodeVisitorInterface;

final class DeferredNodeVisitor implements NodeVisitorInterface
{
    private $hasDeferred = false;

    public function enterNode(Node $node, Environment $env) : Node
    {
        if (!$this->hasDeferred && $node instanceof DeferredBlockNode) {
            $this->hasDeferred = true;
        }

        return $node;
    }

    public function leaveNode(Node $node, Environment $env) : ?Node
    {
        if ($this->hasDeferred && $node instanceof ModuleNode) {
            $node->setNode('constructor_end', new Node([new DeferredExtensionNode(), $node->getNode('constructor_end')]));
            $node->setNode('display_end', new Node([new DeferredNode(), $node->getNode('display_end')]));
            $this->hasDeferred = false;
        }

        return $node;
    }

    public function getPriority() : int
    {
        return 0;
    }
}
