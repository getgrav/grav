<?php

/**
 * @package    Grav\Common\Page
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Page\Interfaces;

use Grav\Common\Page\Media;

/**
 * Methods currently implemented in Flex Page emulation layer.
 */
interface PageContentInterface
{
    /**
     * Gets and Sets the header based on the YAML configuration at the top of the .md file
     *
     * @param  object|array $var a YAML object representing the configuration for the file
     *
     * @return object      the current YAML configuration
     */
    public function header($var = null);

    /**
     * Get the summary.
     *
     * @param  int $size Max summary size.
     *
     * @param bool $textOnly Only count text size.
     *
     * @return string
     */
    public function summary($size = null, $textOnly = false);

    /**
     * Gets and Sets the content based on content portion of the .md file
     *
     * @param  string $var Content
     *
     * @return string      Content
     */
    public function content($var = null);

    /**
     * Needed by the onPageContentProcessed event to get the raw page content
     *
     * @return string   the current page content
     */
    public function getRawContent();

    /**
     * Needed by the onPageContentProcessed event to set the raw page content
     *
     * @param string $content
     */
    public function setRawContent($content);

    /**
     * Gets and Sets the Page raw content
     *
     * @param string|null $var
     *
     * @return null
     */
    public function rawMarkdown($var = null);

    /**
     * Get value from a page variable (used mostly for creating edit forms).
     *
     * @param string $name Variable name.
     * @param mixed $default
     *
     * @return mixed
     */
    public function value($name, $default = null);

    /**
     * Gets and sets the associated media as found in the page folder.
     *
     * @param  Media $var Representation of associated media.
     *
     * @return Media      Representation of associated media.
     */
    public function media($var = null);

    /**
     * Gets and sets the title for this Page.  If no title is set, it will use the slug() to get a name
     *
     * @param  string $var the title of the Page
     *
     * @return string      the title of the Page
     */
    public function title($var = null);

    /**
     * Gets and sets the menu name for this Page.  This is the text that can be used specifically for navigation.
     * If no menu field is set, it will use the title()
     *
     * @param  string $var the menu field for the page
     *
     * @return string      the menu field for the page
     */
    public function menu($var = null);

    /**
     * Gets and Sets whether or not this Page is visible for navigation
     *
     * @param  bool $var true if the page is visible
     *
     * @return bool      true if the page is visible
     */
    public function visible($var = null);

    /**
     * Gets and Sets whether or not this Page is considered published
     *
     * @param  bool $var true if the page is published
     *
     * @return bool      true if the page is published
     */
    public function published($var = null);

    /**
     * Gets and Sets the Page publish date
     *
     * @param  string $var string representation of a date
     *
     * @return int         unix timestamp representation of the date
     */
    public function publishDate($var = null);

    /**
     * Gets and Sets the Page unpublish date
     *
     * @param  string $var string representation of a date
     *
     * @return int|null         unix timestamp representation of the date
     */
    public function unpublishDate($var = null);

    /**
     * Gets and Sets the process setup for this Page. This is multi-dimensional array that consists of
     * a simple array of arrays with the form array("markdown"=>true) for example
     *
     * @param  array $var an Array of name value pairs where the name is the process and value is true or false
     *
     * @return array      an Array of name value pairs where the name is the process and value is true or false
     */
    public function process($var = null);

    /**
     * Gets and Sets the slug for the Page. The slug is used in the URL routing. If not set it uses
     * the parent folder from the path
     *
     * @param  string $var the slug, e.g. 'my-blog'
     *
     * @return string      the slug
     */
    public function slug($var = null);

    /**
     * Get/set order number of this page.
     *
     * @param int $var
     *
     * @return int|bool
     */
    public function order($var = null);

    /**
     * Gets and sets the identifier for this Page object.
     *
     * @param  string $var the identifier
     *
     * @return string      the identifier
     */
    public function id($var = null);

    /**
     * Gets and sets the modified timestamp.
     *
     * @param  int $var modified unix timestamp
     *
     * @return int      modified unix timestamp
     */
    public function modified($var = null);

    /**
     * Gets and sets the option to show the last_modified header for the page.
     *
     * @param  boolean $var show last_modified header
     *
     * @return boolean      show last_modified header
     */
    public function lastModified($var = null);

    /**
     * Get/set the folder.
     *
     * @param string $var Optional path
     *
     * @return string|null
     */
    public function folder($var = null);

    /**
     * Gets and sets the date for this Page object. This is typically passed in via the page headers
     *
     * @param  string $var string representation of a date
     *
     * @return int         unix timestamp representation of the date
     */
    public function date($var = null);

    /**
     * Gets and sets the date format for this Page object. This is typically passed in via the page headers
     * using typical PHP date string structure - http://php.net/manual/en/function.date.php
     *
     * @param  string $var string representation of a date format
     *
     * @return string      string representation of a date format
     */
    public function dateformat($var = null);

    /**
     * Gets and sets the taxonomy array which defines which taxonomies this page identifies itself with.
     *
     * @param  array $var an array of taxonomies
     *
     * @return array      an array of taxonomies
     */
    public function taxonomy($var = null);

    /**
     * Gets the configured state of the processing method.
     *
     * @param  string $process the process, eg "twig" or "markdown"
     *
     * @return bool            whether or not the processing method is enabled for this Page
     */
    public function shouldProcess($process);

    /**
     * Returns whether or not this Page object has a .md file associated with it or if its just a directory.
     *
     * @return bool True if its a page with a .md file associated
     */
    public function isPage();

    /**
     * Returns whether or not this Page object is a directory or a page.
     *
     * @return bool True if its a directory
     */
    public function isDir();

    /**
     * Returns whether the page exists in the filesystem.
     *
     * @return bool
     */
    public function exists();
}
