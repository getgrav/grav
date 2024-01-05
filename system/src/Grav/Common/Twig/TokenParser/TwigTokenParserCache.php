<?php

/**
 * @package    Grav\Common\Twig
 *
 * @copyright  Copyright (c) 2015 - 2024 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Twig\TokenParser;

use Grav\Common\Grav;
use Grav\Common\Twig\Node\TwigNodeCache;
use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;

/**
 * Adds ability to cache Twig between tags.
 *
 * {% cache 600 %}
 * {{ some_complex_work() }}
 * {% endcache %}
 *
 * Also can provide a unique key for the cache:
 *
 * {% cache "prefix-"~lang 600 %}
 *
 * Where the "prefix-"~lang will use a unique key based on the current language. "prefix-en" for example
 */
class TwigTokenParserCache extends AbstractTokenParser
{
    public function parse(Token $token)
    {
        $stream = $this->parser->getStream();
        $lineno = $token->getLine();

        // Parse the optional key and timeout parameters
        $defaults = [
            'key' => $this->parser->getVarName() . $lineno,
            'lifetime' => Grav::instance()['cache']->getLifetime()
        ];

        $key = null;
        $lifetime = null;
        while (!$stream->test(Token::BLOCK_END_TYPE)) {
            if ($stream->test(Token::STRING_TYPE)) {
                $key = $this->parser->getExpressionParser()->parseExpression();
            } elseif ($stream->test(Token::NUMBER_TYPE)) {
                $lifetime = $this->parser->getExpressionParser()->parseExpression();
            } else {
                throw new \Twig\Error\SyntaxError("Unexpected token type in cache tag.", $token->getLine(), $stream->getSourceContext());
            }
        }

        $stream->expect(Token::BLOCK_END_TYPE);

        // Parse the content inside the cache block
        $body = $this->parser->subparse([$this, 'decideCacheEnd'], true);

        $stream->expect(Token::BLOCK_END_TYPE);

        return new TwigNodeCache($body, $key, $lifetime, $defaults, $lineno, $this->getTag());
    }

    public function decideCacheEnd(Token $token): bool
    {
        return $token->test('endcache');
    }

    public function getTag(): string
    {
        return 'cache';
    }
}