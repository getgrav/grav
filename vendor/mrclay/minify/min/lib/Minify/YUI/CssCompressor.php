<?php
/**
 * Class Minify_YUI_CssCompressor 
 * @package Minify
 *
 * YUI Compressor
 * Author: Julien Lecomte -  http://www.julienlecomte.net/
 * Author: Isaac Schlueter - http://foohack.com/
 * Author: Stoyan Stefanov - http://phpied.com/
 * Author: Steve Clay      - http://www.mrclay.org/ (PHP port)
 * Copyright (c) 2009 Yahoo! Inc.  All rights reserved.
 * The copyrights embodied in the content of this file are licensed
 * by Yahoo! Inc. under the BSD (revised) open source license.
 */

/**
 * Compress CSS (incomplete DO NOT USE)
 * 
 * @see https://github.com/yui/yuicompressor/blob/master/src/com/yahoo/platform/yui/compressor/CssCompressor.java
 *
 * @package Minify
 */
class Minify_YUI_CssCompressor {

    /**
     * Minify a CSS string
     *
     * @param string $css
     *
     * @return string
     */
    public function compress($css, $linebreakpos = 0)
    {
        $css = str_replace("\r\n", "\n", $css);

        /**
         * @todo comment removal
         * @todo re-port from newer Java version
         */

        // Normalize all whitespace strings to single spaces. Easier to work with that way.
        $css = preg_replace('@\s+@', ' ', $css);

        // Make a pseudo class for the Box Model Hack
        $css = preg_replace("@\"\\\\\"}\\\\\"\"@", "___PSEUDOCLASSBMH___", $css);

        // Remove the spaces before the things that should not have spaces before them.
        // But, be careful not to turn "p :link {...}" into "p:link{...}"
        // Swap out any pseudo-class colons with the token, and then swap back.
        $css = preg_replace_callback("@(^|\\})(([^\\{:])+:)+([^\\{]*\\{)@", array($this, '_removeSpacesCB'), $css);

        $css = preg_replace("@\\s+([!{};:>+\\(\\)\\],])@", "$1", $css);
        $css = str_replace("___PSEUDOCLASSCOLON___", ":", $css);

        // Remove the spaces after the things that should not have spaces after them.
        $css = preg_replace("@([!{}:;>+\\(\\[,])\\s+@", "$1", $css);

        // Add the semicolon where it's missing.
        $css = preg_replace("@([^;\\}])}@", "$1;}", $css);

        // Replace 0(px,em,%) with 0.
        $css = preg_replace("@([\\s:])(0)(px|em|%|in|cm|mm|pc|pt|ex)@", "$1$2", $css);

        // Replace 0 0 0 0; with 0.
        $css = str_replace(":0 0 0 0;", ":0;", $css);
        $css = str_replace(":0 0 0;", ":0;", $css);
        $css = str_replace(":0 0;", ":0;", $css);

        // Replace background-position:0; with background-position:0 0;
        $css = str_replace("background-position:0;", "background-position:0 0;", $css);

        // Replace 0.6 to .6, but only when preceded by : or a white-space
        $css = preg_replace("@(:|\\s)0+\\.(\\d+)@", "$1.$2", $css);

        // Shorten colors from rgb(51,102,153) to #336699
        // This makes it more likely that it'll get further compressed in the next step.
        $css = preg_replace_callback("@rgb\\s*\\(\\s*([0-9,\\s]+)\\s*\\)@", array($this, '_shortenRgbCB'), $css);

        // Shorten colors from #AABBCC to #ABC. Note that we want to make sure
        // the color is not preceded by either ", " or =. Indeed, the property
        //     filter: chroma(color="#FFFFFF");
        // would become
        //     filter: chroma(color="#FFF");
        // which makes the filter break in IE.
        $css = preg_replace_callback("@([^\"'=\\s])(\\s*)#([0-9a-fA-F])([0-9a-fA-F])([0-9a-fA-F])([0-9a-fA-F])([0-9a-fA-F])([0-9a-fA-F])@", array($this, '_shortenHexCB'), $css);

        // Remove empty rules.
        $css = preg_replace("@[^\\}]+\\{;\\}@", "", $css);

        $linebreakpos = isset($this->_options['linebreakpos'])
            ? $this->_options['linebreakpos']
            : 0;

        if ($linebreakpos > 0) {
            // Some source control tools don't like it when files containing lines longer
            // than, say 8000 characters, are checked in. The linebreak option is used in
            // that case to split long lines after a specific column.
            $i = 0;
            $linestartpos = 0;
            $sb = $css;

            // make sure strlen returns byte count
            $mbIntEnc = null;
            if (function_exists('mb_strlen') && ((int)ini_get('mbstring.func_overload') & 2)) {
                $mbIntEnc = mb_internal_encoding();
                mb_internal_encoding('8bit');
            }
            $sbLength = strlen($css);
            while ($i < $sbLength) {
                $c = $sb[$i++];
                if ($c === '}' && $i - $linestartpos > $linebreakpos) {
                    $sb = substr_replace($sb, "\n", $i, 0);
                    $sbLength++;
                    $linestartpos = $i;
                }
            }
            $css = $sb;

            // undo potential mb_encoding change
            if ($mbIntEnc !== null) {
                mb_internal_encoding($mbIntEnc);
            }
        }

        // Replace the pseudo class for the Box Model Hack
        $css = str_replace("___PSEUDOCLASSBMH___", "\"\\\\\"}\\\\\"\"", $css);

        // Replace multiple semi-colons in a row by a single one
        // See SF bug #1980989
        $css = preg_replace("@;;+@", ";", $css);

        // prevent triggering IE6 bug: http://www.crankygeek.com/ie6pebug/
        $css = preg_replace('/:first-l(etter|ine)\\{/', ':first-l$1 {', $css);

        // Trim the final string (for any leading or trailing white spaces)
        $css = trim($css);

        return $css;
    }

    protected function _removeSpacesCB($m)
    {
        return str_replace(':', '___PSEUDOCLASSCOLON___', $m[0]);
    }

    protected function _shortenRgbCB($m)
    {
        $rgbcolors = explode(',', $m[1]);
        $hexcolor = '#';
        for ($i = 0; $i < count($rgbcolors); $i++) {
            $val = round($rgbcolors[$i]);
            if ($val < 16) {
                $hexcolor .= '0';
            }
            $hexcolor .= dechex($val);
        }
        return $hexcolor;
    }

    protected function _shortenHexCB($m)
    {
        // Test for AABBCC pattern
        if ((strtolower($m[3])===strtolower($m[4])) &&
                (strtolower($m[5])===strtolower($m[6])) &&
                (strtolower($m[7])===strtolower($m[8]))) {
            return $m[1] . $m[2] . "#" . $m[3] . $m[5] . $m[7];
        } else {
            return $m[0];
        }
    }
}