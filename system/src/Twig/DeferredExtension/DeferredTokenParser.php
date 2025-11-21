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

use Twig\Error\SyntaxError;
use Twig\Node\BlockNode;
use Twig\Node\BlockReferenceNode;
use Twig\Node\EmptyNode;
use Twig\Node\Node;
use Twig\Node\Nodes;
use Twig\Node\PrintNode;
use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;

final class DeferredTokenParser extends AbstractTokenParser
{
    public function parse(Token $token) : Node
    {
        $lineno = $token->getLine();
        $stream = $this->parser->getStream();
        $name = $stream->expect(Token::NAME_TYPE)->getValue();
        
        $deferred = $stream->nextIf(Token::NAME_TYPE, 'deferred');

        if ($this->parser->hasBlock($name)) {
            throw new SyntaxError(\sprintf("The block '%s' has already been defined line %d.", $name, $this->parser->getBlock($name)->getTemplateLine()), $stream->getCurrent()->getLine(), $stream->getSourceContext());
        }

        if ($deferred) {
            $block = new DeferredBlockNode($name, new EmptyNode(), $lineno);
        } else {
            $block = new BlockNode($name, new EmptyNode(), $lineno);
        }

        $this->parser->setBlock($name, $block);
        $this->parser->pushLocalScope();
        $this->parser->pushBlockStack($name);

        if ($stream->nextIf(Token::BLOCK_END_TYPE)) {
            $body = $this->parser->subparse([$this, 'decideBlockEnd'], true);
            if ($token = $stream->nextIf(Token::NAME_TYPE)) {
                $value = $token->getValue();

                if ($value != $name) {
                    throw new SyntaxError(\sprintf('Expected endblock for block "%s" (but "%s" given).', $name, $value), $stream->getCurrent()->getLine(), $stream->getSourceContext());
                }
            }
        } else {
            $body = new Nodes([
                new PrintNode($this->parser->parseExpression(), $lineno),
            ]);
        }
        $stream->expect(Token::BLOCK_END_TYPE);

        $block->setNode('body', $body);
        $this->parser->popBlockStack();
        $this->parser->popLocalScope();

        return new BlockReferenceNode($name, $lineno, $this->getTag());
    }

    public function decideBlockEnd(Token $token): bool
    {
        return $token->test('endblock');
    }

    public function getTag() : string
    {
        return 'block';
    }
}
