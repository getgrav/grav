<?php

/**
 * @package    Grav\Common\Twig
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Twig\TokenParser;

use Grav\Common\Twig\Node\TwigNodeTryCatch;
use Twig\Error\SyntaxError;
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
     * @param Token $token
     * @return TwigNodeTryCatch
     * @throws SyntaxError
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

    /**
     * @param Token $token
     * @return bool
     */
    public function decideCatch(Token $token): bool
    {
        return $token->test(['catch']);
    }

    /**
     * @param Token $token
     * @return bool
     */
    public function decideEnd(Token $token): bool
    {
        return $token->test(['endtry']) || $token->test(['endcatch']);
    }

    /**
     * Gets the tag name associated with this token parser.
     *
     * @return string The tag name
     */
    public function getTag(): string
    {
        return 'try';
    }
}
