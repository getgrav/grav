<?php
namespace Grav\Common\Page;

use Grav\Common\Filesystem\Folder;
use Grav\Common\Config\Config;
use Grav\Common\GravTrait;
use Grav\Common\Utils;
use Grav\Common\Cache;
use Grav\Common\Twig;
use Grav\Common\Uri;
use Grav\Common\Grav;
use Grav\Common\Taxonomy;
use Grav\Common\Markdown\Parsedown;
use Grav\Common\Markdown\ParsedownExtra;
use Grav\Common\Data\Blueprint;
use RocketTheme\Toolbox\Event\Event;
use RocketTheme\Toolbox\File\MarkdownFile;
use Symfony\Component\Yaml\Yaml;

/**
 * The Page object, or "Page" object is the main powerhouse of Grav.  It contains all the information
 * related to the nested pages structure that represents the content. Each page has several attributes that
 * can be retrieved via public functions. Also each page can potentially contain an array of sub-pages.
 * Recursively traversing the page structure allows Grav to create navigation systems.
 *
 * @author RocketTheme
 * @license MIT
 */
class Page
{
    use GravTrait;

    /**
     * @var string Filename. Leave as null if page is folder.
     */
    protected $name;

    /**
     * @var string Folder name.
     */
    protected $folder;

    /**
     * @var string Path to the folder. Add $this->folder to get full path.
     */
    protected $path;

    protected $parent;
    protected $template;
    protected $expires;
    protected $visible;
    protected $published;
    protected $publish_date;
    protected $unpublish_date;
    protected $slug;
    protected $route;
    protected $routable;
    protected $modified;
    protected $id;
    protected $items;
    protected $header;
    protected $frontmatter;
    protected $content;
    protected $summary;
    protected $raw_content;
    protected $pagination;
    protected $media;
    protected $metadata;
    protected $title;
    protected $max_count;
    protected $menu;
    protected $date;
    protected $taxonomy;
    protected $order_by;
    protected $order_dir;
    protected $order_manual;
    protected $modular;
    protected $modular_twig;
    protected $process;
    protected $summary_size;
    protected $markdown_extra;
    protected $etag;
    protected $last_modified;

    /**
     * @var Page Unmodified (original) version of the page. Used for copying and moving the page.
     */
    private $_original;

    /**
     * @var string Action
     */
    private $_action;

    /**
     * Page Object Constructor
     */
    public function __construct()
    {
        /** @var Config $config */
        $config = self::getGrav()['config'];

        $this->routable = true;
        $this->taxonomy = array();
        $this->process = $config->get('system.pages.process');
        $this->published = true;
    }

    /**
     * Initializes the page instance variables based on a file
     *
     * @param  \SplFileInfo $file The file information for the .md file that the page represents
     * @return void
     */
    public function init(\SplFileInfo $file)
    {
        $this->filePath($file->getPathName());
        $this->modified($file->getMTime());
        $this->id($this->modified().md5($this->filePath()));
        $this->header();
        $this->date();
        $this->metadata();
        $this->slug();
        $this->visible();
        $this->modularTwig($this->slug[0] == '_');

         // Handle publishing dates if no explict published option set
        if (self::getGrav()['config']->get('system.pages.publish_dates') && !isset($this->header->published)) {
            // unpublish if required, if not clear cache right before page should be unpublished
            if ($this->unpublishDate()) {
                if ($this->unpublishDate() < time()) {
                    $this->published(false);
                } else {
                    $this->published();
                    self::getGrav()['cache']->setLifeTime($this->unpublishDate());
                }
            }
            // publish if required, if not clear cache right before page is published
            if ($this->publishDate() != $this->modified() && $this->publishDate() > time()) {
                $this->published(false);
                self::getGrav()['cache']->setLifeTime($this->publishDate());
            }
        }
        $this->published();
    }

    /**
     * Gets and Sets the raw data
     *
     * @param  string $var Raw content string
     * @return Object      Raw content string
     */
    public function raw($var = null)
    {
        $file = $this->file();

        if ($var) {
            // First update file object.
            if ($file) {
                $file->raw($var);
            }

            // Reset header and content.
            $this->modified = time();
            $this->id($this->modified().md5($this->filePath()));
            $this->header = null;
            $this->content = null;
            $this->summary = null;
        }
        return $file ? $file->raw() : '';
    }

    public function frontmatter($var = null)
    {

        if ($var) {
            $this->frontmatter = (string) $var;

            // Update also file object.
            $file = $this->file();
            if ($file) {
                $file->frontmatter((string) $var);
            }

            // Force content re-processing.
            $this->id(time().md5($this->filePath()));
        }
        if (!$this->frontmatter) {
            $this->header();
        }
        return $this->frontmatter;
    }

