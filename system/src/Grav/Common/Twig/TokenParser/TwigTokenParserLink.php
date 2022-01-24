<?php

/**
 * @package    Grav\Common\Twig
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Twig\TokenParser;

use Grav\Common\Twig\Node\TwigNodeLink;
use Twig\Error\SyntaxError;
use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;

/**
 * Adds a link to the document. First parameter is always value of `rel` without quotes.
 *
 * {% link icon 'theme://images/favicon.png' priority: 20 with { type: 'image/png' } %}
 * {% link modulepreload 'plugin://grav-plugin/build/js/vendor.js' %}
 */
class TwigTokenParserLink extends AbstractTokenParser
{
    protected $rel = [
        'alternate',
        'author',
        'dns-prefetch',
        'help',
        'icon',
        'license',
        'next',
        'pingback',
        'preconnect',
        'prefetch',
        'preload',
        'prerender',
        'prev',
        'search',
        'stylesheet',
    ];

    /**
     * Parses a token and returns a node.
     *
     * @param Token $token
     * @return TwigNodeLink
     * @throws SyntaxError
     */
    public function parse(Token $token)
    {
        $lineno = $token->getLine();

        [$rel, $file, $group, $priority, $attributes] = $this->parseArguments($token);

        return new TwigNodeLink($rel, $file, $group, $priority, $attributes, $lineno, $this->getTag());
    }

    /**
     * @param Token $token
     * @return array
     */
    protected function parseArguments(Token $token): array
    {
        $stream = $this->parser->getStream();


        $rel = null;
        if ($stream->test(Token::NAME_TYPE, $this->rel)) {
            $rel = $stream->getCurrent()->getValue();
            $stream->next();
        }

        $file = null;
        if (!$stream->test(Token::NAME_TYPE) && !$stream->test(Token::BLOCK_END_TYPE)) {
            $file = $this->parser->getExpressionParser()->parseExpression();
        }

        $group = null;
        if ($stream->nextIf(Token::NAME_TYPE, 'at')) {
            $group = $this->parser->getExpressionParser()->parseExpression();
        }

        $priority = null;
        if ($stream->nextIf(Token::NAME_TYPE, 'priority')) {
            $stream->expect(Token::PUNCTUATION_TYPE, ':');
            $priority = $this->parser->getExpressionParser()->parseExpression();
        }

        $attributes = null;
        if ($stream->nextIf(Token::NAME_TYPE, 'with')) {
            $attributes = $this->parser->getExpressionParser()->parseExpression();
        }

        $stream->expect(Token::BLOCK_END_TYPE);

        return [$rel, $file, $group, $priority, $attributes];
    }

    /**
     * Gets the tag name associated with this token parser.
     *
     * @return string The tag name
     */
    public function getTag(): string
    {
        return 'link';
    }
}
