<?php

/**
 * @package    Grav\Common\Page
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Page\Medium;

use Grav\Common\Markdown\Parsedown;
use Grav\Common\Page\Markdown\Excerpts;

/**
 * Trait ParsedownHtmlTrait
 * @package Grav\Common\Page\Medium
 */
trait ParsedownHtmlTrait
{
    /** @var Parsedown|null */
    protected ?Parsedown $parsedown;

    /**
     * Return HTML markup from the medium.
     *
     * @param string|null $title
     * @param string|null $alt
     * @param string|null $class
     * @param string|null $id
     * @param bool $reset
     * @return string
     * @phpstan-impure
     */
    public function html(string $title = null, string $alt = null, string $class = null, string $id = null, bool $reset = true): string
    {
        $element = $this->parsedownElement($title, $alt, $class, $id, $reset);

        if (!isset($this->parsedown)) {
            $this->parsedown = new Parsedown(new Excerpts());
        }

        return $this->parsedown->elementToHtml($element);
    }
}
