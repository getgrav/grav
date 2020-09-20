<?php
namespace Grav\Common\Page\Interfaces;

use Exception;
use Grav\Common\Data\Blueprint;
use Grav\Common\Page\Collection;
use InvalidArgumentException;
use RocketTheme\Toolbox\File\MarkdownFile;
use SplFileInfo;

/**
 * Interface PageLegacyInterface
 * @package Grav\Common\Page\Interfaces
 */
interface PageLegacyInterface
{
    /**
     * Initializes the page instance variables based on a file
     *
     * @param  SplFileInfo $file The file information for the .md file that the page represents
     * @param  string|null $extension
     * @return $this
     */
    public function init(SplFileInfo $file, $extension = null);

    /**
     * Gets and Sets the raw data
     *
     * @param  string|null $var Raw content string
     * @return string      Raw content string
     */
    public function raw($var = null);

    /**
     * Gets and Sets the page frontmatter
     *
     * @param string|null $var
     * @return string
     */
    public function frontmatter($var = null);

    /**
     * Modify a header value directly
     *
     * @param string $key
     * @param mixed $value
     */
    public function modifyHeader($key, $value);

    /**
     * @return int
     */
    public function httpResponseCode();

    /**
     * @return array
     */
    public function httpHeaders();

    /**
     * Get the contentMeta array and initialize content first if it's not already
     *
     * @return mixed
     */
    public function contentMeta();

    /**
     * Add an entry to the page's contentMeta array
     *
     * @param string $name
     * @param mixed $value
     */
    public function addContentMeta($name, $value);

    /**
     * Return the whole contentMeta array as it currently stands
     *
     * @param string|null $name
     * @return mixed
     */
    public function getContentMeta($name = null);

    /**
     * Sets the whole content meta array in one shot
     *
     * @param array $content_meta
     * @return array
     */
    public function setContentMeta($content_meta);

    /**
     * Fires the onPageContentProcessed event, and caches the page content using a unique ID for the page
     */
    public function cachePageContent();

    /**
     * Get file object to the page.
     *
     * @return MarkdownFile|null
     */
    public function file();

    /**
     * Save page if there's a file assigned to it.
     *
     * @param bool|mixed $reorder Internal use.
     */
    public function save($reorder = true);

    /**
     * Prepare move page to new location. Moves also everything that's under the current page.
     *
     * You need to call $this->save() in order to perform the move.
     *
     * @param PageInterface $parent New parent page.
     * @return $this
     */
    public function move(PageInterface $parent);

    /**
     * Prepare a copy from the page. Copies also everything that's under the current page.
     *
     * Returns a new Page object for the copy.
     * You need to call $this->save() in order to perform the move.
     *
     * @param PageInterface $parent New parent page.
     * @return $this
     */
    public function copy(PageInterface $parent);

    /**
     * Get blueprints for the page.
     *
     * @return Blueprint
     */
    public function blueprints();

    /**
     * Get the blueprint name for this page.  Use the blueprint form field if set
     *
     * @return string
     */
    public function blueprintName();

    /**
     * Validate page header.
     *
     * @throws Exception
     */
    public function validate();

    /**
     * Filter page header from illegal contents.
     */
    public function filter();

    /**
     * Get unknown header variables.
     *
     * @return array
     */
    public function extra();

    /**
     * Convert page to an array.
     *
     * @return array
     */
    public function toArray();

    /**
     * Convert page to YAML encoded string.
     *
     * @return string
     */
    public function toYaml();

    /**
     * Convert page to JSON encoded string.
     *
     * @return string
     */
    public function toJson();

    /**
     * Returns normalized list of name => form pairs.
     *
     * @return array
     */
    public function forms();

    /**
     * @param array $new
     */
    public function addForms(array $new);

    /**
     * Gets and sets the name field.  If no name field is set, it will return 'default.md'.
     *
     * @param  string|null $var The name of this page.
     * @return string      The name of this page.
     */
    public function name($var = null);

    /**
     * Returns child page type.
     *
     * @return string
     */
    public function childType();

    /**
     * Gets and sets the template field. This is used to find the correct Twig template file to render.
     * If no field is set, it will return the name without the .md extension
     *
     * @param  string|null $var the template name
     * @return string      the template name
     */
    public function template($var = null);

    /**
     * Allows a page to override the output render format, usually the extension provided
     * in the URL. (e.g. `html`, `json`, `xml`, etc).
     *
     * @param string|null $var
     * @return string
     */
    public function templateFormat($var = null);

    /**
     * Gets and sets the extension field.
     *
     * @param string|null $var
     * @return string|null
     */
    public function extension($var = null);

    /**
     * Gets and sets the expires field. If not set will return the default
     *
     * @param  int|null $var The new expires value.
     * @return int      The expires value
     */
    public function expires($var = null);

    /**
     * Gets and sets the cache-control property.  If not set it will return the default value (null)
     * https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Cache-Control for more details on valid options
     *
     * @param string|null $var
     * @return string|null
     */
    public function cacheControl($var = null);

    /**
     * @param bool|null $var
     * @return bool
     */
    public function ssl($var = null);

