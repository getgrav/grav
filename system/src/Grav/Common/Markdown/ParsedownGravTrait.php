<?php
/**
 * @package    Grav.Common.Markdown
 *
 * @copyright  Copyright (C) 2014 - 2016 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Markdown;

use Grav\Common\Grav;
use Grav\Common\Helpers\Excerpts;
use Grav\Common\Page\Page;
use RocketTheme\Toolbox\Event\Event;

trait ParsedownGravTrait
{
    /** @var Page $page */
    protected $page;

    protected $special_chars;
    protected $twig_link_regex = '/\!*\[(?:.*)\]\((\{([\{%#])\s*(.*?)\s*(?:\2|\})\})\)/';

    public $completable_blocks = [];
    public $continuable_blocks = [];

    /**
     * Initialization function to setup key variables needed by the MarkdownGravLinkTrait
     *
     * @param $page
     * @param $defaults
     */
    protected function init($page, $defaults)
    {
        $grav = Grav::instance();

        $this->page = $page;
        $this->BlockTypes['{'] [] = "TwigTag";
        $this->special_chars = ['>' => 'gt', '<' => 'lt', '"' => 'quot'];

        if ($defaults === null) {
            $defaults = Grav::instance()['config']->get('system.pages.markdown');
        }

        $this->setBreaksEnabled($defaults['auto_line_breaks']);
        $this->setUrlsLinked($defaults['auto_url_links']);
        $this->setMarkupEscaped($defaults['escape_markup']);
        $this->setSpecialChars($defaults['special_chars']);

        $grav->fireEvent('onMarkdownInitialized', new Event(['markdown' => $this]));

    }

    /**
     * Be able to define a new Block type or override an existing one
     *
     * @param $type
     * @param $tag
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

        if (!isset($index)) {
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
     * @param $type
     * @param $tag
     */
    public function addInlineType($type, $tag, $index = null)
    {
        if (!isset($index) || !isset($this->InlineTypes[$type])) {
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
     * @param $Type
     *
     * @return bool
     */
    protected function isBlockContinuable($Type)
    {
        $continuable = in_array($Type, $this->continuable_blocks) || method_exists($this, 'block' . $Type . 'Continue');

        return $continuable;
    }

    /**
     *  Overrides the default behavior to allow for plugin-provided blocks to be completable
     *
     * @param $Type
     *
     * @return bool
     */
    protected function isBlockCompletable($Type)
    {
        $completable = in_array($Type, $this->completable_blocks) || method_exists($this, 'block' . $Type . 'Complete');

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
     * @param $special_chars
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
     */
    protected function blockTwigTag($Line)
    {
        if (preg_match('/(?:{{|{%|{#)(.*)(?:}}|%}|#})/', $Line['body'], $matches)) {
            $Block = [
                'markup' => $Line['body'],
            ];

            return $Block;
        }

        return null;
    }

    protected function inlineSpecialCharacter($Excerpt)
    {
        if ($Excerpt['text'][0] === '&' && !preg_match('/^&#?\w+;/', $Excerpt['text'])) {
            return [
                'markup' => '&amp;',
                'extent' => 1,
            ];
        }

        if (isset($this->special_chars[$Excerpt['text'][0]])) {
            return [
                'markup' => '&' . $this->special_chars[$Excerpt['text'][0]] . ';',
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
        } else {
            $excerpt['type'] = 'image';
            $excerpt = parent::inlineImage($excerpt);
        }

        // if this is an image process it
        if (isset($excerpt['element']['attributes']['src'])) {
            $excerpt = Excerpts::processImageExcerpt($excerpt, $this->page);
        }

        return $excerpt;
    }

    protected function inlineLink($excerpt)
    {
        if (isset($excerpt['type'])) {
            $type = $excerpt['type'];
        } else {
            $type = 'link';
        }

        // do some trickery to get around Parsedown requirement for valid URL if its Twig in there
        if (preg_match($this->twig_link_regex, $excerpt['text'], $matches)) {
            $excerpt['text'] = str_replace($matches[1], '/', $excerpt['text']);
            $excerpt = parent::inlineLink($excerpt);
            $excerpt['element']['attributes']['href'] = $matches[1];
            $excerpt['extent'] = $excerpt['extent'] + strlen($matches[1]) - 1;

            return $excerpt;
        } else {
            $excerpt = parent::inlineLink($excerpt);
        }

        // if this is a link
        if (isset($excerpt['element']['attributes']['href'])) {
            $excerpt = Excerpts::processLinkExcerpt($excerpt, $this->page, $type);
        }

        return $excerpt;
    }

    // For extending this class via plugins
    public function __call($method, $args)
    {
        if (isset($this->$method) === true) {
            $func = $this->$method;

            return call_user_func_array($func, $args);
        }

        return null;
    }
}
