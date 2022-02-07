<?php

/**
 * @package    Grav\Common\Twig
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Twig\TokenParser;

use Grav\Common\Twig\Node\TwigNodeMarkdown;
use Twig\Error\SyntaxError;
use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;

/**
 * Adds ability to inline markdown between tags.
 *
 * {% markdown %}
 * This is **bold** and this _underlined_
 *
 * 1. This is a bullet list
 * 2. This is another item in that same list
 * {% endmarkdown %}
 */
class TwigTokenParserMarkdown extends AbstractTokenParser
{
    /**
     * @param Token $token
     * @return TwigNodeMarkdown
     * @throws SyntaxError
     */
    public function parse(Token $token)
    {
        $lineno = $token->getLine();
        $this->parser->getStream()->expect(Token::BLOCK_END_TYPE);
        $body = $this->parser->subparse([$this, 'decideMarkdownEnd'], true);
        $this->parser->getStream()->expect(Token::BLOCK_END_TYPE);
        return new TwigNodeMarkdown($body, $lineno, $this->getTag());
    }
    /**
     * Decide if current token marks end of Markdown block.
     *
     * @param Token $token
     * @return bool
     */
    public function decideMarkdownEnd(Token $token): bool
    {
        return $token->test('endmarkdown');
    }
    /**
     * {@inheritdoc}
     */
    public function getTag(): string
    {
        return 'markdown';
    }
}