    /**
     * Returns the state of the debugger override etting for this page
     *
     * @return bool
     */
    public function debugger();

    /**
     * Function to merge page metadata tags and build an array of Metadata objects
     * that can then be rendered in the page.
     *
     * @param  array|null $var an Array of metadata values to set
     * @return array      an Array of metadata values for the page
     */
    public function metadata($var = null);

    /**
     * Gets and sets the option to show the etag header for the page.
     *
     * @param  bool|null $var show etag header
     * @return bool      show etag header
     */
    public function eTag($var = null): bool;

    /**
     * Gets and sets the path to the .md file for this Page object.
     *
     * @param  string|null $var the file path
     * @return string|null      the file path
     */
    public function filePath($var = null);

    /**
     * Gets the relative path to the .md file
     *
     * @return string The relative file path
     */
    public function filePathClean();

    /**
     * Gets and sets the order by which any sub-pages should be sorted.
     *
     * @param  string|null $var the order, either "asc" or "desc"
     * @return string      the order, either "asc" or "desc"
     * @deprecated 1.6
     */
    public function orderDir($var = null);

    /**
     * Gets and sets the order by which the sub-pages should be sorted.
     *
     * default - is the order based on the file system, ie 01.Home before 02.Advark
     * title - is the order based on the title set in the pages
     * date - is the order based on the date set in the pages
     * folder - is the order based on the name of the folder with any numerics omitted
     *
     * @param  string|null $var supported options include "default", "title", "date", and "folder"
     * @return string      supported options include "default", "title", "date", and "folder"
     * @deprecated 1.6
     */
    public function orderBy($var = null);

    /**
     * Gets the manual order set in the header.
     *
     * @param  string|null $var supported options include "default", "title", "date", and "folder"
     * @return array
     * @deprecated 1.6
     */
    public function orderManual($var = null);

    /**
     * Gets and sets the maxCount field which describes how many sub-pages should be displayed if the
     * sub_pages header property is set for this page object.
     *
     * @param  int|null $var the maximum number of sub-pages
     * @return int      the maximum number of sub-pages
     * @deprecated 1.6
     */
    public function maxCount($var = null);

    /**
     * Gets and sets the modular var that helps identify this page is a modular child
     *
     * @param  bool|null $var true if modular_twig
     * @return bool      true if modular_twig
     * @deprecated 1.7 Use ->isModule() or ->modularTwig() method instead.
     */
    public function modular($var = null);

    /**
     * Gets and sets the modular_twig var that helps identify this page as a modular child page that will need
     * twig processing handled differently from a regular page.
     *
     * @param  bool|null $var true if modular_twig
     * @return bool      true if modular_twig
     */
    public function modularTwig($var = null);

    /**
     * Returns children of this page.
     *
     * @return PageCollectionInterface|Collection
     */
    public function children();

    /**
     * Check to see if this item is the first in an array of sub-pages.
     *
     * @return bool True if item is first.
     */
    public function isFirst();

    /**
     * Check to see if this item is the last in an array of sub-pages.
     *
     * @return bool True if item is last
     */
    public function isLast();

    /**
     * Gets the previous sibling based on current position.
     *
     * @return PageInterface the previous Page item
     */
    public function prevSibling();

    /**
     * Gets the next sibling based on current position.
     *
     * @return PageInterface the next Page item
     */
    public function nextSibling();

    /**
     * Returns the adjacent sibling based on a direction.
     *
     * @param  int $direction either -1 or +1
     * @return PageInterface|false             the sibling page
     */
    public function adjacentSibling($direction = 1);

    /**
     * Helper method to return an ancestor page.
     *
     * @param bool|null $lookup Name of the parent folder
     * @return PageInterface page you were looking for if it exists
     */
    public function ancestor($lookup = null);

    /**
     * Helper method to return an ancestor page to inherit from. The current
     * page object is returned.
     *
     * @param string $field Name of the parent folder
     * @return PageInterface
     */
    public function inherited($field);

    /**
     * Helper method to return an ancestor field only to inherit from. The
     * first occurrence of an ancestor field will be returned if at all.
     *
     * @param string $field Name of the parent folder
     * @return array
     */
    public function inheritedField($field);

    /**
     * Helper method to return a page.
     *
     * @param string $url the url of the page
     * @param bool $all
     * @return PageInterface page you were looking for if it exists
     */
    public function find($url, $all = false);

    /**
     * Get a collection of pages in the current context.
     *
     * @param string|array $params
     * @param bool $pagination
     * @return Collection
     * @throws InvalidArgumentException
     */
    public function collection($params = 'content', $pagination = true);

    /**
     * @param string|array $value
     * @param bool $only_published
     * @return PageCollectionInterface|Collection
     */
    public function evaluate($value, $only_published = true);

    /**
     * Returns whether or not the current folder exists
     *
     * @return bool
     */
    public function folderExists();

    /**
     * Gets the Page Unmodified (original) version of the page.
     *
     * @return PageInterface The original version of the page.
     */
    public function getOriginal();

    /**
     * Gets the action.
     *
     * @return string The Action string.
     */
    public function getAction();
}