    /**
     * Gets and Sets the header based on the YAML configuration at the top of the .md file
     *
     * @param  object|array $var a YAML object representing the configuration for the file
     * @return object      the current YAML configuration
     */
    public function header($var = null)
    {
        if ($var) {
            $this->header = (object) $var;

            // Update also file object.
            $file = $this->file();
            if ($file) {
                $file->header((array) $var);
            }

            // Force content re-processing.
            $this->id(time().md5($this->filePath()));
        }
        if (!$this->header) {
            $file = $this->file();
            if ($file) {
                $this->raw_content = $file->markdown();
                $this->frontmatter = $file->frontmatter();
                $this->header = (object) $file->header();

                $var = true;
            }
        }

        if ($var) {
            if (isset($this->header->slug)) {
                $this->slug = trim($this->header->slug);
            }
            if (isset($this->header->title)) {
                $this->title = trim($this->header->title);
            }
            if (isset($this->header->template)) {
                $this->template = trim($this->header->template);
            }
            if (isset($this->header->menu)) {
                $this->menu = trim($this->header->menu);
            }
            if (isset($this->header->routable)) {
                $this->routable = $this->header->routable;
            }
            if (isset($this->header->visible)) {
                $this->visible = $this->header->visible;
            }
            if (isset($this->header->order_dir)) {
                $this->order_dir = trim($this->header->order_dir);
            }
            if (isset($this->header->order_by)) {
                $this->order_by = trim($this->header->order_by);
            }
            if (isset($this->header->order_manual)) {
                $this->order_manual = (array)$this->header->order_manual;
            }
            if (isset($this->header->date)) {
                $this->date = strtotime($this->header->date);
            }
            if (isset($this->header->markdown_extra)) {
                $this->markdown_extra = (bool)$this->header->markdown_extra;
            }
            if (isset($this->header->taxonomy)) {
                foreach ($this->header->taxonomy as $taxonomy => $taxitems) {
                    $this->taxonomy[$taxonomy] = (array)$taxitems;
                }
            }
            if (isset($this->header->max_count)) {
                $this->max_count = intval($this->header->max_count);
            }
            if (isset($this->header->process)) {
                foreach ($this->header->process as $process => $status) {
                    $this->process[$process] = $status;
                }
            }
            if (isset($this->header->published)) {
                $this->published = $this->header->published;
            }
            if (isset($this->header->publish_date)) {
                $this->publish_date = strtotime($this->header->publish_date);
            }
            if (isset($this->header->unpublish_date)) {
                $this->unpublish_date = strtotime($this->header->unpublish_date);
            }
            if (isset($this->header->expires)) {
                $this->expires = intval($this->header->expires);
            }
            if (isset($this->header->etag)) {
                $this->etag = (bool)$this->header->etag;
            }
            if (isset($this->header->last_modified)) {
                $this->last_modified = (bool)$this->header->last_modified;
            }

        }

        return $this->header;
    }

    /**
     * Modify a header value directly
     *
     * @param $key
     * @param $value
     */
    public function modifyHeader($key, $value)
    {
        $this->header->$key = $value;
    }

    /**
     * Get the summary.
     *
     * @param  int    $size Max summary size.
     * @return string
     */
    public function summary($size = null)
    {
        /** @var Config $config */
        $config = self::getGrav()['config']->get('site.summary');
        if (isset($this->header->summary)) {
            $config = array_merge($config, $this->header->summary);
        }

        // Return summary based on settings in site config file
        if (!$config['enabled']) {
            return $content;
        }

        // Set up variables to process summary from page or from custom summary
        if ($this->summary === null) {
            $content = $this->content();
            $summary_size = $this->summary_size;
        } else {
            $content = $this->summary;
            $summary_size = mb_strlen($this->summary);
        }

        // Return calculated summary based on summary divider's position
        $format = $config['format'];
        // Return entire page content on wrong/ unknown format
        if (!in_array($format, array('short', 'long'))) {
            return $content;
        } elseif (($format === 'short') && isset($summary_size)) {
            return mb_substr($content, 0, $summary_size);
        }

        // Get summary size from site config's file
        if (is_null($size)) {
            $size = $config['size'];
        }

        // If the size is zero, return the entire page content
        if ($size === 0) {
            return $content;
        // Return calculated summary based on defaults
        } elseif (!is_numeric($size) || ($size < 0)) {
            $size = 300;
        }

        return Utils::truncateHTML($content, $size);
    }

    /**
     * Sets the summary of the page
     *
     * @param string $var Summary
     */
    public function setSummary($summary)
    {
        $this->summary = $summary;
    }

    /**
     * Gets and Sets the content based on content portion of the .md file
     *
     * @param  string $var Content
     * @return string      Content
     */
    public function content($var = null)
    {
        if ($var !== null) {
            $this->raw_content = $var;

            // Update file object.
            $file = $this->file();
            if ($file) {
                $file->markdown($var);
            }

            // Force re-processing.
            $this->id(time().md5($this->filePath()));
            $this->content = null;
        }
        // If no content, process it
        if ($this->content === null) {
            // Get media
            $this->media();

            // Load cached content
            /** @var Cache $cache */
            $cache = self::getGrav()['cache'];
            $cache_id = md5('page'.$this->id());
            $this->content = $cache->fetch($cache_id);

            $process_markdown = $this->shouldProcess('markdown');
            $process_twig = $this->shouldProcess('twig');
            $cache_twig = isset($this->header->cache_enable) ? $this->header->cache_enable : true;
            $twig_first = isset($this->header->twig_first) ? $this->header->twig_first : false;
            $twig_already_processed = false;

            // if no cached-content run everything
            if ($this->content === false) {
                $this->content = $this->raw_content;
                self::getGrav()->fireEvent('onPageContentRaw', new Event(['page' => $this]));

                if ($twig_first) {
                    if ($process_twig) {
                        $this->processTwig();
                        $twig_already_processed = true;
                    }
                    if ($process_markdown) {
                        $this->processMarkdown();
                    }
                    if ($cache_twig) {
                        $this->cachePageContent();
                    }
                } else {
                    if ($process_markdown) {
                        $this->processMarkdown();
                    }
                    if (!$cache_twig) {
                        $this->cachePageContent();
                    }
                    if ($process_twig) {
                        $this->processTwig();
                        $twig_already_processed = true;
                    }
                    if ($cache_twig) {
                        $this->cachePageContent();
                    }
                }
            // content cached, but twig cache off
            }

            // only markdown content cached, process twig if required and not already processed
            if ($process_twig && !$cache_twig && !$twig_already_processed) {
                $this->processTwig();
            }

            // Handle summary divider
            $delimiter = self::getGrav()['config']->get('site.summary.delimiter', '===');
            $divider_pos = strpos($this->content, "<p>{$delimiter}</p>");
            if ($divider_pos !== false) {
                $this->summary_size = $divider_pos;
                $this->content = str_replace("<p>{$delimiter}</p>", '', $this->content);
            }

        }

        return $this->content;
    }

