<?php
/**
 * @package    Grav.Common.Twig
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Twig\TokenParser;

use Grav\Common\Twig\Node\TwigNodeMarkdown;

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
class TwigTokenParserMarkdown extends \Twig_TokenParser
{
    /**
     * {@inheritdoc}
     */
    public function parse(\Twig_Token $token)
    {
        $lineno = $token->getLine();
        $this->parser->getStream()->expect(\Twig_Token::BLOCK_END_TYPE);
        $body = $this->parser->subparse(array($this, 'decideMarkdownEnd'), true);
        $this->parser->getStream()->expect(\Twig_Token::BLOCK_END_TYPE);
        return new TwigNodeMarkdown($body, $lineno, $this->getTag());
    }
    /**
     * Decide if current token marks end of Markdown block.
     *
     * @param \Twig_Token $token
     * @return bool
     */
    public function decideMarkdownEnd(\Twig_Token $token)
    {
        return $token->test('endmarkdown');
    }
    /**
     * {@inheritdoc}
     */
    public function getTag()
    {
        return 'markdown';
    }
}
