<?php

/**
 * @package    Grav\Common\Markdown
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Markdown;

use Grav\Common\Page\Markdown\Excerpts;
use Grav\Common\Page\Interfaces\PageInterface;

trait ParsedownGravTrait
{
    /** @var Excerpts */
    protected $excerpts;

    protected $special_chars;
    protected $twig_link_regex = '/\!*\[(?:.*)\]\((\{([\{%#])\s*(.*?)\s*(?:\2|\})\})\)/';

    public $completable_blocks = [];
    public $continuable_blocks = [];

    /**
     * Initialization function to setup key variables needed by the MarkdownGravLinkTrait
     *
     * @param PageInterface|Excerpts|null $excerpts
     * @param array|null $defaults
     */
    protected function init($excerpts = null, $defaults = null)
    {
        if (!$excerpts || $excerpts instanceof PageInterface) {
            // Deprecated in Grav 1.6.10
            if ($defaults) {
                $defaults = ['markdown' => $defaults];
            }
            $this->excerpts = new Excerpts($excerpts, $defaults);
            user_error(__CLASS__ . '::' . __FUNCTION__ . '($page, $defaults) is deprecated since Grav 1.6.10, use ->init(new ' . Excerpts::class . '($page, [\'markdown\' => $defaults])) instead.', E_USER_DEPRECATED);
        } else {
            $this->excerpts = $excerpts;
        }

        $this->BlockTypes['{'][] = 'TwigTag';
        $this->special_chars = ['>' => 'gt', '<' => 'lt', '"' => 'quot'];

        $defaults = $this->excerpts->getConfig();

        if (isset($defaults['markdown']['auto_line_breaks'])) {
            $this->setBreaksEnabled($defaults['markdown']['auto_line_breaks']);
        }
        if (isset($defaults['markdown']['auto_url_links'])) {
            $this->setUrlsLinked($defaults['markdown']['auto_url_links']);
        }
        if (isset($defaults['markdown']['escape_markup'])) {
                $this->setMarkupEscaped($defaults['markdown']['escape_markup']);
        }
        if (isset($defaults['markdown']['special_chars'])) {
            $this->setSpecialChars($defaults['markdown']['special_chars']);
        }

        $this->excerpts->fireInitializedEvent($this);
    }

    /**
     * @return Excerpts
     */
    public function getExcerpts()
    {
        return $this->excerpts;
    }

    /**
     * Be able to define a new Block type or override an existing one
     *
     * @param string $type
     * @param string $tag
     * @param bool $continuable
     * @param bool $completable
     * @param int|null $index
     */
    public function addBlockType($type, $tag, $continuable = false, $completable = false, $index = null)
    {
        $block = &$this->unmarkedBlockTypes;
        if ($type) {
            if (!isset($this->BlockTypes[$type])) {
                $this->BlockTypes[$type] = [];
            }
            $block = &$this->BlockTypes[$type];
        }

        if (null === $index) {
            $block[] = $tag;
        } else {
            array_splice($block, $index, 0, [$tag]);
        }

        if ($continuable) {
            $this->continuable_blocks[] = $tag;
        }
        if ($completable) {
            $this->completable_blocks[] = $tag;
        }
    }

    /**
     * Be able to define a new Inline type or override an existing one
     *
     * @param string $type
     * @param string $tag
     * @param int|null $index
     */
    public function addInlineType($type, $tag, $index = null)
    {
        if (null === $index || !isset($this->InlineTypes[$type])) {
            $this->InlineTypes[$type] [] = $tag;
        } else {
            array_splice($this->InlineTypes[$type], $index, 0, [$tag]);
        }

        if (strpos($this->inlineMarkerList, $type) === false) {
            $this->inlineMarkerList .= $type;
        }
    }

    /**
     * Overrides the default behavior to allow for plugin-provided blocks to be continuable
     *
     * @param string $Type
     *
     * @return bool
     */
    protected function isBlockContinuable($Type)
    {
        $continuable = \in_array($Type, $this->continuable_blocks, true)
            || method_exists($this, 'block' . $Type . 'Continue');

        return $continuable;
    }

    /**
     *  Overrides the default behavior to allow for plugin-provided blocks to be completable
     *
     * @param string $Type
     *
     * @return bool
     */
    protected function isBlockCompletable($Type)
    {
        $completable = \in_array($Type, $this->completable_blocks, true)
            || method_exists($this, 'block' . $Type . 'Complete');

        return $completable;
    }


    /**
     * Make the element function publicly accessible, Medium uses this to render from Twig
     *
     * @param  array $Element
     *
     * @return string markup
     */
    public function elementToHtml(array $Element)
    {
        return $this->element($Element);
    }

    /**
     * Setter for special chars
     *
     * @param array $special_chars
     *
     * @return $this
     */
    public function setSpecialChars($special_chars)
    {
        $this->special_chars = $special_chars;

        return $this;
    }

    /**
     * Ensure Twig tags are treated as block level items with no <p></p> tags
     *
     * @param array $line
     * @return array|null
     */
    protected function blockTwigTag($line)
    {
        if (preg_match('/(?:{{|{%|{#)(.*)(?:}}|%}|#})/', $line['body'], $matches)) {
            return ['markup' => $line['body']];
        }

        return null;
    }

    protected function inlineSpecialCharacter($excerpt)
    {
        if ($excerpt['text'][0] === '&' && !preg_match('/^&#?\w+;/', $excerpt['text'])) {
            return [
                'markup' => '&amp;',
                'extent' => 1,
            ];
        }

        if (isset($this->special_chars[$excerpt['text'][0]])) {
            return [
                'markup' => '&' . $this->special_chars[$excerpt['text'][0]] . ';',
                'extent' => 1,
            ];
        }

        return null;
    }

    protected function inlineImage($excerpt)
    {
        if (preg_match($this->twig_link_regex, $excerpt['text'], $matches)) {
            $excerpt['text'] = str_replace($matches[1], '/', $excerpt['text']);
            $excerpt = parent::inlineImage($excerpt);
            $excerpt['element']['attributes']['src'] = $matches[1];
            $excerpt['extent'] = $excerpt['extent'] + strlen($matches[1]) - 1;

            return $excerpt;
        }

        $excerpt['type'] = 'image';
        $excerpt = parent::inlineImage($excerpt);

        // if this is an image process it
        if (isset($excerpt['element']['attributes']['src'])) {
            $excerpt = $this->excerpts->processImageExcerpt($excerpt);
        }

        return $excerpt;
    }

    protected function inlineLink($excerpt)
    {
        $type = $excerpt['type'] ?? 'link';

        // do some trickery to get around Parsedown requirement for valid URL if its Twig in there
        if (preg_match($this->twig_link_regex, $excerpt['text'], $matches)) {
            $excerpt['text'] = str_replace($matches[1], '/', $excerpt['text']);
            $excerpt = parent::inlineLink($excerpt);
            $excerpt['element']['attributes']['href'] = $matches[1];
            $excerpt['extent'] = $excerpt['extent'] + strlen($matches[1]) - 1;

            return $excerpt;
        }

        $excerpt = parent::inlineLink($excerpt);

        // if this is a link
        if (isset($excerpt['element']['attributes']['href'])) {
            $excerpt = $this->excerpts->processLinkExcerpt($excerpt, $type);
        }

        return $excerpt;
    }

    /**
     * For extending this class via plugins
     */
    public function __call($method, $args)
    {
        if (isset($this->{$method}) === true) {
            $func = $this->{$method};

            return  \call_user_func_array($func, $args);
        }

        return null;
    }
}
