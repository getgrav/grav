<?php

/**
 * @package    Grav\Common\Helpers
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Helpers;

use DOMDocument;
use DOMElement;
use Grav\Common\Page\Interfaces\PageInterface;
use Grav\Common\Page\Markdown\Excerpts as ExcerptsObject;
use Grav\Common\Page\Medium\Link;
use Grav\Common\Page\Medium\Medium;
use function is_array;

/**
 * Class Excerpts
 * @package Grav\Common\Helpers
 */
class Excerpts
{
    /**
     * Process Grav image media URL from HTML tag
     *
     * @param string $html              HTML tag e.g. `<img src="image.jpg" />`
     * @param PageInterface|null $page  Page, defaults to the current page object
     * @return string                   Returns final HTML string
     */
    public static function processImageHtml($html, PageInterface $page = null)
    {
        $excerpt = static::getExcerptFromHtml($html, 'img');
        if (null === $excerpt) {
            return '';
        }

        $original_src = $excerpt['element']['attributes']['src'];
        $excerpt['element']['attributes']['href'] = $original_src;

        $excerpt = static::processLinkExcerpt($excerpt, $page, 'image');

        $excerpt['element']['attributes']['src'] = $excerpt['element']['attributes']['href'];
        unset($excerpt['element']['attributes']['href']);

        $excerpt = static::processImageExcerpt($excerpt, $page);

        $excerpt['element']['attributes']['data-src'] = $original_src;

        $html = static::getHtmlFromExcerpt($excerpt);

        return $html;
    }

    /**
     * Process Grav page link URL from HTML tag
     *
     * @param string $html              HTML tag e.g. `<a href="../foo">Page Link</a>`
     * @param PageInterface|null $page  Page, defaults to the current page object
     * @return string                   Returns final HTML string
     */
    public static function processLinkHtml($html, PageInterface $page = null)
    {
        $excerpt = static::getExcerptFromHtml($html, 'a');
        if (null === $excerpt) {
            return '';
        }

        $original_href = $excerpt['element']['attributes']['href'];
        $excerpt = static::processLinkExcerpt($excerpt, $page, 'link');
        $excerpt['element']['attributes']['data-href'] = $original_href;

        $html = static::getHtmlFromExcerpt($excerpt);

        return $html;
    }

    /**
     * Get an Excerpt array from a chunk of HTML
     *
     * @param string $html         Chunk of HTML
     * @param string $tag          A tag, for example `img`
     * @return array|null   returns nested array excerpt
     */
    public static function getExcerptFromHtml($html, $tag)
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $internalErrors = libxml_use_internal_errors(true);
        $doc->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        libxml_use_internal_errors($internalErrors);

        $elements = $doc->getElementsByTagName($tag);
        $excerpt = null;
        $inner = [];

        foreach ($elements as $element) {
            $attributes = [];
            foreach ($element->attributes as $name => $value) {
                $attributes[$name] = $value->value;
            }
            $excerpt = [
                'element' => [
                    'name'       => $element->tagName,
                    'attributes' => $attributes
                ]
            ];

            foreach ($element->childNodes as $node) {
                    $inner[] = $doc->saveHTML($node);
            }

            $excerpt = array_merge_recursive($excerpt, ['element' => ['text' => implode('', $inner)]]);


        }

        return $excerpt;
    }

    /**
     * Rebuild HTML tag from an excerpt array
     *
     * @param array $excerpt
     * @return string
     */
    public static function getHtmlFromExcerpt($excerpt)
    {
        $element = $excerpt['element'];
        $html = '<'.$element['name'];

        if (isset($element['attributes'])) {
            foreach ($element['attributes'] as $name => $value) {
                if ($value === null) {
                    continue;
                }
                $html .= ' '.$name.'="'.$value.'"';
            }
        }

        if (isset($element['text'])) {
            $html .= '>';
            $html .= is_array($element['text']) ? static::getHtmlFromExcerpt(['element' => $element['text']]) : $element['text'];
            $html .= '</'.$element['name'].'>';
        } else {
            $html .= ' />';
        }

        return $html;
    }

    /**
     * Process a Link excerpt
     *
     * @param array $excerpt
     * @param PageInterface|null $page  Page, defaults to the current page object
     * @param string $type
     * @return mixed
     */
    public static function processLinkExcerpt($excerpt, PageInterface $page = null, $type = 'link')
    {
        $excerpts = new ExcerptsObject($page);

        return $excerpts->processLinkExcerpt($excerpt, $type);
    }

    /**
     * Process an image excerpt
     *
     * @param array $excerpt
     * @param PageInterface|null $page  Page, defaults to the current page object
     * @return array
     */
    public static function processImageExcerpt(array $excerpt, PageInterface $page = null)
    {
        $excerpts = new ExcerptsObject($page);

        return $excerpts->processImageExcerpt($excerpt);
    }

    /**
     * Process media actions
     *
     * @param Medium $medium
     * @param string|array $url
     * @param PageInterface|null $page  Page, defaults to the current page object
     * @return Medium|Link
     */
    public static function processMediaActions($medium, $url, PageInterface $page = null)
    {
        $excerpts = new ExcerptsObject($page);

        return $excerpts->processMediaActions($medium, $url);
    }
}
