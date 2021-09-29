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

use Twig\Node\BlockNode;
use Twig\Node\Node;
use Twig\Parser;
use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;
use Twig\TokenParser\BlockTokenParser;

final class DeferredTokenParser extends AbstractTokenParser
{
    private $blockTokenParser;

    public function setParser(Parser $parser) : void
    {
        parent::setParser($parser);

        $this->blockTokenParser = new BlockTokenParser();
        $this->blockTokenParser->setParser($parser);
    }

    public function parse(Token $token) : Node
    {
        $stream = $this->parser->getStream();
        $nameToken = $stream->next();
        $deferredToken = $stream->nextIf(Token::NAME_TYPE, 'deferred');
        $stream->injectTokens([$nameToken]);

        $node = $this->blockTokenParser->parse($token);

        if ($deferredToken) {
            $this->replaceBlockNode($nameToken->getValue());
        }

        return $node;
    }

    public function getTag() : string
    {
        return 'block';
    }

    private function replaceBlockNode(string $name) : void
    {
        $block = $this->parser->getBlock($name)->getNode('0');
        $this->parser->setBlock($name, $this->createDeferredBlockNode($block));
    }

    private function createDeferredBlockNode(BlockNode $block) : DeferredBlockNode
    {
        $name = $block->getAttribute('name');
        $deferredBlock = new DeferredBlockNode($name, new Node([]), $block->getTemplateLine());

        foreach ($block as $nodeName => $node) {
            $deferredBlock->setNode($nodeName, $node);
        }

        if ($sourceContext = $block->getSourceContext()) {
            $deferredBlock->setSourceContext($sourceContext);
        }

        return $deferredBlock;
    }
}
