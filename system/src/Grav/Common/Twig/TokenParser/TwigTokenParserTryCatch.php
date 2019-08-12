<?php

/**
 * @package    Grav\Common\Twig
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Twig\TokenParser;

use Grav\Common\Twig\Node\TwigNodeTryCatch;
use Twig\Node\Node;
use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;

/**
 * Handles try/catch in template file.
 *
 * <pre>
 * {% try %}
 *    <li>{{ user.get('name') }}</li>
 * {% catch %}
 *    {{ e.message }}
 * {% endcatch %}
 * </pre>
 */
class TwigTokenParserTryCatch extends AbstractTokenParser
{
    /**
     * Parses a token and returns a node.
     *
     * @param Token $token A Twig_Token instance
     *
     * @return Node A Twig_Node instance
     */
    public function parse(Token $token)
    {
        $lineno = $token->getLine();
        $stream = $this->parser->getStream();

        $stream->expect(Token::BLOCK_END_TYPE);
        $try = $this->parser->subparse([$this, 'decideCatch']);
        $stream->next();
        $stream->expect(Token::BLOCK_END_TYPE);
        $catch = $this->parser->subparse([$this, 'decideEnd']);
        $stream->next();
        $stream->expect(Token::BLOCK_END_TYPE);

        return new TwigNodeTryCatch($try, $catch, $lineno, $this->getTag());
    }

    public function decideCatch(Token $token)
    {
        return $token->test(array('catch'));
    }

    public function decideEnd(Token $token)
    {
        return $token->test(array('endtry')) || $token->test(array('endcatch'));
    }

    /**
     * Gets the tag name associated with this token parser.
     *
     * @return string The tag name
     */
    public function getTag()
    {
        return 'try';
    }
}
