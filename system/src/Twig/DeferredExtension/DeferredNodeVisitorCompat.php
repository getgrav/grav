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

final class DeferredNodeVisitorCompat implements NodeVisitorInterface
{
    private $hasDeferred = false;

    /**
     * @param \Twig_NodeInterface $node
     * @param Environment $env
     * @return Node
     */
    public function enterNode(\Twig_NodeInterface $node, Environment $env): Node
    {
        if (!$this->hasDeferred && $node instanceof DeferredBlockNode) {
            $this->hasDeferred = true;
        }

        \assert($node instanceof Node);

        return $node;
    }

    /**
     * @param \Twig_NodeInterface $node
     * @param Environment $env
     * @return Node|null
     */
    public function leaveNode(\Twig_NodeInterface $node, Environment $env): ?Node
    {
        if ($this->hasDeferred && $node instanceof ModuleNode) {
            $node->setNode('constructor_end', new Node([new DeferredExtensionNode(), $node->getNode('constructor_end')]));
            $node->setNode('display_end', new Node([new DeferredNode(), $node->getNode('display_end')]));
            $this->hasDeferred = false;
        }

        \assert($node instanceof Node);

        return $node;
    }

    /**
     * @return int
     */
    public function getPriority() : int
    {
        return 0;
    }
}
