<?php
/**
 * @package    Grav.Common.Twig
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 * @origin     https://gist.github.com/maxgalbu/9409182
 */

namespace Grav\Common\Twig\TokenParser;

use Grav\Common\Twig\Node\TwigNodeSwitch;

/**
 * Adds ability use elegant switch instead of ungainly if statements
 *
 * {% switch type %}
 *   {% case 'foo' %}
 *      {{ my_data.foo }}
 *   {% case 'bar' %}
 *      {{ my_data.bar }}
 *   {% default %}
 *      {{ my_data.default }}
 * {% endswitch %}
 */
class TwigTokenParserSwitch extends \Twig_TokenParser
{
    /**
     * {@inheritdoc}
     */
    public function parse(\Twig_Token $token)
    {
        $lineno = $token->getLine();
        $stream = $this->parser->getStream();

        $name = $this->parser->getExpressionParser()->parseExpression();
        $stream->expect(\Twig_Token::BLOCK_END_TYPE);

        // There can be some whitespace between the {% switch %} and first {% case %} tag.
        while ($stream->getCurrent()->getType() === \Twig_Token::TEXT_TYPE && trim($stream->getCurrent()->getValue()) === '') {
            $stream->next();
        }

        $stream->expect(\Twig_Token::BLOCK_START_TYPE);

        $expressionParser = $this->parser->getExpressionParser();

        $default = null;
        $cases = [];
        $end = false;

        while (!$end) {
            $next = $stream->next();

            switch ($next->getValue()) {
                case 'case':
                    $values = [];

                    while (true) {
                        $values[] = $expressionParser->parsePrimaryExpression();
                        // Multiple allowed values?
                        if ($stream->test(\Twig_Token::OPERATOR_TYPE, 'or')) {
                            $stream->next();
                        } else {
                            break;
                        }
                    }

                    $stream->expect(\Twig_Token::BLOCK_END_TYPE);
                    $body = $this->parser->subparse(array($this, 'decideIfFork'));
                    $cases[] = new \Twig_Node([
                        'values' => new \Twig_Node($values),
                        'body' => $body
                    ]);
                    break;

                case 'default':
                    $stream->expect(\Twig_Token::BLOCK_END_TYPE);
                    $default = $this->parser->subparse(array($this, 'decideIfEnd'));
                    break;

                case 'endswitch':
                    $end = true;
                    break;

                default:
                    throw new \Twig_Error_Syntax(sprintf('Unexpected end of template. Twig was looking for the following tags "case", "default", or "endswitch" to close the "switch" block started at line %d)', $lineno), -1);
            }
        }

        $stream->expect(\Twig_Token::BLOCK_END_TYPE);

        return new TwigNodeSwitch($name, new \Twig_Node($cases), $default, $lineno, $this->getTag());
    }

    /**
     * Decide if current token marks switch logic.
     *
     * @param \Twig_Token $token
     * @return bool
     */
    public function decideIfFork(\Twig_Token $token)
    {
        return $token->test(array('case', 'default', 'endswitch'));
    }

    /**
     * Decide if current token marks end of swtich block.
     *
     * @param \Twig_Token $token
     * @return bool
     */
    public function decideIfEnd(\Twig_Token $token)
    {
        return $token->test(array('endswitch'));
    }

    /**
     * {@inheritdoc}
     */
    public function getTag()
    {
        return 'switch';
    }
}