    /**
     * Process the Markdown content.  Uses Parsedown or Parsedown Extra depending on configuration
     */
    protected function processMarkdown()
    {
        /** @var Config $config */
        $config = self::getGrav()['config'];

        $defaults = (array) $config->get('system.pages.markdown');
        if (isset($this->header()->markdown)) {
            $defaults = array_merge($defaults, $this->header()->markdown);
        }

        // pages.markdown_extra is deprecated, but still check it...
        if (isset($this->markdown_extra) || $config->get('system.pages.markdown_extra') !== null) {
            $defaults['extra'] = $this->markdown_extra ?: $config->get('system.pages.markdown_extra');
        }

        // Initialize the preferred variant of Parsedown
        if ($defaults['extra']) {
            $parsedown = new ParsedownExtra($this, $defaults);
        } else {
            $parsedown = new Parsedown($this, $defaults);
        }

        $this->content = $parsedown->text($this->content);
    }


    /**
     * Process the Twig page content.
     */
    private function processTwig()
    {
        $twig = self::getGrav()['twig'];
        $this->content = $twig->processPage($this, $this->content);
    }

    /**
     * Fires the onPageContentProcessed event, and caches the page content using a unique ID for the page
     */
    private function cachePageContent()
    {
        $cache = self::getGrav()['cache'];
        $cache_id = md5('page'.$this->id());

        self::getGrav()->fireEvent('onPageContentProcessed', new Event(['page' => $this]));
        $cache->save($cache_id, $this->content);
    }

    /**
     * Needed by the onPageContentProcessed event to get the raw page content
     *
     * @return string   the current page content
     */
    public function getRawContent()
    {
        return $this->content;
    }

    /**
     * Needed by the onPageContentProcessed event to set the raw page content
     *
     * @param $content
     */
    public function setRawContent($content)
    {
        $this->content = $content;
    }

    /**
     * Get value from a page variable (used mostly for creating edit forms).
     *
     * @param  string  $name  Variable name.
     * @param mixed $default
     * @return mixed
     */
    public function value($name, $default = null)
    {
        if ($name == 'content') {
            return $this->raw_content;
        }
        if ($name == 'route') {
            return dirname($this->route());
        }
        if ($name == 'order') {
            $order = $this->order();
            return $order ? (int) $this->order() : '';
        }
        if ($name == 'folder') {
            $regex = '/^[0-9]+\./u';
            return preg_replace($regex, '', $this->folder);
        }
        if ($name == 'type') {
            return basename($this->name(), '.md');
        }
        if ($name == 'media') {
            return $this->media()->all();
        }
        if ($name == 'media.file') {
            return $this->media()->files();
        }
        if ($name == 'media.video') {
            return $this->media()->videos();
        }
        if ($name == 'media.image') {
            return $this->media()->images();
        }
        if ($name == 'media.audio') {
            return $this->media()->audios();
        }

        $path = explode('.', $name);
        $scope = array_shift($path);

        if ($name == 'frontmatter') {
            return $this->frontmatter;
        }

        if ($scope == 'header') {
            $current = $this->header();
            foreach ($path as $field) {
                if (is_object($current) && isset($current->{$field})) {
                    $current = $current->{$field};
                } elseif (is_array($current) && isset($current[$field])) {
                    $current = $current[$field];
                } else {
                    return $default;
                }
            }

            return $current;
        }

        return $default;
    }

    public function rawMarkdown($var = null)
    {
        if ($var !== null) {
            $this->raw_content = $var;
        }

        return $this->raw_content;
    }

    /**
     * Get file object to the page.
     *
     * @return MarkdownFile|null
     */
    public function file()
    {
        if ($this->name) {
            // TODO: use CompiledMarkdownFile after fixing issues in it.
            return MarkdownFile::instance($this->filePath());
        }
        return null;
    }

    /**
     * Save page if there's a file assigned to it.
     * @param bool $reorder Internal use.
     */
    public function save($reorder = true)
    {
        // Perform move, copy or reordering if needed.
        $this->doRelocation($reorder);

        $file = $this->file();
        if ($file) {
            $file->filename($this->filePath());
            $file->header((array) $this->header());
            $file->markdown($this->raw_content);
            $file->save();
        }
    }

    /**
     * Prepare move page to new location. Moves also everything that's under the current page.
     *
     * You need to call $this->save() in order to perform the move.
     *
     * @param Page $parent New parent page.
     * @return Page
     */
    public function move(Page $parent)
    {
        $clone = clone $this;
        $clone->_action = 'move';
        $clone->_original = $this;
        $clone->parent($parent);
        $clone->id(time().md5($clone->filePath()));
        // TODO: make sure that the path is in user context.
        if ($parent->path()) {
            $clone->path($parent->path() . '/' . $clone->folder());
        }
        // TODO: make sure we always have the route.
        if ($parent->route()) {
            $clone->route($parent->route() . '/'. $clone->slug());
        }

        return $clone;
    }

    /**
     * Prepare a copy from the page. Copies also everything that's under the current page.
     *
     * Returns a new Page object for the copy.
     * You need to call $this->save() in order to perform the move.
     *
     * @param Page $parent New parent page.
     * @return Page
     */
    public function copy($parent)
    {
        $clone = $this->move($parent);
        $clone->_action = 'copy';

        return $clone;
    }

