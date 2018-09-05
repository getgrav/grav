<?php
/**
 * @package    Grav.Common.Twig
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Twig\TokenParser;

use Grav\Common\Twig\Node\TwigNodeThrow;

/**
 * Handles try/catch in template file.
 *
 * <pre>
 * {% throw 404 'Not Found' %}
 * </pre>
 */
class TwigTokenParserThrow extends \Twig_TokenParser
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
        $stream = $this->parser->getStream();

        $code = $stream->expect(\Twig_Token::NUMBER_TYPE)->getValue();
        $message = $this->parser->getExpressionParser()->parseExpression();
        $stream->expect(\Twig_Token::BLOCK_END_TYPE);

        return new TwigNodeThrow($code, $message, $lineno, $this->getTag());
    }

    /**
     * Gets the tag name associated with this token parser.
     *
     * @return string The tag name
     */
    public function getTag()
    {
        return 'throw';
    }
}
