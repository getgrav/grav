<?php

/**
 * @package    Grav\Common\Twig
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 * @origin     https://gist.github.com/maxgalbu/9409182
 */

namespace Grav\Common\Twig\TokenParser;

use Grav\Common\Twig\Node\TwigNodeSwitch;
use Twig\Error\SyntaxError;
use Twig\Node\Node;
use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;

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
class TwigTokenParserSwitch extends AbstractTokenParser
{
    /**
     * {@inheritdoc}
     */
    public function parse(Token $token)
    {
        $lineno = $token->getLine();
        $stream = $this->parser->getStream();

        $name = $this->parser->getExpressionParser()->parseExpression();
        $stream->expect(Token::BLOCK_END_TYPE);

        // There can be some whitespace between the {% switch %} and first {% case %} tag.
        while ($stream->getCurrent()->getType() === Token::TEXT_TYPE && trim($stream->getCurrent()->getValue()) === '') {
            $stream->next();
        }

        $stream->expect(Token::BLOCK_START_TYPE);

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
                        if ($stream->test(Token::OPERATOR_TYPE, 'or')) {
                            $stream->next();
                        } else {
                            break;
                        }
                    }

                    $stream->expect(Token::BLOCK_END_TYPE);
                    $body = $this->parser->subparse(array($this, 'decideIfFork'));
                    $cases[] = new Node([
                        'values' => new Node($values),
                        'body' => $body
                    ]);
                    break;

                case 'default':
                    $stream->expect(Token::BLOCK_END_TYPE);
                    $default = $this->parser->subparse(array($this, 'decideIfEnd'));
                    break;

                case 'endswitch':
                    $end = true;
                    break;

                default:
                    throw new SyntaxError(sprintf('Unexpected end of template. Twig was looking for the following tags "case", "default", or "endswitch" to close the "switch" block started at line %d)', $lineno), -1);
            }
        }

        $stream->expect(Token::BLOCK_END_TYPE);

        return new TwigNodeSwitch($name, new Node($cases), $default, $lineno, $this->getTag());
    }

    /**
     * Decide if current token marks switch logic.
     *
     * @param Token $token
     * @return bool
     */
    public function decideIfFork(Token $token)
    {
        return $token->test(array('case', 'default', 'endswitch'));
    }

    /**
     * Decide if current token marks end of swtich block.
     *
     * @param Token $token
     * @return bool
     */
    public function decideIfEnd(Token $token)
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