    /**
     * Get blueprints for the page.
     *
     * @return Blueprint
     */
    public function blueprints()
    {
        /** @var Pages $pages */
        $pages = self::getGrav()['pages'];

        return $pages->blueprints($this->template());
    }

    /**
     * Validate page header.
     *
     * @throws \Exception
     */
    public function validate()
    {
        $blueprints = $this->blueprints();
        $blueprints->validate($this->toArray());
    }

    /**
     * Filter page header from illegal contents.
     */
    public function filter()
    {
        $blueprints = $this->blueprints();
        $values = $blueprints->filter($this->toArray());
        $this->header($values['header']);
    }

    /**
     * Get unknown header variables.
     *
     * @return array
     */
    public function extra()
    {
        $blueprints = $this->blueprints();
        return $blueprints->extra($this->toArray(), 'header.');
    }

    /**
     * Convert page to an array.
     *
     * @return array
     */
    public function toArray()
    {
        return array(
            'header' => (array) $this->header(),
            'content' => (string) $this->value('content')
        );
    }

    /**
     * Convert page to YAML encoded string.
     *
     * @return string
     */
    public function toYaml()
    {
        return Yaml::dump($this->toArray(), 10);
    }

    /**
     * Convert page to JSON encoded string.
     *
     * @return string
     */
    public function toJson()
    {
        return json_encode($this->toArray());
    }

    /**
     * Gets and sets the associated media as found in the page folder.
     *
     * @param  Media $var Representation of associated media.
     * @return Media      Representation of associated media.
     */
    public function media($var = null)
    {
        /** @var Cache $cache */
        $cache = self::getGrav()['cache'];

        if ($var) {
            $this->media = $var;
        }
        if ($this->media === null) {
            // Use cached media if possible.
            $media_cache_id = md5('media'.$this->id());
            if (!$media = $cache->fetch($media_cache_id)) {
                $media = new Media($this->path());
                $cache->save($media_cache_id, $media);
            }
            $this->media = $media;
        }
        return $this->media;
    }

    /**
     * Gets and sets the name field.  If no name field is set, it will return 'default.md'.
     *
     * @param  string $var The name of this page.
     * @return string      The name of this page.
     */
    public function name($var = null)
    {
        if ($var !== null) {
            $this->name = $var;
        }
        return empty($this->name) ? 'default.md' : $this->name;
    }

    /**
     * Returns child page type.
     *
     * @return string
     */
    public function childType()
    {
        return isset($this->header->child_type) ? (string) $this->header->child_type : 'default';
    }

    /**
     * Gets and sets the template field. This is used to find the correct Twig template file to render.
     * If no field is set, it will return the name without the .md extension
     *
     * @param  string $var the template name
     * @return string      the template name
     */
    public function template($var = null)
    {
        if ($var !== null) {
            $this->template = $var;
        }
        if (empty($this->template)) {
            $this->template = ($this->modular() ? 'modular/' : '') . str_replace(CONTENT_EXT, '', $this->name());
        }
        return $this->template;
    }

    /**
     * Gets and sets the expires field. If not set will return the default
     *
     * @param  string $var The name of this page.
     * @return string      The name of this page.
     */
    public function expires($var = null)
    {
        if ($var !== null) {
            $this->expires = $var;
        }
        return empty($this->expires) ? self::getGrav()['config']->get('system.pages.expires') : $this->expires;
    }

    /**
     * Gets and sets the title for this Page.  If no title is set, it will use the slug() to get a name
     *
     * @param  string $var the title of the Page
     * @return string      the title of the Page
     */
    public function title($var = null)
    {
        if ($var !== null) {
            $this->title = $var;
        }
        if (empty($this->title)) {
            $this->title = ucfirst($this->slug());
        }
        return $this->title;
    }

    /**
     * Gets and sets the menu name for this Page.  This is the text that can be used specifically for navigation.
     * If no menu field is set, it will use the title()
     *
     * @param  string $var the menu field for the page
     * @return string      the menu field for the page
     */
    public function menu($var = null)
    {
        if ($var !== null) {
            $this->menu = $var;
        }
        if (empty($this->menu)) {
            $this->menu = $this->title();
        }
        return $this->menu;
    }

    /**
     * Gets and Sets whether or not this Page is visible for navigation
     *
     * @param  bool $var true if the page is visible
     * @return bool      true if the page is visible
     */
    public function visible($var = null)
    {
        if ($var !== null) {
            $this->visible = (bool) $var;
        }

        if ($this->visible === null) {
            // Set item visibility in menu if folder is different from slug
            // eg folder = 01.Home and slug = Home
            $regex = '/^[0-9]+\./u';
            if (preg_match($regex, $this->folder)) {
                $this->visible = true;
            } else {
                $this->visible = false;
            }
        }
        return $this->visible;
    }

    /**
     * Gets and Sets whether or not this Page is considered published
     *
     * @param  bool $var true if the page is published
     * @return bool      true if the page is published
     */
    public function published($var = null)
    {
        if ($var !== null) {
            $this->published = (bool) $var;
        }

        // If not published, should not be visible in menus either
        if ($this->published === false) {
            $this->visible = false;
        }

        return $this->published;
    }

    /**
     * Gets and Sets the Page publish date
     *
     * @param  string $var string representation of a date
     * @return int         unix timestamp representation of the date
     */
    public function publishDate($var = null)
    {
        if ($var !== null) {
            $this->publish_date = strtotime($var);
        }

        if ($this->publish_date === null) {
            $this->publish_date = $this->date();
        }

        return $this->publish_date;
    }

