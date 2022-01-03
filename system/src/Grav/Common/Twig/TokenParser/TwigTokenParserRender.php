<?php

/**
 * @package    Grav\Common\Twig
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Twig\TokenParser;

use Grav\Common\Twig\Node\TwigNodeRender;
use Twig\Node\Node;
use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;

/**
 * Renders an object.
 *
 * {% render object layout: 'default' with { variable: true } %}
 */
class TwigTokenParserRender extends AbstractTokenParser
{
    /**
     * Parses a token and returns a node.
     *
     * @param Token $token
     * @return TwigNodeRender
     */
    public function parse(Token $token)
    {
        $lineno = $token->getLine();

        [$object, $layout, $context] = $this->parseArguments($token);

        return new TwigNodeRender($object, $layout, $context, $lineno, $this->getTag());
    }

    /**
     * @param Token $token
     * @return array
     */
    protected function parseArguments(Token $token): array
    {
        $stream = $this->parser->getStream();

        $object = $this->parser->getExpressionParser()->parseExpression();

        $layout = null;
        if ($stream->nextIf(Token::NAME_TYPE, 'layout')) {
            $stream->expect(Token::PUNCTUATION_TYPE, ':');
            $layout = $this->parser->getExpressionParser()->parseExpression();
        }

        $context = null;
        if ($stream->nextIf(Token::NAME_TYPE, 'with')) {
            $context = $this->parser->getExpressionParser()->parseExpression();
        }

        $stream->expect(Token::BLOCK_END_TYPE);

        return [$object, $layout, $context];
    }

    /**
     * Gets the tag name associated with this token parser.
     *
     * @return string The tag name
     */
    public function getTag(): string
    {
        return 'render';
    }
}
