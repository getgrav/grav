<?php

/**
 * @package    Grav\Common\Helpers
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Helpers;

use DOMText;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMWordsIterator;
use DOMLettersIterator;
use function in_array;
use function strlen;

/**
 * This file is part of https://github.com/Bluetel-Solutions/twig-truncate-extension
 *
 * Copyright (c) 2015 Bluetel Solutions developers@bluetel.co.uk
 * Copyright (c) 2015 Alex Wilson ajw@bluetel.co.uk
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
class Truncator
{
    /**
     * Safely truncates HTML by a given number of words.
     *
     * @param  string  $html     Input HTML.
     * @param  int     $limit    Limit to how many words we preserve.
     * @param  string  $ellipsis String to use as ellipsis (if any).
     * @return string            Safe truncated HTML.
     */
    public static function truncateWords($html, $limit = 0, $ellipsis = '')
    {
        if ($limit <= 0) {
            return $html;
        }

        $doc = self::htmlToDomDocument($html);
        $container = $doc->getElementsByTagName('div')->item(0);
        $container = $container->parentNode->removeChild($container);

        // Iterate over words.
        $words = new DOMWordsIterator($container);
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

                self::removeProceedingNodes($curNode, $container);

                if (!empty($ellipsis)) {
                    self::insertEllipsis($curNode, $ellipsis);
                }

                $truncated = true;

                break;
            }
        }

        // Return original HTML if not truncated.
        if ($truncated) {
            $html = self::getCleanedHtml($doc, $container);
        }

        return $html;
    }

    /**
     * Safely truncates HTML by a given number of letters.
     *
     * @param  string  $html     Input HTML.
     * @param  int     $limit    Limit to how many letters we preserve.
     * @param  string  $ellipsis String to use as ellipsis (if any).
     * @return string            Safe truncated HTML.
     */
    public static function truncateLetters($html, $limit = 0, $ellipsis = '')
    {
        if ($limit <= 0) {
            return $html;
        }

        $doc = self::htmlToDomDocument($html);
        $container = $doc->getElementsByTagName('div')->item(0);
        $container = $container->parentNode->removeChild($container);

        // Iterate over letters.
        $letters = new DOMLettersIterator($container);
        $truncated = false;
        foreach ($letters as $letter) {
            // If we have exceeded the limit, we want to delete the remainder of this document.
            if ($letters->key() >= $limit) {
                $currentText = $letters->currentTextPosition();
                $currentText[0]->nodeValue = mb_substr($currentText[0]->nodeValue, 0, $currentText[1] + 1);
                self::removeProceedingNodes($currentText[0], $container);

                if (!empty($ellipsis)) {
                    self::insertEllipsis($currentText[0], $ellipsis);
                }

                $truncated = true;

                break;
            }
        }

        // Return original HTML if not truncated.
        if ($truncated) {
            $html = self::getCleanedHtml($doc, $container);
        }

        return $html;
    }

    /**
     * Builds a DOMDocument object from a string containing HTML.
     *
     * @param string $html HTML to load
     * @return DOMDocument Returns a DOMDocument object.
     */
    public static function htmlToDomDocument($html)
    {
        if (!$html) {
            $html = '';
        }

        // Transform multibyte entities which otherwise display incorrectly.
        $html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');

        // Internal errors enabled as HTML5 not fully supported.
        libxml_use_internal_errors(true);

        // Instantiate new DOMDocument object, and then load in UTF-8 HTML.
        $dom = new DOMDocument();
        $dom->encoding = 'UTF-8';
        $dom->loadHTML("<div>$html</div>");

        return $dom;
    }

    /**
     * Removes all nodes after the current node.
     *
     * @param  DOMNode|DOMElement $domNode
     * @param  DOMNode|DOMElement $topNode
     * @return void
     */
    private static function removeProceedingNodes($domNode, $topNode)
    {
        /** @var DOMNode|null $nextNode */
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
     * Clean extra code
     *
     * @param DOMDocument $doc
     * @param DOMNode $container
     * @return string
     */
    private static function getCleanedHTML(DOMDocument $doc, DOMNode $container)
    {
        while ($doc->firstChild) {
            $doc->removeChild($doc->firstChild);
        }

        while ($container->firstChild) {
            $doc->appendChild($container->firstChild);
        }

        return trim($doc->saveHTML());
    }

    /**
     * Inserts an ellipsis
     *
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

            /** @var DOMNode|null $nextSibling */
            $nextSibling = $domNode->parentNode->parentNode->nextSibling;
            if ($nextSibling) {
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
     * @param string $text
     * @param int $length
     * @param string $ending
     * @param bool $exact
     * @param bool $considerHtml
     * @return string
     */
    public function truncate(
        $text,
        $length = 100,
        $ending = '...',
        $exact = false,
        $considerHtml = true
    ) {
        if ($considerHtml) {
            // if the plain text is shorter than the maximum length, return the whole text
            if (strlen(preg_replace('/<.*?>/', '', $text)) <= $length) {
                return $text;
            }

            // splits all html-tags to scanable lines
            preg_match_all('/(<.+?>)?([^<>]*)/s', $text, $lines, PREG_SET_ORDER);
            $total_length = strlen($ending);
            $truncate = '';
            $open_tags = [];

            foreach ($lines as $line_matchings) {
                // if there is any html-tag in this line, handle it and add it (uncounted) to the output
                if (!empty($line_matchings[1])) {
                    // if it's an "empty element" with or without xhtml-conform closing slash
                    if (preg_match('/^<(\s*.+?\/\s*|\s*(img|br|input|hr|area|base|basefont|col|frame|isindex|link|meta|param)(\s.+?)?)>$/is', $line_matchings[1])) {
                        // do nothing
                        // if tag is a closing tag
                    } elseif (preg_match('/^<\s*\/([^\s]+?)\s*>$/s', $line_matchings[1], $tag_matchings)) {
                        // delete tag from $open_tags list
                        $pos = array_search($tag_matchings[1], $open_tags);
                        if ($pos !== false) {
                            unset($open_tags[$pos]);
                        }
                        // if tag is an opening tag
                    } elseif (preg_match('/^<\s*([^\s>!]+).*?>$/s', $line_matchings[1], $tag_matchings)) {
                        // add tag to the beginning of $open_tags list
                        array_unshift($open_tags, strtolower($tag_matchings[1]));
                    }
                    // add html-tag to $truncate'd text
                    $truncate .= $line_matchings[1];
                }
                // calculate the length of the plain text part of the line; handle entities as one character
                $content_length = strlen(preg_replace('/&[0-9a-z]{2,8};|&#[0-9]{1,7};|[0-9a-f]{1,6};/i', ' ', $line_matchings[2]));
                if ($total_length+$content_length> $length) {
                    // the number of characters which are left
                    $left = $length - $total_length;
                    $entities_length = 0;
                    // search for html entities
                    if (preg_match_all('/&[0-9a-z]{2,8};|&#[0-9]{1,7};|[0-9a-f]{1,6};/i', $line_matchings[2], $entities, PREG_OFFSET_CAPTURE)) {
                        // calculate the real length of all entities in the legal range
                        foreach ($entities[0] as $entity) {
                            if ($entity[1]+1-$entities_length <= $left) {
                                $left--;
                                $entities_length += strlen($entity[0]);
                            } else {
                                // no more characters left
                                break;
                            }
                        }
                    }
                    $truncate .= substr($line_matchings[2], 0, $left+$entities_length);
                    // maximum lenght is reached, so get off the loop
                    break;
                } else {
                    $truncate .= $line_matchings[2];
                    $total_length += $content_length;
                }
                // if the maximum length is reached, get off the loop
                if ($total_length>= $length) {
                    break;
                }
            }
        } else {
            if (strlen($text) <= $length) {
                return $text;
            }

            $truncate = substr($text, 0, $length - strlen($ending));
        }
        // if the words shouldn't be cut in the middle...
        if (!$exact) {
            // ...search the last occurance of a space...
            $spacepos = strrpos($truncate, ' ');
            if (false !== $spacepos) {
                // ...and cut the text in this position
                $truncate = substr($truncate, 0, $spacepos);
            }
        }
        // add the defined ending to the text
        $truncate .= $ending;
        if (isset($open_tags)) {
            // close all unclosed html-tags
            foreach ($open_tags as $tag) {
                $truncate .= '</' . $tag . '>';
            }
        }

        return $truncate;
    }
}