    /**
     * Gets and Sets the Page unpublish date
     *
     * @param  string $var string representation of a date
     * @return int|null         unix timestamp representation of the date
     */
    public function unpublishDate($var = null)
    {
        if ($var !== null) {
            $this->unpublish_date = strtotime($var);
        }

        return $this->unpublish_date;
    }

    /**
     * Gets and Sets whether or not this Page is routable, ie you can reach it
     * via a URL
     *
     * @param  bool $var true if the page is routable
     * @return bool      true if the page is routable
     */
    public function routable($var = null)
    {
        if ($var !== null) {
            $this->routable = (bool) $var;
        }
        return $this->routable;
    }

    /**
     * Gets and Sets the process setup for this Page. This is multi-dimensional array that consists of
     * a simple array of arrays with the form array("markdown"=>true) for example
     *
     * @param  array $var an Array of name value pairs where the name is the process and value is true or false
     * @return array      an Array of name value pairs where the name is the process and value is true or false
     */
    public function process($var = null)
    {
        if ($var !== null) {
            $this->process = (array) $var;
        }
        return $this->process;
    }

    /**
     * Function to merge page metadata tags and build an array of Metadata objects
     * that can then be rendered in the page.
     *
     * @param  array $var an Array of metadata values to set
     * @return array      an Array of metadata values for the page
     */
    public function metadata($var = null)
    {
        if ($var !== null) {
            $this->metadata = (array) $var;
        }

        // if not metadata yet, process it.
        if (null === $this->metadata) {
            $header_tag_http_equivs = ['content-type', 'default-style', 'refresh'];
            $this->metadata = array();
            $page_header = $this->header;

            // Set the Generator tag
            $this->metadata['generator'] = array('name'=>'generator', 'content'=>'Grav ' . GRAV_VERSION);

            // Safety check to ensure we have a header
            if ($page_header) {
                // Merge any site.metadata settings in with page metadata
                $defaults = (array) self::getGrav()['config']->get('site.metadata');

                if (isset($page_header->metadata)) {
                    $page_header->metadata = array_merge($defaults, $page_header->metadata);
                } else {
                    $page_header->metadata = $defaults;
                }

                // Build an array of meta objects..
                foreach ((array)$page_header->metadata as $key => $value) {
                    // If this is a property type metadata: "og", "twitter", "facebook" etc
                    if (is_array($value)) {
                        foreach ($value as $property => $prop_value) {
                            $prop_key =  $key.":".$property;
                            $this->metadata[$prop_key] = array('property'=>$prop_key, 'content'=>htmlspecialchars($prop_value, ENT_QUOTES));
                        }
                    // If it this is a standard meta data type
                    } else {
                        if (in_array($key, $header_tag_http_equivs)) {
                            $this->metadata[$key] = array('http_equiv'=>$key, 'content'=>htmlspecialchars($value, ENT_QUOTES));
                        } else {
                            $this->metadata[$key] = array('name'=>$key, 'content'=>htmlspecialchars($value, ENT_QUOTES));
                        }
                    }
                }
            }
        }

        return $this->metadata;
    }

    /**
     * Gets and Sets the slug for the Page. The slug is used in the URL routing. If not set it uses
     * the parent folder from the path
     *
     * @param  string $var the slug, e.g. 'my-blog'
     * @return string      the slug
     */
    public function slug($var = null)
    {
        if ($var !== null) {
            $this->slug = $var;
            $baseRoute = $this->parent ? (string) $this->parent()->route() : null;
            $this->route = isset($baseRoute) ? $baseRoute . '/'. $this->slug : null;
        }

        if (empty($this->slug)) {
            $regex = '/^[0-9]+\./u';
            $this->slug = preg_replace($regex, '', $this->folder);
            $baseRoute = $this->parent ? (string) $this->parent()->route() : null;
            $this->route = isset($baseRoute) ? $baseRoute . '/'. $this->slug : null;
        }
        return $this->slug;
    }

    /**
     * Get/set order number of this page.
     *
     * @param int $var
     * @return int|bool
     */
    public function order($var = null)
    {
        $regex = '/^[0-9]+\./u';
        if ($var !== null) {
            $order = !empty($var) ? sprintf('%02d.', (int) $var) : '';
            $slug = preg_replace($regex, '', $this->folder);
            $this->folder($order.$slug);
        }
        preg_match($regex, $this->folder, $order);
        return isset($order[0]) ? $order[0] : false;
    }

    /**
     * Gets the URL with host information, aka Permalink.
     * @return string The permalink.
     */
    public function permalink()
    {
        return $this->url(true);
    }

    /**
     * Gets the URL for a page - alias of url().
     *
     * @param bool $include_host
     * @return string the permalink
     */
    public function link($include_host = false)
    {
        return $this->url($include_host);
    }

    /**
     * Gets the url for the Page.
     *
     * @param  bool  $include_host  Defaults false, but true would include http://yourhost.com
     * @return string  The url.
     */
    public function url($include_host = false)
    {
        /** @var Pages $pages */
        $pages = self::getGrav()['pages'];

        /** @var Uri $uri */
        $uri = self::getGrav()['uri'];

        $rootUrl = $uri->rootUrl($include_host) . $pages->base();
        $url = $rootUrl.'/'.trim($this->route(), '/');

        // trim trailing / if not root
        if ($url !== '/') {
            $url = rtrim($url, '/');
        }

        return $url;
    }

    /**
     * Gets the route for the page based on the parents route and the current Page's slug.
     *
     * @param  string  $var  Set new default route.
     *
     * @return string  The route for the Page.
     */
    public function route($var = null)
    {
        if ($var !== null) {
            $this->route = $var;
        }
        return $this->route;
    }

