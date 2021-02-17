<?php

/**
 * @package    Grav\Common\Twig
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Twig\TokenParser;

use Grav\Common\Grav;
use Grav\Common\Twig\Node\TwigNodeCache;
use Twig\Error\SyntaxError;
use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;

/**
 * Adds ability to cache Twig between tags.
 *
 * {% cache 600 %}
 * {{ some_complex_work() }}
 * {% endcache %}
 *
 * Where the `600` is an optional lifetime in seconds
 */
class TwigTokenParserCache extends AbstractTokenParser
{
    /**
     * @param Token $token
     * @return TwigNodeCache
     * @throws SyntaxError
     */
    public function parse(Token $token)
    {
        $lineno = $token->getLine();
        $stream = $this->parser->getStream();
        $key = $this->parser->getVarName() . $lineno;
        $lifetime = Grav::instance()['cache']->getLifetime();

        // Check for optional lifetime override
        if (!$stream->test(Token::BLOCK_END_TYPE)) {
            $lifetime_expr = $this->parser->getExpressionParser()->parseExpression();
            $lifetime = $lifetime_expr->getAttribute('value');
        }

        $stream->expect(Token::BLOCK_END_TYPE);
        $body = $this->parser->subparse(array($this, 'decideCacheEnd'), true);
        $stream->expect(Token::BLOCK_END_TYPE);

        return new TwigNodeCache($key, $lifetime, $body, $lineno, $this->getTag());
    }

    /**
     * Decide if current token marks end of cache block.
     *
     * @param Token $token
     * @return bool
     */
    public function decideCacheEnd(Token $token): bool
    {
        return $token->test('endcache');
    }
    /**
     * {@inheritDoc}
     */
    public function getTag(): string
    {
        return 'cache';
    }
}
