<?php
/**
 * @package    Grav\Framework\ContentBlock
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\ContentBlock;

/**
 * Interface HtmlBlockInterface
 * @package Grav\Framework\ContentBlock
 */
interface HtmlBlockInterface extends ContentBlockInterface
{
    /**
     * @return array
     */
    public function getAssets();

    /**
     * @return array
     */
    public function getFrameworks();

    /**
     * @param string $location
     * @return array
     */
    public function getStyles($location = 'head');

    /**
     * @param string $location
     * @return array
     */
    public function getScripts($location = 'head');

    /**
     * @param string $location
     * @return array
     */
    public function getHtml($location = 'bottom');

    /**
     * @param string $framework
     * @return $this
     */
    public function addFramework($framework);

    /**
     * @param string|array $element
     * @param int $priority
     * @param string $location
     * @return bool
     *
     * @example $block->addStyle('assets/js/my.js');
     * @example $block->addStyle(['href' => 'assets/js/my.js', 'media' => 'screen']);
     */
    public function addStyle($element, $priority = 0, $location = 'head');

    /**
     * @param string|array $element
     * @param int $priority
     * @param string $location
     * @return bool
     */
    public function addInlineStyle($element, $priority = 0, $location = 'head');

    /**
     * @param string|array $element
     * @param int $priority
     * @param string $location
     * @return bool
     */
    public function addScript($element, $priority = 0, $location = 'head');


    /**
     * @param string|array $element
     * @param int $priority
     * @param string $location
     * @return bool
     */
    public function addInlineScript($element, $priority = 0, $location = 'head');

    /**
     * @param string $html
     * @param int $priority
     * @param string $location
     * @return bool
     */
    public function addHtml($html, $priority = 0, $location = 'bottom');
}