    /**
     * Gets and sets the identifier for this Page object.
     *
     * @param  string $var the identifier
     * @return string      the identifier
     */
    public function id($var = null)
    {
        if ($var !== null) {
            $this->id = $var;
        }
        return $this->id;
    }

    /**
     * Gets and sets the modified timestamp.
     *
     * @param  int $var modified unix timestamp
     * @return int      modified unix timestamp
     */
    public function modified($var = null)
    {
        if ($var !== null) {
            $this->modified = $var;
        }
        return $this->modified;
    }

    /**
     * Gets and sets the option to show the etag header for the page.
     *
     * @param  boolean $var show etag header
     * @return boolean      show etag header
     */
    public function eTag($var = null)
    {
        if ($var !== null) {
            $this->etag = $var;
        }
        if (!isset($this->etag)) {
            $this->etag = (bool) self::getGrav()['config']->get('system.pages.etag');
        }
        return $this->etag;
    }

    /**
     * Gets and sets the option to show the last_modified header for the page.
     *
     * @param  boolean $var show last_modified header
     * @return boolean      show last_modified header
     */
    public function lastModified($var = null)
    {
        if ($var !== null) {
            $this->last_modified = $var;
        }
        if (!isset($this->last_modified)) {
            $this->last_modified = (bool) self::getGrav()['config']->get('system.pages.last_modified');
        }
        return $this->last_modified;
    }

    /**
     * Gets and sets the path to the .md file for this Page object.
     *
     * @param  string $var the file path
     * @return string|null      the file path
     */
    public function filePath($var = null)
    {
        if ($var !== null) {
            // Filename of the page.
            $this->name = basename($var);
            // Folder of the page.
            $this->folder = basename(dirname($var));
            // Path to the page.
            $this->path = dirname(dirname($var));
        }
        return $this->path . '/' . $this->folder . '/' . ($this->name ?: '');
    }

    /**
     * Gets the relative path to the .md file
     *
     * @return string The relative file path
     */
    public function filePathClean()
    {
        return str_replace(ROOT_DIR, '', $this->filePath());
    }

    /**
     * Gets and sets the path to the folder where the .md for this Page object resides.
     * This is equivalent to the filePath but without the filename.
     *
     * @param  string $var the path
     * @return string|null      the path
     */
    public function path($var = null)
    {
        if ($var !== null) {
            // Folder of the page.
            $this->folder = basename($var);
            // Path to the page.
            $this->path = dirname($var);
        }
        return $this->path ? $this->path . '/' . $this->folder : null;
    }

    /**
     * Get/set the folder.
     *
     * @param string $var Optional path
     * @return string|null
     */
    public function folder($var = null)
    {
        if ($var !== null) {
            $this->folder = $var;
        }
        return $this->folder;
    }

    /**
     * Gets and sets the date for this Page object. This is typically passed in via the page headers
     *
     * @param  string $var string representation of a date
     * @return int         unix timestamp representation of the date
     */
    public function date($var = null)
    {
        if ($var !== null) {
            $this->date = strtotime($var);
        }

        if (!$this->date) {
            $this->date = $this->modified;
        }

        return $this->date;
    }

    /**
     * Gets and sets the order by which any sub-pages should be sorted.
     * @param  string $var the order, either "asc" or "desc"
     * @return string      the order, either "asc" or "desc"
     */
    public function orderDir($var = null)
    {
        if ($var !== null) {
            $this->order_dir = $var;
        }
        if (empty($this->order_dir)) {
            $this->order_dir = 'asc';
        }
        return $this->order_dir;
    }

    /**
     * Gets and sets the order by which the sub-pages should be sorted.
     *
     * default - is the order based on the file system, ie 01.Home before 02.Advark
     * title - is the order based on the title set in the pages
     * date - is the order based on the date set in the pages
     * folder - is the order based on the name of the folder with any numerics omitted
     *
     * @param  string $var supported options include "default", "title", "date", and "folder"
     * @return string      supported options include "default", "title", "date", and "folder"
     */
    public function orderBy($var = null)
    {
        if ($var !== null) {
            $this->order_by = $var;
        }
        return $this->order_by;
    }

    /**
     * Gets the manual order set in the header.
     *
     * @param  string $var supported options include "default", "title", "date", and "folder"
     * @return array
     */
    public function orderManual($var = null)
    {
        if ($var !== null) {
            $this->order_manual = $var;
        }
        return (array) $this->order_manual;
    }

    /**
     * Gets and sets the maxCount field which describes how many sub-pages should be displayed if the
     * sub_pages header property is set for this page object.
     *
     * @param  int $var the maximum number of sub-pages
     * @return int      the maximum number of sub-pages
     */
    public function maxCount($var = null)
    {
        if ($var !== null) {
            $this->max_count = (int) $var;
        }
        if (empty($this->max_count)) {
            /** @var Config $config */
            $config = self::getGrav()['config'];
            $this->max_count = (int) $config->get('system.pages.list.count');
        }
        return $this->max_count;
    }

    /**
     * Gets and sets the taxonomy array which defines which taxonomies this page identifies itself with.
     *
     * @param  array $var an array of taxonomies
     * @return array      an array of taxonomies
     */
    public function taxonomy($var = null)
    {
        if ($var !== null) {
            $this->taxonomy = $var;
        }
        return $this->taxonomy;
    }

     /**
     * Gets and sets the modular var that helps identify this parent page contains modular pages.
     *
     * @param  bool $var true if modular_twig
     * @return bool      true if modular_twig
     */
    public function modular($var = null)
    {
        return $this->modularTwig($var);
    }

