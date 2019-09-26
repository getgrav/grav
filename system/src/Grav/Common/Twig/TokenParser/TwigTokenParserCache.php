<?php

/**
 * @package    Grav\Common\Twig
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Twig\TokenParser;

use Grav\Common\Twig\Node\TwigNodeCache;
use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;

/**
 * Adds ability to cache Twig between tags.
 *
 * {% cache 'unique-key' 600 %}
 * {{ some_complex_work() }}
 * {% endcache %}
 */
class TwigTokenParserCache extends AbstractTokenParser
{
    /**
     * {@inheritDoc}
     */
    public function parse(Token $token)
    {
        $lineno = $token->getLine();
        $stream = $this->parser->getStream();
        $key =  $this->parser->getExpressionParser()->parseExpression();
        $lifetime =$this->parser->getExpressionParser()->parseExpression();

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
    public function decideCacheEnd(Token $token)
    {
        return $token->test('endcache');
    }
    /**
     * {@inheritDoc}
     */
    public function getTag()
    {
        return 'cache';
    }


}
