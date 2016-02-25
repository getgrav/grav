<?php
namespace Grav\Common\Helpers;

use DOMDocument;

/**
 * This file is part of urodoz/truncateHTML.
 *
 * (c) Albert Lacarta <urodoz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class Truncator {

    public static $default_options = array(
        'ellipsis' => '…',
        'break' => ' ',
        'length_in_chars' => false,
        'word_safe' => false,
    );

    // These tags are allowed to have an ellipsis inside
    public static $ellipsable_tags = array(
        'p', 'ol', 'ul', 'li',
        'div', 'header', 'article', 'nav',
        'section', 'footer', 'aside',
        'dd', 'dt', 'dl',
    );

    public static $self_closing_tags = array(
        'br', 'hr', 'img',
    );

    /**
     * Truncate given HTML string to specified length.
     * If length_in_chars is false it's trimmed by number
     * of words, otherwise by number of characters.
     *
     * @param  string        $html
     * @param  integer       $length
     * @param  string|array  $opts
     * @return string
     */
    public static function truncate($html, $length, $opts=array())
    {
        if (is_string($opts)) $opts = array('ellipsis' => $opts);
        $opts = array_merge(static::$default_options, $opts);
        // wrap the html in case it consists of adjacent nodes like <p>foo</p><p>bar</p>
        $html = mb_convert_encoding("<div>".$html."</div>", 'HTML-ENTITIES', 'UTF-8');

        $root_node = null;
        // Parse using HTML5Lib if it's available.
        if (class_exists('HTML5Lib\\Parser')) {
            try {
                $doc = \HTML5Lib\Parser::parse($html);
                $root_node = $doc->documentElement->lastChild->lastChild;
            }
            catch (\Exception $e) {
                ;
            }
        }
        if ($root_node === null) {
            // HTML5Lib not available so we'll have to use DOMDocument
            // We'll only be able to parse HTML5 if it's valid XML
            $doc = new DOMDocument('4.01', 'utf-8');
            $doc->formatOutput = false;
            $doc->preserveWhiteSpace = true;
            // loadHTML will fail with HTML5 tags (article, nav, etc)
            // so we need to suppress errors and if it fails to parse we
            // retry with the XML parser instead
            $prev_use_errors = libxml_use_internal_errors(true);
            if ($doc->loadHTML($html)) {
                $root_node = $doc->documentElement->lastChild->lastChild;
            }
            else if ($doc->loadXML($html)) {
                $root_node = $doc->documentElement;
            }
            else {
                libxml_use_internal_errors($prev_use_errors);
                throw new \RuntimeException;
            }
            libxml_use_internal_errors($prev_use_errors);
        }
        list($text, $_, $opts) = static::truncateNode($doc, $root_node, $length, $opts);

        $text = mb_substr(mb_substr($text, 0, -6), 5);

        return $text;
    }

    protected static function truncateNode($doc, $node, $length, $opts)
    {
        if ($length === 0 && !static::ellipsable($node)) {
            return array('', 1, $opts);
        }
        list($inner, $remaining, $opts) = static::innerTruncate($doc, $node, $length, $opts);
        if (0 === mb_strlen($inner)) {
            return array(in_array(mb_strtolower($node->nodeName), static::$self_closing_tags) ? $doc->saveXML($node) : "", $length - $remaining, $opts);
        }
        while($node->firstChild) {
            $node->removeChild($node->firstChild);
        }
        $newNode = $doc->createDocumentFragment();
        // handle the ampersand
        $newNode->appendXml(static::xmlEscape($inner));
        $node->appendChild($newNode);
        return array($doc->saveXML($node), $length - $remaining, $opts);
    }

    protected static function innerTruncate($doc, $node, $length, $opts)
    {
        $inner = '';
        $remaining = $length;
        foreach($node->childNodes as $childNode) {
            if ($childNode->nodeType === XML_ELEMENT_NODE) {
                list($txt, $nb, $opts) = static::truncateNode($doc, $childNode, $remaining, $opts);
            }
            else if ($childNode->nodeType === XML_TEXT_NODE) {
                list($txt, $nb, $opts) = static::truncateText($childNode, $remaining, $opts);
            } else {
                $txt = '';
                $nb  = 0;
            }

            // unhandle the ampersand
            $txt = static::xmlUnescape($txt);

            $remaining -= $nb;
            $inner .= $txt;
            if ($remaining < 0) {
                if (static::ellipsable($node)) {
                    $inner = preg_replace('/(?:[\s\pP]+|(?:&(?:[a-z]+|#[0-9]+);?))*$/u', '', $inner).$opts['ellipsis'];
                    $opts['ellipsis'] = '';
                    $opts['was_truncated'] = true;
                }
                break;
            }
        }
        return array($inner, $remaining, $opts);
    }

    protected static function truncateText($node, $length, $opts)
    {
        $string = $node->textContent;

        if ($opts['length_in_chars']) {
            $count = mb_strlen($string);
            if ($count <= $length && $length > 0) {
                return array($string, $count, $opts);
            }
            if ($opts['word_safe']) {
                if (false !== ($breakpoint = mb_strpos($string, $opts['break'], $length))) {
                    if ($breakpoint < mb_strlen($string) - 1) {
                        $string = mb_substr($string, 0, $breakpoint) . $opts['break'];
                    }
                }
                return array($string, $count, $opts);
            }
            return array(mb_substr($node->textContent, 0, $length), $count, $opts);
        }
        else {
            preg_match_all('/\s*\S+/', $string, $words);
            $words = $words[0];
            $count = count($words);
            if ($count <= $length && $length > 0) {
                return array($string, $count, $opts);
            }
            return array(implode('', array_slice($words, 0, $length)), $count, $opts);
        }
    }

    protected static function ellipsable($node)
    {
        return ($node instanceof DOMDocument)
        || in_array(mb_strtolower($node->nodeName), static::$ellipsable_tags)
            ;
    }

    protected static function xmlEscape($string)
    {
        $string = str_replace('&', '&amp;', $string);
        $string = str_replace('<?', '&lt;?', $string);
        return $string;
    }

    protected static function xmlUnescape($string)
    {
        $string = str_replace('&amp;', '&', $string);
        $string = str_replace('&lt;?', '<?', $string);
        return $string;
    }
}