    /**
     * Gets and sets the modular_twig var that helps identify this page as a modular page that will need
     * twig processing handled differently from a regular page.
     *
     * @param  bool $var true if modular_twig
     * @return bool      true if modular_twig
     */
    public function modularTwig($var = null)
    {
        if ($var !== null) {
            $this->modular_twig = (bool) $var;
            if ($var) {
                $this->process['twig'] = true;
                $this->visible(false);
            }
        }
        return $this->modular_twig;
    }

    /**
     * Gets the configured state of the processing method.
     *
     * @param  string $process the process, eg "twig" or "markdown"
     * @return bool            whether or not the processing method is enabled for this Page
     */
    public function shouldProcess($process)
    {
        return isset($this->process[$process]) ? (bool) $this->process[$process] : false;
    }

    /**
     * Gets and Sets the parent object for this page
     *
     * @param  Page $var the parent page object
     * @return Page|null the parent page object if it exists.
     */
    public function parent(Page $var = null)
    {
        if ($var) {
            $this->parent = $var->path();
            return $var;
        }

        /** @var Pages $pages */
        $pages = self::getGrav()['pages'];

        return $pages->get($this->parent);
    }

    /**
     * Returns children of this page.
     *
     * @return \Grav\Common\Page\Collection
     */
    public function children()
    {
        /** @var Pages $pages */
        $pages = self::getGrav()['pages'];
        return $pages->children($this->path());
    }


    /**
     * Check to see if this item is the first in an array of sub-pages.
     *
     * @return boolean True if item is first.
     */
    public function isFirst()
    {
        $collection = $this->parent()->collection('content', false);
        return $collection->isFirst($this->path());
    }

    /**
     * Check to see if this item is the last in an array of sub-pages.
     *
     * @return boolean True if item is last
     */
    public function isLast()
    {
        $collection = $this->parent()->collection('content', false);
        return $collection->isLast($this->path());
    }

    /**
     * Gets the previous sibling based on current position.
     *
     * @return Page the previous Page item
     */
    public function prevSibling()
    {
        return $this->adjacentSibling(-1);
    }

    /**
     * Gets the next sibling based on current position.
     *
     * @return Page the next Page item
     */
    public function nextSibling()
    {
        return $this->adjacentSibling(1);
    }

    /**
     * Returns the adjacent sibling based on a direction.
     *
     * @param  integer $direction either -1 or +1
     * @return Page             the sibling page
     */
    public function adjacentSibling($direction = 1)
    {
        $collection = $this->parent()->collection('content', false);
        return $collection->adjacentSibling($this->path(), $direction);
    }

    /**
     * Returns whether or not this page is the currently active page requested via the URL.
     *
     * @return bool True if it is active
     */
    public function active()
    {
        /** @var Uri $uri */
        $uri = self::getGrav()['uri'];
        if ($this->url() == $uri->url()) {
            return true;
        }
        return false;
    }

