<?php

/**
 * @package    Grav\Common\Twig
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Twig\TokenParser;

use Grav\Common\Twig\Node\TwigNodeStyle;
use Twig\Error\SyntaxError;
use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;

/**
 * Adds a style to the document.
 *
 * {% style 'theme://css/foo.css' priority: 20 %}

 * {% style priority: 20 with { media: 'screen' } %}
 *     a { color: red; }
 * {% endstyle %}
 */
class TwigTokenParserStyle extends AbstractTokenParser
{
    /**
     * Parses a token and returns a node.
     *
     * @param Token $token
     * @return TwigNodeStyle
     * @throws SyntaxError
     */
    public function parse(Token $token)
    {
        $lineno = $token->getLine();
        $stream = $this->parser->getStream();

        [$file, $group, $priority, $attributes] = $this->parseArguments($token);

        $content = null;
        if (!$file) {
            $content = $this->parser->subparse([$this, 'decideBlockEnd'], true);
            $stream->expect(Token::BLOCK_END_TYPE);
        }

        return new TwigNodeStyle($content, $file, $group, $priority, $attributes, $lineno, $this->getTag());
    }

    /**
     * @param Token $token
     * @return array
     */
    protected function parseArguments(Token $token): array
    {
        $stream = $this->parser->getStream();

        // Look for deprecated {% style ... in ... %}
        if (!$stream->test(Token::BLOCK_END_TYPE) && !$stream->test(Token::OPERATOR_TYPE, 'in')) {
            $i = 0;
            do {
                $token = $stream->look(++$i);
                if ($token->test(Token::BLOCK_END_TYPE)) {
                    break;
                }
                if ($token->test(Token::OPERATOR_TYPE, 'in') && $stream->look($i+1)->test(Token::STRING_TYPE)) {
                    user_error("Twig: Using {% style ... in ... %} is deprecated, use {% style ...  at ... %} instead", E_USER_DEPRECATED);

                    break;
                }
            } while (true);
        }

        $file = null;
        if (!$stream->test(Token::NAME_TYPE) && !$stream->test(Token::OPERATOR_TYPE, 'in') && !$stream->test(Token::BLOCK_END_TYPE)) {
            $file = $this->parser->getExpressionParser()->parseExpression();
        }

        $group = null;
        if ($stream->nextIf(Token::NAME_TYPE, 'at') || $stream->nextIf(Token::OPERATOR_TYPE, 'in')) {
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

        return [$file, $group, $priority, $attributes];
    }

    /**
     * @param Token $token
     * @return bool
     */
    public function decideBlockEnd(Token $token): bool
    {
        return $token->test('endstyle');
    }

    /**
     * Gets the tag name associated with this token parser.
     *
     * @return string The tag name
     */
    public function getTag(): string
    {
        return 'style';
    }
}
