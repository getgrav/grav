<?php
/**
 * @package    Grav.Common.Helpers
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Helpers;

use DOMText;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMWordsIterator;
use DOMLettersIterator;

/**
 * This file is part of https://github.com/Bluetel-Solutions/twig-truncate-extension
 *
 * Copyright (c) 2015 Bluetel Solutions developers@bluetel.co.uk
 * Copyright (c) 2015 Alex Wilson ajw@bluetel.co.uk
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class Truncator {

    /**
     * Safely truncates HTML by a given number of words.
     * @param  string  $html     Input HTML.
     * @param  integer $limit    Limit to how many words we preserve.
     * @param  string  $ellipsis String to use as ellipsis (if any).
     * @return string            Safe truncated HTML.
     */
    public static function truncateWords($html, $limit = 0, $ellipsis = '')
    {
        if ($limit <= 0) {
            return $html;
        }

        $dom = self::htmlToDomDocument($html);

        // Grab the body of our DOM.
        $body = $dom->getElementsByTagName("body")->item(0);

        // Iterate over words.
        $words = new DOMWordsIterator($body);
        $truncated = false;
        foreach ($words as $word) {

            // If we have exceeded the limit, we delete the remainder of the content.
            if ($words->key() >= $limit) {

                // Grab current position.
                $currentWordPosition = $words->currentWordPosition();
                $curNode = $currentWordPosition[0];
                $offset = $currentWordPosition[1];
                $words = $currentWordPosition[2];

                $curNode->nodeValue = substr(
                    $curNode->nodeValue,
                    0,
                    $words[$offset][1] + strlen($words[$offset][0])
                );

                self::removeProceedingNodes($curNode, $body);

                if (!empty($ellipsis)) {
                    self::insertEllipsis($curNode, $ellipsis);
                }

                $truncated = true;

                break;
            }

        }

        // Return original HTML if not truncated.
        if ($truncated) {
            return self::innerHTML($body);
        } else {
            return $html;
        }
    }

    /**
     * Safely truncates HTML by a given number of letters.
     * @param  string  $html     Input HTML.
     * @param  integer $limit    Limit to how many letters we preserve.
     * @param  string  $ellipsis String to use as ellipsis (if any).
     * @return string            Safe truncated HTML.
     */
    public static function truncateLetters($html, $limit = 0, $ellipsis = "")
    {
        if ($limit <= 0) {
            return $html;
        }

        $dom = self::htmlToDomDocument($html);

        // Grab the body of our DOM.
        $body = $dom->getElementsByTagName('body')->item(0);

        // Iterate over letters.
        $letters = new DOMLettersIterator($body);
        $truncated = false;
        foreach ($letters as $letter) {

            // If we have exceeded the limit, we want to delete the remainder of this document.
            if ($letters->key() >= $limit) {

                $currentText = $letters->currentTextPosition();
                $currentText[0]->nodeValue = mb_substr($currentText[0]->nodeValue, 0, $currentText[1] + 1);
                self::removeProceedingNodes($currentText[0], $body);

                if (!empty($ellipsis)) {
                    self::insertEllipsis($currentText[0], $ellipsis);
                }

                $truncated = true;

                break;
            }
        }

        // Return original HTML if not truncated.
        if ($truncated) {
            return self::innerHTML($body);
        } else {
            return $html;
        }
    }

    /**
     * Builds a DOMDocument object from a string containing HTML.
     * @param string $html HTML to load
     * @returns DOMDocument Returns a DOMDocument object.
     */
    public static function htmlToDomDocument($html)
    {
        if (!$html) {
            $html = '<p></p>';
        }

        // Transform multibyte entities which otherwise display incorrectly.
        $html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');

        // Internal errors enabled as HTML5 not fully supported.
        libxml_use_internal_errors(true);

        // Instantiate new DOMDocument object, and then load in UTF-8 HTML.
        $dom = new DOMDocument();
        $dom->encoding = 'UTF-8';
        $dom->loadHTML($html);

        return $dom;
    }

    /**
     * Removes all nodes after the current node.
     * @param  DOMNode|DOMElement $domNode
     * @param  DOMNode|DOMElement $topNode
     * @return void
     */
    private static function removeProceedingNodes($domNode, $topNode)
    {
        $nextNode = $domNode->nextSibling;

        if ($nextNode !== null) {
            self::removeProceedingNodes($nextNode, $topNode);
            $domNode->parentNode->removeChild($nextNode);
        } else {
            //scan upwards till we find a sibling
            $curNode = $domNode->parentNode;
            while ($curNode !== $topNode) {
                if ($curNode->nextSibling !== null) {
                    $curNode = $curNode->nextSibling;
                    self::removeProceedingNodes($curNode, $topNode);
                    $curNode->parentNode->removeChild($curNode);
                    break;
                }
                $curNode = $curNode->parentNode;
            }
        }
    }

    /**
     * Inserts an ellipsis
     * @param  DOMNode|DOMElement $domNode  Element to insert after.
     * @param  string             $ellipsis Text used to suffix our document.
     * @return void
     */
    private static function insertEllipsis($domNode, $ellipsis)
    {
        $avoid = array('a', 'strong', 'em', 'h1', 'h2', 'h3', 'h4', 'h5'); //html tags to avoid appending the ellipsis to

        if ($domNode->parentNode->parentNode !== null && in_array($domNode->parentNode->nodeName, $avoid, true)) {
            // Append as text node to parent instead
            $textNode = new DOMText($ellipsis);

            if ($domNode->parentNode->parentNode->nextSibling) {
                $domNode->parentNode->parentNode->insertBefore($textNode, $domNode->parentNode->parentNode->nextSibling);
            } else {
                $domNode->parentNode->parentNode->appendChild($textNode);
            }

        } else {
            // Append to current node
            $domNode->nodeValue = rtrim($domNode->nodeValue) . $ellipsis;
        }
    }

    /**
     * Returns the innerHTML of a particular DOMElement
     *
     * @param $element
     * @return string
     */
    private static function innerHTML($element) {
        $innerHTML = '';
        $children = $element->childNodes;
        foreach ($children as $child)
        {
            $tmp_dom = new DOMDocument();
            $tmp_dom->appendChild($tmp_dom->importNode($child, true));
            $innerHTML.=trim($tmp_dom->saveHTML());
        }
        return $innerHTML;
    }

}
