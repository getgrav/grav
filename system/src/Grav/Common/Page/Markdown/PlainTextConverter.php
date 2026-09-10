<?php

/**
 * @package    Grav\Common\Page
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Page\Markdown;

use League\HTMLToMarkdown\Converter\TextConverter;
use League\HTMLToMarkdown\ElementInterface;

/**
 * Text node converter for Markdown output that keeps `&`, `<` and `>` as
 * the characters they are.
 *
 * The stock converter re-encodes them as HTML entities so the Markdown
 * round-trips through an HTML renderer. An agent reads the Markdown as
 * text, and `Range &amp; Display` is noise to it.
 *
 * @package Grav\Common\Page\Markdown
 */
class PlainTextConverter extends TextConverter
{
    /**
     * @param ElementInterface $element
     * @return string
     */
    public function convert(ElementInterface $element): string
    {
        return htmlspecialchars_decode(parent::convert($element), ENT_NOQUOTES);
    }
}
