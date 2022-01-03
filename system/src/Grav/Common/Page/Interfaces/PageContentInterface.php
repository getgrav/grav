<?php

/**
 * @package    Grav\Common\Page
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Page\Interfaces;

use Grav\Common\Data\Blueprint;
use Grav\Common\Media\Interfaces\MediaCollectionInterface;
use Grav\Common\Page\Header;

/**
 * Methods currently implemented in Flex Page emulation layer.
 */
interface PageContentInterface
{
    /**
     * Gets and Sets the header based on the YAML configuration at the top of the .md file
     *
     * @param  object|array|null $var a YAML object representing the configuration for the file
     * @return \stdClass|Header      The current YAML configuration
     */
    public function header($var = null);

    /**
     * Get the summary.
     *
     * @param int|null $size Max summary size.
     * @param bool $textOnly Only count text size.
     * @return string
     */
    public function summary($size = null, $textOnly = false);

    /**
     * Sets the summary of the page
     *
     * @param string $summary Summary
     */
    public function setSummary($summary);

    /**
     * Gets and Sets the content based on content portion of the .md file
     *
     * @param  string|null $var Content
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
     * @param string|null $content
     */
    public function setRawContent($content);

    /**
     * Gets and Sets the Page raw content
     *
     * @param string|null $var
     * @return string
     */
    public function rawMarkdown($var = null);

    /**
     * Get value from a page variable (used mostly for creating edit forms).
     *
     * @param string $name Variable name.
     * @param mixed|null $default
     * @return mixed
     */
    public function value($name, $default = null);

    /**
     * Gets and sets the associated media as found in the page folder.
     *
     * @param  MediaCollectionInterface|null $var New media object.
     * @return MediaCollectionInterface           Representation of associated media.
     */
    public function media($var = null);

    /**
     * Gets and sets the title for this Page.  If no title is set, it will use the slug() to get a name
     *
     * @param  string|null $var New title of the Page
     * @return string           The title of the Page
     */
    public function title($var = null);

    /**
     * Gets and sets the menu name for this Page.  This is the text that can be used specifically for navigation.
     * If no menu field is set, it will use the title()
     *
     * @param  string|null $var New menu field for the page
     * @return string           The menu field for the page
     */
    public function menu($var = null);

    /**
     * Gets and Sets whether or not this Page is visible for navigation
     *
     * @param  bool|null $var   New value
     * @return bool             True if the page is visible
     */
    public function visible($var = null);

    /**
     * Gets and Sets whether or not this Page is considered published
     *
     * @param  bool|null $var   New value
     * @return bool             True if the page is published
     */
    public function published($var = null);

    /**
     * Gets and Sets the Page publish date
     *
     * @param  string|null $var String representation of the new date
     * @return int              Unix timestamp representation of the date
     */
    public function publishDate($var = null);

    /**
     * Gets and Sets the Page unpublish date
     *
     * @param  string|null $var String representation of the new date
     * @return int|null         Unix timestamp representation of the date
     */
    public function unpublishDate($var = null);

    /**
     * Gets and Sets the process setup for this Page. This is multi-dimensional array that consists of
     * a simple array of arrays with the form array("markdown"=>true) for example
     *
     * @param  array|null $var New array of name value pairs where the name is the process and value is true or false
     * @return array            Array of name value pairs where the name is the process and value is true or false
     */
    public function process($var = null);

    /**
     * Gets and Sets the slug for the Page. The slug is used in the URL routing. If not set it uses
     * the parent folder from the path
     *
     * @param  string|null $var New slug, e.g. 'my-blog'
     * @return string           The slug
     */
    public function slug($var = null);

    /**
     * Get/set order number of this page.
     *
     * @param int|null $var      New order as a number
     * @return string|bool       Order in a form of '02.' or false if not set
     */
    public function order($var = null);

    /**
     * Gets and sets the identifier for this Page object.
     *
     * @param  string|null $var New identifier
     * @return string           The identifier
     */
    public function id($var = null);

    /**
     * Gets and sets the modified timestamp.
     *
     * @param  int|null $var New modified unix timestamp
     * @return int           Modified unix timestamp
     */
    public function modified($var = null);

    /**
     * Gets and sets the option to show the last_modified header for the page.
     *
     * @param  bool|null $var New last_modified header value
     * @return bool           Show last_modified header
     */
    public function lastModified($var = null);

    /**
     * Get/set the folder.
     *
     * @param string|null $var New folder
     * @return string|null     The folder
     */
    public function folder($var = null);

    /**
     * Gets and sets the date for this Page object. This is typically passed in via the page headers
     *
     * @param  string|null $var New string representation of a date
     * @return int              Unix timestamp representation of the date
     */
    public function date($var = null);

    /**
     * Gets and sets the date format for this Page object. This is typically passed in via the page headers
     * using typical PHP date string structure - http://php.net/manual/en/function.date.php
     *
     * @param  string|null $var New string representation of a date format
     * @return string           String representation of a date format
     */
    public function dateformat($var = null);

    /**
     * Gets and sets the taxonomy array which defines which taxonomies this page identifies itself with.
     *
     * @param  array|null $var  New array of taxonomies
     * @return array            An array of taxonomies
     */
    public function taxonomy($var = null);

    /**
     * Gets the configured state of the processing method.
     *
     * @param  string $process The process name, eg "twig" or "markdown"
     * @return bool            Whether or not the processing method is enabled for this Page
     */
    public function shouldProcess($process);

    /**
     * Returns true if page is a module.
     *
     * @return bool
     */
    public function isModule(): bool;

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

    /**
     * Returns the blueprint from the page.
     *
     * @param string $name Name of the Blueprint form. Used by flex only.
     * @return Blueprint Returns a Blueprint.
     */
    public function getBlueprint(string $name = '');
}
