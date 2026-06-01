<?php

/**
 * @package    Grav\Common\Markdown
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Markdown;

use Exception;
use Grav\Common\Page\Interfaces\PageInterface;
use Grav\Common\Page\Markdown\Excerpts;

/**
 * Class ParsedownExtra
 * @package Grav\Common\Markdown
 */
class ParsedownExtra extends \ParsedownExtra
{
    use ParsedownGravTrait;

    /**
     * ParsedownExtra constructor.
     *
     * @param Excerpts|PageInterface|null $excerpts
     * @param array|null $defaults
     * @throws Exception
     */
    public function __construct($excerpts = null, $defaults = null)
    {
        if (!$excerpts || $excerpts instanceof PageInterface || null !== $defaults) {
            // Deprecated in Grav 1.6.10
            if ($defaults) {
                $defaults = ['markdown' => $defaults];
            }
            $excerpts = new Excerpts($excerpts, $defaults);
            user_error(self::class . '::' . __FUNCTION__ . '($page, $defaults) is deprecated since Grav 1.6.10, use new ' . self::class . '(new ' . Excerpts::class . '($page, [\'markdown\' => $defaults])) instead.', E_USER_DEPRECATED);
        }

        parent::__construct();

        $this->init($excerpts, $defaults);
    }

    /**
     * Apply `{#id .class}` attribute syntax to fenced code blocks.
     *
     * Vanilla Parsedown Extra never overrides fenced code, so the base parser
     * folds a trailing `{...}` straight into the language token and emits a
     * broken `class="language-{.foo"`. This separates the info string from a
     * trailing attribute block: the first whitespace-delimited token becomes the
     * `language-*` class and the `{...}` contributes id/classes on the `<code>`.
     *
     * @param array $Line
     * @return array|null
     */
    protected function blockFencedCode($Line)
    {
        $Block = parent::blockFencedCode($Line);
        if ($Block === null || !isset($Block['element']['text'])) {
            return $Block;
        }

        $char = $Line['text'][0];
        if (!preg_match('/^[' . $char . ']{3,}[ ]*([^`]+)?[ ]*$/', (string) $Line['text'], $matches) || !isset($matches[1])) {
            return $Block;
        }

        $info = trim($matches[1]);
        $attributes = [];

        // Peel a trailing {…} attribute block off the info string.
        if (preg_match('/^(.*?)[ ]*\{(' . $this->regexAttribute . '+)\}[ ]*$/', $info, $am)) {
            $info = trim($am[1]);
            $attributes = $this->parseAttributeData($am[2]);
        }

        $classes = [];
        if ($info !== '') {
            $language = substr($info, 0, strcspn($info, " \t\n\f\r"));
            if ($language !== '') {
                $classes[] = 'language-' . $language;
            }
        }
        if (isset($attributes['class'])) {
            $classes[] = $attributes['class'];
            unset($attributes['class']);
        }
        if ($classes !== []) {
            $attributes['class'] = implode(' ', $classes);
        }

        if ($attributes !== []) {
            $Block['element']['text']['attributes'] = $attributes;
        } else {
            unset($Block['element']['text']['attributes']);
        }

        return $Block;
    }
}
