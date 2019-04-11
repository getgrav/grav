<?php

/**
 * @package    Grav\Common\Twig
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Twig\TokenParser;

use Grav\Common\Twig\Node\TwigNodeScript;
use Twig\Node\Node;
use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;

/**
 * Adds a script to head/bottom/custom location in the document.
 *
 * {% script 'theme://js/something.js' in 'bottom' priority: 20 with { defer: true, async: true } %}
 *
 * {% script in 'bottom' priority: 20 %}
 *     alert('Warning!');
 * {% endscript %}

 */
class TwigTokenParserScript extends AbstractTokenParser
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

        list($file, $group, $priority, $attributes) = $this->parseArguments($token);

        $content = null;
        if ($file === null) {
            $content = $this->parser->subparse([$this, 'decideBlockEnd'], true);
            $stream->expect(Token::BLOCK_END_TYPE);
        }

        return new TwigNodeScript($content, $file, $group, $priority, $attributes, $lineno, $this->getTag());
    }

    /**
     * @param Token $token
     * @return array
     */
    protected function parseArguments(Token $token)
    {
        $stream = $this->parser->getStream();

        $file = null;
        if (!$stream->test(Token::NAME_TYPE) && !$stream->test(Token::OPERATOR_TYPE) && !$stream->test(Token::BLOCK_END_TYPE)) {
            $file = $this->parser->getExpressionParser()->parseExpression();
        }

        $group = null;
        if ($stream->nextIf(Token::OPERATOR_TYPE, 'in')) {
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
    public function decideBlockEnd(Token $token)
    {
        return $token->test('endscript');
    }

    /**
     * Gets the tag name associated with this token parser.
     *
     * @return string The tag name
     */
    public function getTag()
    {
        return 'script';
    }
}