    /**
     * Returns whether or not this URI's URL contains the URL of the active page.
     * Or in other words, is this page's URL in the current URL
     *
     * @return bool True if active child exists
     */
    public function activeChild()
    {
        /** @var Uri $uri */
        $uri = self::getGrav()['uri'];
        $config = self::getGrav()['config'];

        // Special check when item is home
        if ($this->home()) {
            $paths = $uri->paths();
            $home = ltrim($config->get('system.home.alias'), '/');
            if (isset($paths[0]) && $paths[0] == $home) {
                return true;
            }
        } else {
            if (strpos($uri->url(), $this->url()) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns whether or not this page is the currently configured home page.
     *
     * @return bool True if it is the homepage
     */
    public function home()
    {
        return $this->find('/') == $this;
    }

    /**
     * Returns whether or not this page is the root node of the pages tree.
     *
     * @return bool True if it is the root
     */
    public function root()
    {
        if (!$this->parent && !$this->name && !$this->visible) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Helper method to return a page.
     *
     * @param  string $url the url of the page
     * @param bool    $all
     *
     * @return \Grav\Common\Page\Page page you were looking for if it exists
     * @deprecated
     */
    public function find($url, $all = false)
    {
        /** @var Pages $pages */
        $pages = self::getGrav()['pages'];
        return $pages->dispatch($url, $all);
    }

    /**
     * Get a collection of pages in the current context.
     *
     * @param string|array $params
     * @param boolean $pagination
     * @return Collection
     * @throws \InvalidArgumentException
     */
    public function collection($params = 'content', $pagination = true)
    {
        if (is_string($params)) {
            $params = (array) $this->value('header.'.$params);
        } elseif (!is_array($params)) {
            throw new \InvalidArgumentException('Argument should be either header variable name or array of parameters');
        }

        if (!isset($params['items'])) {
            return array();
        }

        $collection = $this->evaluate($params['items']);
        if (!$collection instanceof Collection) {
            $collection = new Collection();
        }
        $collection->setParams($params);

        // TODO: MOVE THIS INTO SOMEWHERE ELSE?
        /** @var Uri $uri */
        $uri = self::getGrav()['uri'];
        /** @var Config $config */
        $config = self::getGrav()['config'];

        foreach ((array) $config->get('site.taxonomies') as $taxonomy) {
            if ($uri->param($taxonomy)) {
                $items = explode(',', $uri->param($taxonomy));
                $collection->setParams(['taxonomies' => [$taxonomy => $items]]);

                foreach ($collection as $page) {
                    if ($page->modular()) {
                        continue;
                    }
                    foreach ($items as $item) {
                        if (empty($page->taxonomy[$taxonomy])
                            || !in_array($item, $page->taxonomy[$taxonomy])) {
                            $collection->remove();
                        }
                    }
                }
            }
        }
        // TODO: END OF MOVE

        if (isset($params['dateRange'])) {
            $start = isset($params['dateRange']['start']) ? $params['dateRange']['start'] : 0;
            $end = isset($params['dateRange']['end']) ? $params['dateRange']['end'] : false;
            $collection->dateRange($start, $end);
        }

        if (isset($params['order'])) {
            $by = isset($params['order']['by']) ? $params['order']['by'] : 'default';
            $dir = isset($params['order']['dir']) ? $params['order']['dir'] : 'asc';
            $custom = isset($params['order']['custom']) ? $params['order']['custom'] : null;
            $collection->order($by, $dir, $custom);
        }

        /** @var Grav $grav */
        $grav = self::getGrav()['grav'];

        // New Custom event to handle things like pagination.
        $grav->fireEvent('onCollectionProcessed', new Event(['collection' => $collection]));

        // Slice and dice the collection if pagination is required
        if ($pagination) {
            $params = $collection->params();

            $limit = isset($params['limit']) ? $params['limit'] : 0;
            $start = !empty($params['pagination']) ? ($uri->currentPage() - 1) * $limit : 0;

            if ($limit && $collection->count() > $limit) {
                $collection->slice($start, $limit);
            }
        }

        return $collection;
    }

    /**
     * @param string $value
     *
     * @return mixed
     * @internal
     */
    protected function evaluate($value)
    {
        // Parse command.
        if (is_string($value)) {
            // Format: @command.param
            $cmd = $value;
            $params = array();
        } elseif (is_array($value) && count($value) == 1) {
            // Format: @command.param: { attr1: value1, attr2: value2 }
            $cmd = (string) key($value);
            $params = (array) current($value);
        } else {
            return $value;
        }

        // We only evaluate commands which start with @
        if (empty($cmd) || $cmd[0] != '@') {
            return $value;
        }

        $parts = explode('.', $cmd);
        $current = array_shift($parts);

        $results = null;
        switch ($current) {
            case '@self':
                if (!empty($parts)) {
                    switch ($parts[0]) {
                        case 'modular':
                            $results = $this->children()->modular()->published();
                            break;
                        case 'children':
                            $results = $this->children()->nonModular()->published();
                            break;
                    }
                }
                break;

            case '@page':
                if (!empty($params)) {
                    $page = $this->find($params[0]);
                    if ($page) {
                        $results = $page->children()->nonModular()->published();
                    }
                }
                break;

            case '@taxonomy':
                // Gets a collection of pages by using one of the following formats:
                // @taxonomy.category: blog
                // @taxonomy.category: [ blog, featured ]
                // @taxonomy: { category: [ blog, featured ], level: 1 }

                /** @var Taxonomy $taxonomy_map */
                $taxonomy_map = self::getGrav()['taxonomy'];

                if (!empty($parts)) {
                    $params = [implode('.', $parts) => $params];
                }
                $results = $taxonomy_map->findTaxonomy($params);
                break;
        }

        return $results;
    }


    /**
     * Returns whether or not this Page object has a .md file associated with it or if its just a directory.
     *
     * @return bool True if its a page with a .md file associated
     */
    public function isPage()
    {
        if ($this->name) {
            return true;
        }
        return false;
    }

    /**
     * Returns whether or not this Page object is a directory or a page.
     *
     * @return bool True if its a directory
     */
    public function isDir()
    {
        return !$this->isPage();
    }

    /**
     * Returns whether the page exists in the filesystem.
     *
     * @return bool
     */
    public function exists()
    {
        $file = $this->file();
        return $file && $file->exists();
    }

    /**
     * Cleans the path.
     *
     * @param  string $path the path
     * @return string       the path
     */
    protected function cleanPath($path)
    {
        $lastchunk = strrchr($path, DS);
        if (strpos($lastchunk, ':') !== false) {
            $path = str_replace($lastchunk, '', $path);
        }
        return $path;
    }

    /**
     * Moves or copies the page in filesystem.
     *
     * @internal
     */
    protected function doRelocation($reorder)
    {
        if (empty($this->_original)) {
            return;
        }

        // Do reordering.
        if ($reorder && $this->order() != $this->_original->order()) {
            /** @var Pages $pages */
            $pages = self::getGrav()['pages'];

            $parent = $this->parent();

            // Extract visible children from the parent page.
            $list = array();
            /** @var Page $page */
            foreach ($parent->children()->visible() as $page) {
                if ($page->order()) {
                    $list[$page->slug] = $page->path();
                }
            }

            // If page was moved, take it out of the list.
            if ($this->_action == 'move') {
                unset($list[$this->slug()]);
            }

            $list = array_values($list);

            // Then add it back to the new location (if needed).
            if ($this->order()) {
                array_splice($list, min($this->order()-1, count($list)), 0, array($this->path()));
            }

            // Reorder all moved pages.
            foreach ($list as $order => $path) {
                if ($path == $this->path()) {
                    // Handle current page; we do want to change ordering number, but nothing else.
                    $this->order($order+1);
                } else {
                    // Handle all the other pages.
                    $page = $pages->get($path);

                    if ($page && $page->exists() && $page->order() != $order+1) {
                        $page = $page->move($parent);
                        $page->order($order+1);
                        $page->save(false);
                    }
                }
            }
        }
        if ($this->_action == 'move' && $this->_original->exists()) {
            Folder::move($this->_original->path(), $this->path());
        }
        if ($this->_action == 'copy' && $this->_original->exists()) {
            Folder::copy($this->_original->path(), $this->path());
        }

        if ($this->name() != $this->_original->name()) {
            $path = $this->path();
            if (is_file($path . '/' . $this->_original->name())) {
                rename($path . '/' . $this->_original->name(), $path . '/' . $this->name());
            }
        }

        $this->_action = null;
        $this->_original = null;
    }


}
