<?php

/**
 * @package    Grav\Common\Twig
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Twig\TokenParser;

use Grav\Common\Twig\Node\TwigNodeRender;

/**
 * Renders an object.
 *
 * {% render object layout: 'default' with { variable: true } %}
 */
class TwigTokenParserRender extends \Twig_TokenParser
{
    /**
     * Parses a token and returns a node.
     *
     * @param \Twig_Token $token A Twig_Token instance
     *
     * @return \Twig_Node A Twig_Node instance
     */
    public function parse(\Twig_Token $token)
    {
        $lineno = $token->getLine();

        [$object, $layout, $context] = $this->parseArguments($token);

        return new TwigNodeRender($object, $layout, $context, $lineno, $this->getTag());
    }

    /**
     * @param \Twig_Token $token
     * @return array
     */
    protected function parseArguments(\Twig_Token $token)
    {
        $stream = $this->parser->getStream();

        $object = $this->parser->getExpressionParser()->parseExpression();

        $layout = null;
        if ($stream->nextIf(\Twig_Token::NAME_TYPE, 'layout')) {
            $stream->expect(\Twig_Token::PUNCTUATION_TYPE, ':');
            $layout = $this->parser->getExpressionParser()->parseExpression();
        }

        $context = null;
        if ($stream->nextIf(\Twig_Token::NAME_TYPE, 'with')) {
            $context = $this->parser->getExpressionParser()->parseExpression();
        }

        $stream->expect(\Twig_Token::BLOCK_END_TYPE);

        return [$object, $layout, $context];
    }

    /**
     * Gets the tag name associated with this token parser.
     *
     * @return string The tag name
     */
    public function getTag()
    {
        return 'render';
    }
}
