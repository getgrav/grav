<?php
/**
 * @package    Grav.Common.Page
 *
 * @copyright  Copyright (C) 2014 - 2016 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Page;

use Exception;
use Grav\Common\Cache;
use Grav\Common\Config\Config;
use Grav\Common\Data\Blueprint;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use Grav\Common\Language\Language;
use Grav\Common\Markdown\Parsedown;
use Grav\Common\Markdown\ParsedownExtra;
use Grav\Common\Taxonomy;
use Grav\Common\Uri;
use Grav\Common\Utils;
use RocketTheme\Toolbox\Event\Event;
use RocketTheme\Toolbox\File\MarkdownFile;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

define('PAGE_ORDER_PREFIX_REGEX', '/^[0-9]+\./u');

class Page
{
    /**
     * @var string Filename. Leave as null if page is folder.
     */
    protected $name;
    protected $folder;
    protected $path;
    protected $extension;
    protected $url_extension;

    protected $id;
    protected $parent;
    protected $template;
    protected $expires;
    protected $visible;
    protected $published;
    protected $publish_date;
    protected $unpublish_date;
    protected $slug;
    protected $route;
    protected $raw_route;
    protected $url;
    protected $routes;
    protected $routable;
    protected $modified;
    protected $redirect;
    protected $external_url;
    protected $items;
    protected $header;
    protected $frontmatter;
    protected $language;
    protected $content;
    protected $content_meta;
    protected $summary;
    protected $raw_content;
    protected $pagination;
    protected $media;
    protected $metadata;
    protected $title;
    protected $max_count;
    protected $menu;
    protected $date;
    protected $dateformat;
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
    protected $home_route;
    protected $hide_home_route;
    protected $ssl;
    protected $template_format;

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
        $config = Grav::instance()['config'];

        $this->taxonomy = [];
        $this->process = $config->get('system.pages.process');
        $this->published = true;
    }

    /**
     * Initializes the page instance variables based on a file
     *
     * @param  \SplFileInfo $file The file information for the .md file that the page represents
     * @param  string       $extension
     *
     * @return $this
     */
    public function init(\SplFileInfo $file, $extension = null)
    {
        $config = Grav::instance()['config'];

        $this->hide_home_route = $config->get('system.home.hide_in_urls', false);
        $this->home_route = $config->get('system.home.alias');
        $this->filePath($file->getPathName());
        $this->modified($file->getMTime());
        $this->id($this->modified() . md5($this->filePath()));
        $this->routable(true);
        $this->header();
        $this->date();
        $this->metadata();
        $this->url();
        $this->visible();
        $this->modularTwig($this->slug[0] == '_');
        $this->setPublishState();
        $this->published();
        $this->urlExtension();

        // some extension logic
        if (empty($extension)) {
            $this->extension('.' . $file->getExtension());
        } else {
            $this->extension($extension);
        }

        // extract page language from page extension
        $language = trim(basename($this->extension(), 'md'), '.') ?: null;
        $this->language($language);

        return $this;
    }

    protected function processFrontmatter()
    {
        // Quick check for twig output tags in frontmatter if enabled
        if (Utils::contains($this->frontmatter, '{{')) {
            $process_fields = $this->file()->header();
            $ignored_fields = [];
            foreach ((array)Grav::instance()['config']->get('system.pages.frontmatter.ignore_fields') as $field) {
                if (isset($process_fields[$field])) {
                    $ignored_fields[$field] = $process_fields[$field];
                    unset($process_fields[$field]);
                }
            }
            $text_header = Grav::instance()['twig']->processString(json_encode($process_fields), ['page' => $this]);
            $this->header((object)(json_decode($text_header, true) + $ignored_fields));
        }
    }

    /**
     * Return an array with the routes of other translated languages
     * @return array the page translated languages
     */
    public function translatedLanguages()
    {
        $filename = substr($this->name, 0, -(strlen($this->extension())));
        $config = Grav::instance()['config'];
        $languages = $config->get('system.languages.supported', []);
        $translatedLanguages = [];

        foreach ($languages as $language) {
            $path = $this->path . DS . $this->folder . DS . $filename . '.' . $language . '.md';
            if (file_exists($path)) {
                $aPage = new Page();
                $aPage->init(new \SplFileInfo($path), $language . '.md');

                $route = isset($aPage->header()->routes['default']) ? $aPage->header()->routes['default'] : $aPage->rawRoute();
                if (!$route) {
                    $route = $aPage->slug();
                }

                $translatedLanguages[$language] = $route;
            }
        }

        return $translatedLanguages;
    }

    /**
     * Return an array listing untranslated languages available
     * @return array the page untranslated languages
     */
    public function untranslatedLanguages()
    {
        $filename = substr($this->name, 0, -(strlen($this->extension())));
        $config = Grav::instance()['config'];
        $languages = $config->get('system.languages.supported', []);
        $untranslatedLanguages = [];

        foreach ($languages as $language) {
            $path = $this->path . DS . $this->folder . DS . $filename . '.' . $language . '.md';
            if (!file_exists($path)) {
                $untranslatedLanguages[] = $language;
            }
        }

        return $untranslatedLanguages;
    }

    /**
     * Gets and Sets the raw data
     *
     * @param  string $var Raw content string
     *
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
            $this->id($this->modified() . md5($this->filePath()));
            $this->header = null;
            $this->content = null;
            $this->summary = null;
        }

        return $file ? $file->raw() : '';
    }

    /**
     * Gets and Sets the page frontmatter
     *
     * @param string|null $var
     *
     * @return string
     */
    public function frontmatter($var = null)
    {

        if ($var) {
            $this->frontmatter = (string)$var;

            // Update also file object.
            $file = $this->file();
            if ($file) {
                $file->frontmatter((string)$var);
            }

            // Force content re-processing.
            $this->id(time() . md5($this->filePath()));
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
     *
     * @return object      the current YAML configuration
     */
    public function header($var = null)
    {
        if ($var) {
            $this->header = (object)$var;

            // Update also file object.
            $file = $this->file();
            if ($file) {
                $file->header((array)$var);
            }

            // Force content re-processing.
            $this->id(time() . md5($this->filePath()));
        }
        if (!$this->header) {
            $file = $this->file();
            if ($file) {
                // Set some options
                $file->settings(['native' => true, 'compat' => true]);
                try {
                    $this->raw_content = $file->markdown();
                    $this->frontmatter = $file->frontmatter();
                    $this->header = (object)$file->header();

                    if (!Utils::isAdminPlugin()) {
                        // Process frontmatter with Twig if enabled
                        if (Grav::instance()['config']->get('system.pages.frontmatter.process_twig') === true) {
                            $this->processFrontmatter();
                        }
                        // If there's a `frontmatter.yaml` file merge that in with the page header
                        // note page's own frontmatter has precedence and will overwrite any defaults
                        $frontmatter_file = $this->path . '/' . $this->folder . '/frontmatter.yaml';
                        if (file_exists($frontmatter_file)) {
                            $frontmatter_data = (array)Yaml::parse(file_get_contents($frontmatter_file));
                            $this->header = (object)array_replace_recursive($frontmatter_data, (array)$this->header);
                        }
                    }
                } catch (ParseException $e) {
                    $file->raw(Grav::instance()['language']->translate([
                        'FRONTMATTER_ERROR_PAGE',
                        $this->slug(),
                        $file->filename(),
                        $e->getMessage(),
                        $file->raw()
                    ]));
                    $this->raw_content = $file->markdown();
                    $this->frontmatter = $file->frontmatter();
                    $this->header = (object)$file->header();
                }
                $var = true;
            }


        }

        if ($var) {
            if (isset($this->header->slug)) {
                $this->slug(($this->header->slug));
            }
            if (isset($this->header->routes)) {
                $this->routes = (array)($this->header->routes);
            }
            if (isset($this->header->title)) {
                $this->title = trim($this->header->title);
            }
            if (isset($this->header->language)) {
                $this->language = trim($this->header->language);
            }
            if (isset($this->header->template)) {
                $this->template = trim($this->header->template);
            }
            if (isset($this->header->menu)) {
                $this->menu = trim($this->header->menu);
            }
            if (isset($this->header->routable)) {
                $this->routable = (bool)$this->header->routable;
            }
            if (isset($this->header->visible)) {
                $this->visible = (bool)$this->header->visible;
            }
            if (isset($this->header->redirect)) {
                $this->redirect = trim($this->header->redirect);
            }
            if (isset($this->header->external_url)) {
                $this->external_url = trim($this->header->external_url);
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
            if (isset($this->header->dateformat)) {
                $this->dateformat($this->header->dateformat);
            }
            if (isset($this->header->date)) {
                $this->date($this->header->date);
            }
            if (isset($this->header->markdown_extra)) {
                $this->markdown_extra = (bool)$this->header->markdown_extra;
            }
            if (isset($this->header->taxonomy)) {
                foreach ((array)$this->header->taxonomy as $taxonomy => $taxitems) {
                    $this->taxonomy[$taxonomy] = (array)$taxitems;
                }
            }
            if (isset($this->header->max_count)) {
                $this->max_count = intval($this->header->max_count);
            }
            if (isset($this->header->process)) {
                foreach ((array)$this->header->process as $process => $status) {
                    $this->process[$process] = (bool)$status;
                }
            }
            if (isset($this->header->published)) {
                $this->published = (bool)$this->header->published;
            }
            if (isset($this->header->publish_date)) {
                $this->publishDate($this->header->publish_date);
            }
            if (isset($this->header->unpublish_date)) {
                $this->unpublishDate($this->header->unpublish_date);
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
            if (isset($this->header->ssl)) {
                $this->ssl = (bool)$this->header->ssl;
            }
            if (isset($this->header->template_format)) {
                $this->template_format = $this->header->template_format;
            }
        }

        return $this->header;
    }

    /**
     * Get page language
     *
     * @param $var
     *
     * @return mixed
     */
    public function language($var = null)
    {
        if ($var !== null) {
            $this->language = $var;
        }

        return $this->language;
    }

    /**
     * Modify a header value directly
     *
     * @param $key
     * @param $value
     */
    public function modifyHeader($key, $value)
    {
        $this->header->{$key} = $value;
    }

    /**
     * Get the summary.
     *
     * @param  int $size Max summary size.
     *
     * @return string
     */
    public function summary($size = null)
    {
        $config = (array)Grav::instance()['config']->get('site.summary');
        if (isset($this->header->summary)) {
            $config = array_merge($config, $this->header->summary);
        }

        // Return summary based on settings in site config file
        if (!$config['enabled']) {
            return $this->content();
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
        if (!in_array($format, ['short', 'long'])) {
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

        $summary = Utils::truncateHTML($content, $size);

        return html_entity_decode($summary);
    }

    /**
     * Sets the summary of the page
     *
     * @param string $summary Summary
     */
    public function setSummary($summary)
    {
        $this->summary = $summary;
    }

    /**
     * Gets and Sets the content based on content portion of the .md file
     *
     * @param  string $var Content
     *
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
            $this->id(time() . md5($this->filePath()));
            $this->content = null;
        }
        // If no content, process it
        if ($this->content === null) {
            // Get media
            $this->media();

            /** @var Config $config */
            $config = Grav::instance()['config'];

            // Load cached content
            /** @var Cache $cache */
            $cache = Grav::instance()['cache'];
            $cache_id = md5('page' . $this->id());
            $content_obj = $cache->fetch($cache_id);

            if (is_array($content_obj)) {
                $this->content = $content_obj['content'];
                $this->content_meta = $content_obj['content_meta'];
            } else {
                $this->content = $content_obj;
            }


            $process_markdown = $this->shouldProcess('markdown');
            $process_twig = $this->shouldProcess('twig');
            $cache_enable = isset($this->header->cache_enable) ? $this->header->cache_enable : $config->get('system.cache.enabled',
                true);
            $twig_first = isset($this->header->twig_first) ? $this->header->twig_first : $config->get('system.pages.twig_first',
                true);


            // if no cached-content run everything
            if ($this->content === false || $cache_enable === false) {
                $this->content = $this->raw_content;
                Grav::instance()->fireEvent('onPageContentRaw', new Event(['page' => $this]));

                if ($twig_first) {
                    if ($process_twig) {
                        $this->processTwig();
                    }
                    if ($process_markdown) {
                        $this->processMarkdown();
                    }

                    // Content Processed but not cached yet
                    Grav::instance()->fireEvent('onPageContentProcessed', new Event(['page' => $this]));

                } else {
                    if ($process_markdown) {
                        $this->processMarkdown();
                    }

                    // Content Processed but not cached yet
                    Grav::instance()->fireEvent('onPageContentProcessed', new Event(['page' => $this]));

                    if ($process_twig) {
                        $this->processTwig();
                    }
                }

                if ($cache_enable) {
                    $this->cachePageContent();
                }
            }

            // Handle summary divider
            $delimiter = $config->get('site.summary.delimiter', '===');
            $divider_pos = mb_strpos($this->content, "<p>{$delimiter}</p>");
            if ($divider_pos !== false) {
                $this->summary_size = $divider_pos;
                $this->content = str_replace("<p>{$delimiter}</p>", '', $this->content);
            }

        }

        return $this->content;
    }

    /**
     * Get the contentMeta array and initialize content first if it's not already
     *
     * @return mixed
     */
    public function contentMeta()
    {
        if ($this->content === null) {
            $this->content();
        }

        return $this->getContentMeta();
    }

    /**
     * Add an entry to the page's contentMeta array
     *
     * @param $name
     * @param $value
     */
    public function addContentMeta($name, $value)
    {
        $this->content_meta[$name] = $value;
    }

    /**
     * Return the whole contentMeta array as it currently stands
     *
     * @param null $name
     *
     * @return mixed
     */
    public function getContentMeta($name = null)
    {
        if ($name) {
            if (isset($this->content_meta[$name])) {
                return $this->content_meta[$name];
            } else {
                return null;
            }

        }

        return $this->content_meta;
    }

    /**
     * Sets the whole content meta array in one shot
     *
     * @param $content_meta
     *
     * @return mixed
     */
    public function setContentMeta($content_meta)
    {
        return $this->content_meta = $content_meta;
    }

    /**
     * Process the Markdown content.  Uses Parsedown or Parsedown Extra depending on configuration
     */
    protected function processMarkdown()
    {
        /** @var Config $config */
        $config = Grav::instance()['config'];

        $defaults = (array)$config->get('system.pages.markdown');
        if (isset($this->header()->markdown)) {
            $defaults = array_merge($defaults, $this->header()->markdown);
        }

        // pages.markdown_extra is deprecated, but still check it...
        if (!isset($defaults['extra']) && (isset($this->markdown_extra) || $config->get('system.pages.markdown_extra') !== null)) {
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
        $twig = Grav::instance()['twig'];
        $this->content = $twig->processPage($this, $this->content);
    }

    /**
     * Fires the onPageContentProcessed event, and caches the page content using a unique ID for the page
     */
    public function cachePageContent()
    {
        $cache = Grav::instance()['cache'];
        $cache_id = md5('page' . $this->id());
        $cache->save($cache_id, ['content' => $this->content, 'content_meta' => $this->content_meta]);
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
     * @param string $name Variable name.
     * @param mixed  $default
     *
     * @return mixed
     */
    public function value($name, $default = null)
    {
        if ($name == 'content') {
            return $this->raw_content;
        }
        if ($name == 'route') {
            return $this->parent()->rawRoute();
        }
        if ($name == 'order') {
            $order = $this->order();

            return $order ? (int)$this->order() : '';
        }
        if ($name == 'ordering') {
            return (bool)$this->order();
        }
        if ($name == 'folder') {
            return preg_replace(PAGE_ORDER_PREFIX_REGEX, '', $this->folder);
        }
        if ($name == 'name') {
            $language = $this->language() ? '.' . $this->language() : '';
            $name_val = str_replace($language . '.md', '', $this->name());
            if ($this->modular()) {
                return 'modular/' . $name_val;
            }

            return $name_val;
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

    /**
     * Gets and Sets the Page raw content
     *
     * @param null $var
     *
     * @return null
     */
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
            return MarkdownFile::instance($this->filePath());
        }

        return null;
    }

    /**
     * Save page if there's a file assigned to it.
     *
     * @param bool $reorder Internal use.
     */
    public function save($reorder = true)
    {
        // Perform move, copy or reordering if needed.
        $this->doRelocation($reorder);

        $file = $this->file();
        if ($file) {
            $file->filename($this->filePath());
            $file->header((array)$this->header());
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
     *
     * @return $this
     */
    public function move(Page $parent)
    {
        if (!$this->_original) {
            $clone = clone $this;
            $this->_original = $clone;
        }

        $this->_action = 'move';
        $this->parent($parent);
        $this->id(time() . md5($this->filePath()));

        if ($parent->path()) {
            $this->path($parent->path() . '/' . $this->folder());
        }

        if ($parent->route()) {
            $this->route($parent->route() . '/' . $this->slug());
        } else {
            $this->route(Grav::instance()['pages']->root()->route() . '/' . $this->slug());
        }

        $this->raw_route = null;

        return $this;
    }

    /**
     * Prepare a copy from the page. Copies also everything that's under the current page.
     *
     * Returns a new Page object for the copy.
     * You need to call $this->save() in order to perform the move.
     *
     * @param Page $parent New parent page.
     *
     * @return $this
     */
    public function copy($parent)
    {
        $this->move($parent);
        $this->_action = 'copy';

        return $this;
    }

    /**
     * Get blueprints for the page.
     *
     * @return Blueprint
     */
    public function blueprints()
    {
        $grav = Grav::instance();

        /** @var Pages $pages */
        $pages = $grav['pages'];

        $blueprint = $pages->blueprints($this->blueprintName());
        $fields = $blueprint->fields();
        $edit_mode = isset($grav['admin']) ? $grav['config']->get('plugins.admin.edit_mode') : null;

        // override if you only want 'normal' mode
        if (empty($fields) && ($edit_mode == 'auto' || $edit_mode == 'normal')) {
            $blueprint = $pages->blueprints('default');
        }

        // override if you only want 'expert' mode
        if (!empty($fields) && $edit_mode == 'expert') {
            $blueprint = $pages->blueprints('');
        }

        return $blueprint;
    }

    /**
     * Get the blueprint name for this page.  Use the blueprint form field if set
     *
     * @return string
     */
    public function blueprintName()
    {
        $blueprint_name = filter_input(INPUT_POST, 'blueprint', FILTER_SANITIZE_STRING) ?: $this->template();

        return $blueprint_name;
    }

    /**
     * Validate page header.
     *
     * @throws Exception
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
        if ($values && isset($values['header'])) {
            $this->header($values['header']);
        }
    }

    /**
     * Get unknown header variables.
     *
     * @return array
     */
    public function extra()
    {
        $blueprints = $this->blueprints();

        return $blueprints->extra($this->toArray()['header'], 'header.');
    }

    /**
     * Convert page to an array.
     *
     * @return array
     */
    public function toArray()
    {
        return [
            'header'  => (array)$this->header(),
            'content' => (string)$this->value('content')
        ];
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
     *
     * @return Media      Representation of associated media.
     */
    public function media($var = null)
    {
        /** @var Cache $cache */
        $cache = Grav::instance()['cache'];

        if ($var) {
            $this->media = $var;
        }
        if ($this->media === null) {
            // Use cached media if possible.
            $media_cache_id = md5('media' . $this->id());
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
     *
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
        return isset($this->header->child_type) ? (string)$this->header->child_type : 'default';
    }

    /**
     * Gets and sets the template field. This is used to find the correct Twig template file to render.
     * If no field is set, it will return the name without the .md extension
     *
     * @param  string $var the template name
     *
     * @return string      the template name
     */
    public function template($var = null)
    {
        if ($var !== null) {
            $this->template = $var;
        }
        if (empty($this->template)) {
            $this->template = ($this->modular() ? 'modular/' : '') . str_replace($this->extension(), '', $this->name());
        }

        return $this->template;
    }

    /**
     * Allows a page to override the output render format, usually the extension provided
     * in the URL. (e.g. `html`, `json`, `xml`, etc).
     *
     * @param null $var
     *
     * @return null
     */
    public function templateFormat($var = null)
    {
        if ($var !== null) {
            $this->template_format = $var;
        }

        if (empty($this->template_format)) {
            $this->template_format = Grav::instance()['uri']->extension();
        }

        return $this->template_format;
    }

    /**
     * Gets and sets the extension field.
     *
     * @param null $var
     *
     * @return null|string
     */
    public function extension($var = null)
    {
        if ($var !== null) {
            $this->extension = $var;
        }
        if (empty($this->extension)) {
            $this->extension = '.' . pathinfo($this->name(), PATHINFO_EXTENSION);
        }

        return $this->extension;
    }

    /**
     * Returns the page extension, got from the page `url_extension` config and falls back to the
     * system config `system.pages.append_url_extension`.
     *
     * @return string      The extension of this page. For example `.html`
     */
    public function urlExtension()
    {
        if ($this->home()) {
            return '';
        }

        // if not set in the page get the value from system config
        if (empty($this->url_extension)) {
            $this->url_extension = trim(isset($this->header->append_url_extension) ? $this->header->append_url_extension : Grav::instance()['config']->get('system.pages.append_url_extension',
                false));
        }

        return $this->url_extension;
    }

    /**
     * Gets and sets the expires field. If not set will return the default
     *
     * @param  int $var The new expires value.
     *
     * @return int      The expires value
     */
    public function expires($var = null)
    {
        if ($var !== null) {
            $this->expires = $var;
        }

        return empty($this->expires) ? Grav::instance()['config']->get('system.pages.expires') : $this->expires;
    }

    /**
     * Gets and sets the title for this Page.  If no title is set, it will use the slug() to get a name
     *
     * @param  string $var the title of the Page
     *
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
     *
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
     *
     * @return bool      true if the page is visible
     */
    public function visible($var = null)
    {
        if ($var !== null) {
            $this->visible = (bool)$var;
        }

        if ($this->visible === null) {
            // Set item visibility in menu if folder is different from slug
            // eg folder = 01.Home and slug = Home
            if (preg_match(PAGE_ORDER_PREFIX_REGEX, $this->folder)) {
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
     *
     * @return bool      true if the page is published
     */
    public function published($var = null)
    {
        if ($var !== null) {
            $this->published = (bool)$var;
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
     *
     * @return int         unix timestamp representation of the date
     */
    public function publishDate($var = null)
    {
        if ($var !== null) {
            $this->publish_date = Utils::date2timestamp($var, $this->dateformat);
        }

        return $this->publish_date;
    }

    /**
     * Gets and Sets the Page unpublish date
     *
     * @param  string $var string representation of a date
     *
     * @return int|null         unix timestamp representation of the date
     */
    public function unpublishDate($var = null)
    {
        if ($var !== null) {
            $this->unpublish_date = Utils::date2timestamp($var, $this->dateformat);
        }

        return $this->unpublish_date;
    }

    /**
     * Gets and Sets whether or not this Page is routable, ie you can reach it
     * via a URL.
     * The page must be *routable* and *published*
     *
     * @param  bool $var true if the page is routable
     *
     * @return bool      true if the page is routable
     */
    public function routable($var = null)
    {
        if ($var !== null) {
            $this->routable = (bool)$var;
        }

        return $this->routable && $this->published();
    }

    public function ssl($var = null)
    {
        if ($var !== null) {
            $this->ssl = (bool)$var;
        }

        return $this->ssl;
    }

    /**
     * Gets and Sets the process setup for this Page. This is multi-dimensional array that consists of
     * a simple array of arrays with the form array("markdown"=>true) for example
     *
     * @param  array $var an Array of name value pairs where the name is the process and value is true or false
     *
     * @return array      an Array of name value pairs where the name is the process and value is true or false
     */
    public function process($var = null)
    {
        if ($var !== null) {
            $this->process = (array)$var;
        }

        return $this->process;
    }

    /**
     * Function to merge page metadata tags and build an array of Metadata objects
     * that can then be rendered in the page.
     *
     * @param  array $var an Array of metadata values to set
     *
     * @return array      an Array of metadata values for the page
     */
    public function metadata($var = null)
    {
        if ($var !== null) {
            $this->metadata = (array)$var;
        }

        // if not metadata yet, process it.
        if (null === $this->metadata) {
            $header_tag_http_equivs = ['content-type', 'default-style', 'refresh', 'x-ua-compatible'];

            $this->metadata = [];

            $metadata = [];
            // Set the Generator tag
            $metadata['generator'] = 'GravCMS';

            // Get initial metadata for the page
            $metadata = array_merge($metadata, Grav::instance()['config']->get('site.metadata'));

            if (isset($this->header->metadata)) {
                // Merge any site.metadata settings in with page metadata
                $metadata = array_merge($metadata, $this->header->metadata);
            }

            // Build an array of meta objects..
            foreach ((array)$metadata as $key => $value) {
                // If this is a property type metadata: "og", "twitter", "facebook" etc
                // Backward compatibility for nested arrays in metas
                if (is_array($value)) {
                    foreach ($value as $property => $prop_value) {
                        $prop_key = $key . ":" . $property;
                        $this->metadata[$prop_key] = [
                            'name'     => $prop_key,
                            'property' => $prop_key,
                            'content'  => htmlspecialchars($prop_value, ENT_QUOTES, 'UTF-8')
                        ];
                    }
                } else {
                    // If it this is a standard meta data type
                    if ($value) {
                        if (in_array($key, $header_tag_http_equivs)) {
                            $this->metadata[$key] = [
                                'http_equiv' => $key,
                                'content'    => htmlspecialchars($value, ENT_QUOTES, 'UTF-8')
                            ];
                        } elseif ($key == 'charset') {
                            $this->metadata[$key] = ['charset' => htmlspecialchars($value, ENT_QUOTES, 'UTF-8')];
                        } else {
                            // if it's a social metadata with separator, render as property
                            $separator = strpos($key, ':');
                            $hasSeparator = $separator && $separator < strlen($key) - 1;
                            $entry = ['name' => $key, 'content' => htmlspecialchars($value, ENT_QUOTES, 'UTF-8')];

                            if ($hasSeparator) {
                                $entry['property'] = $key;
                            }

                            $this->metadata[$key] = $entry;
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
     *
     * @return string      the slug
     */
    public function slug($var = null)
    {
        if ($var !== null && $var !== "") {
            $this->slug = $var;
            if (!preg_match('/^[a-z0-9][-a-z0-9]*$/', $this->slug)) {
                Grav::instance()['log']->notice("Invalid slug set in YAML frontmatter: " . $this->rawRoute() . " => " . $this->slug);
            }
        }

        if (empty($this->slug)) {
            $this->slug = strtolower(preg_replace(PAGE_ORDER_PREFIX_REGEX, '', $this->folder));
        }


        return $this->slug;
    }

    /**
     * Get/set order number of this page.
     *
     * @param int $var
     *
     * @return int|bool
     */
    public function order($var = null)
    {
        if ($var !== null) {
            $order = !empty($var) ? sprintf('%02d.', (int)$var) : '';
            $this->folder($order . $this->slug());
        }
        preg_match(PAGE_ORDER_PREFIX_REGEX, $this->folder, $order);

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
     *
     * @return string the permalink
     */
    public function link($include_host = false)
    {
        return $this->url($include_host);
    }

    /**
     * Gets the url for the Page.
     *
     * @param bool $include_host Defaults false, but true would include http://yourhost.com
     * @param bool $canonical    true to return the canonical URL
     * @param bool $include_lang
     *
     * @return string The url.
     */
    public function url($include_host = false, $canonical = false, $include_lang = true)
    {
        $grav = Grav::instance();

        /** @var Pages $pages */
        $pages = $grav['pages'];

        /** @var Config $config */
        $config = $grav['config'];

        /** @var Language $language */
        $language = $grav['language'];

        /** @var Uri $uri */
        $uri = $grav['uri'];

        // Override any URL when external_url is set
        if (isset($this->external_url)) {
            return $this->external_url;
        }

        // get pre-route
        if ($include_lang && $language->enabled()) {
            $pre_route = $language->getLanguageURLPrefix();
        } else {
            $pre_route = '';
        }

        // add full route if configured to do so
        if ($config->get('system.absolute_urls', false)) {
            $include_host = true;
        }

        // get canonical route if requested
        if ($canonical) {
            $route = $pre_route . $this->routeCanonical();
        } else {
            $route = $pre_route . $this->route();
        }

        $rootUrl = $uri->rootUrl($include_host) . $pages->base();

        $url = $rootUrl . '/' . trim($route, '/') . $this->urlExtension();

        // trim trailing / if not root
        if ($url !== '/') {
            $url = rtrim($url, '/');
        }

        return $url;
    }

    /**
     * Gets the route for the page based on the route headers if available, else from
     * the parents route and the current Page's slug.
     *
     * @param  string $var Set new default route.
     *
     * @return string  The route for the Page.
     */
    public function route($var = null)
    {
        if ($var !== null) {
            $this->route = $var;
        }

        if (empty($this->route)) {
            $baseRoute = null;

            // calculate route based on parent slugs
            $parent = $this->parent();
            if (isset($parent)) {
                if ($this->hide_home_route && $parent->route() == $this->home_route) {
                    $baseRoute = '';
                } else {
                    $baseRoute = (string)$parent->route();
                }
            }

            $this->route = isset($baseRoute) ? $baseRoute . '/' . $this->slug() : null;

            if (!empty($this->routes) && isset($this->routes['default'])) {
                $this->routes['aliases'][] = $this->route;
                $this->route = $this->routes['default'];

                return $this->route;
            }
        }

        return $this->route;
    }

    /**
     * Helper method to clear the route out so it regenerates next time you use it
     */
    public function unsetRouteSlug()
    {
        unset($this->route);
        unset($this->slug);
    }

    /**
     * Gets and Sets the page raw route
     *
     * @param null $var
     *
     * @return null|string
     */
    public function rawRoute($var = null)
    {
        if ($var !== null) {
            $this->raw_route = $var;
        }

        if (empty($this->raw_route)) {
            $baseRoute = $this->parent ? (string)$this->parent()->rawRoute() : null;

            $slug = preg_replace(PAGE_ORDER_PREFIX_REGEX, '', $this->folder);

            $this->raw_route = isset($baseRoute) ? $baseRoute . '/' . $slug : null;
        }

        return $this->raw_route;
    }

    /**
     * Gets the route aliases for the page based on page headers.
     *
     * @param  array $var list of route aliases
     *
     * @return array  The route aliases for the Page.
     */
    public function routeAliases($var = null)
    {
        if ($var !== null) {
            $this->routes['aliases'] = (array)$var;
        }

        if (!empty($this->routes) && isset($this->routes['aliases'])) {
            return $this->routes['aliases'];
        } else {
            return [];
        }
    }

    /**
     * Gets the canonical route for this page if its set. If provided it will use
     * that value, else if it's `true` it will use the default route.
     *
     * @param null $var
     *
     * @return bool|string
     */
    public function routeCanonical($var = null)
    {
        if ($var !== null) {
            $this->routes['canonical'] = (array)$var;
        }

        if (!empty($this->routes) && isset($this->routes['canonical'])) {
            return $this->routes['canonical'];
        }

        return $this->route();
    }

    /**
     * Gets and sets the identifier for this Page object.
     *
     * @param  string $var the identifier
     *
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
     *
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
     * Gets the redirect set in the header.
     *
     * @param  string $var redirect url
     *
     * @return array
     */
    public function redirect($var = null)
    {
        if ($var !== null) {
            $this->redirect = $var;
        }

        return $this->redirect;
    }

    /**
     * Gets and sets the option to show the etag header for the page.
     *
     * @param  boolean $var show etag header
     *
     * @return boolean      show etag header
     */
    public function eTag($var = null)
    {
        if ($var !== null) {
            $this->etag = $var;
        }
        if (!isset($this->etag)) {
            $this->etag = (bool)Grav::instance()['config']->get('system.pages.etag');
        }

        return $this->etag;
    }

    /**
     * Gets and sets the option to show the last_modified header for the page.
     *
     * @param  boolean $var show last_modified header
     *
     * @return boolean      show last_modified header
     */
    public function lastModified($var = null)
    {
        if ($var !== null) {
            $this->last_modified = $var;
        }
        if (!isset($this->last_modified)) {
            $this->last_modified = (bool)Grav::instance()['config']->get('system.pages.last_modified');
        }

        return $this->last_modified;
    }

    /**
     * Gets and sets the path to the .md file for this Page object.
     *
     * @param  string $var the file path
     *
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
        $path = str_replace(ROOT_DIR, '', $this->filePath());

        return $path;
    }

    /**
     * Returns the clean path to the page file
     */
    public function relativePagePath()
    {
        $path = str_replace('/' . $this->name(), '', $this->filePathClean());

        return $path;
    }

    /**
     * Gets and sets the path to the folder where the .md for this Page object resides.
     * This is equivalent to the filePath but without the filename.
     *
     * @param  string $var the path
     *
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
     *
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
     *
     * @return int         unix timestamp representation of the date
     */
    public function date($var = null)
    {
        if ($var !== null) {
            $this->date = Utils::date2timestamp($var, $this->dateformat);
        }

        if (!$this->date) {
            $this->date = $this->modified;
        }

        return $this->date;
    }

    /**
     * Gets and sets the date format for this Page object. This is typically passed in via the page headers
     * using typical PHP date string structure - http://php.net/manual/en/function.date.php
     *
     * @param  string $var string representation of a date format
     *
     * @return string      string representation of a date format
     */
    public function dateformat($var = null)
    {
        if ($var !== null) {
            $this->dateformat = $var;
        }

        return $this->dateformat;
    }

    /**
     * Gets and sets the order by which any sub-pages should be sorted.
     *
     * @param  string $var the order, either "asc" or "desc"
     *
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
     *
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
     *
     * @return array
     */
    public function orderManual($var = null)
    {
        if ($var !== null) {
            $this->order_manual = $var;
        }

        return (array)$this->order_manual;
    }

    /**
     * Gets and sets the maxCount field which describes how many sub-pages should be displayed if the
     * sub_pages header property is set for this page object.
     *
     * @param  int $var the maximum number of sub-pages
     *
     * @return int      the maximum number of sub-pages
     */
    public function maxCount($var = null)
    {
        if ($var !== null) {
            $this->max_count = (int)$var;
        }
        if (empty($this->max_count)) {
            /** @var Config $config */
            $config = Grav::instance()['config'];
            $this->max_count = (int)$config->get('system.pages.list.count');
        }

        return $this->max_count;
    }

    /**
     * Gets and sets the taxonomy array which defines which taxonomies this page identifies itself with.
     *
     * @param  array $var an array of taxonomies
     *
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
     * Gets and sets the modular var that helps identify this page is a modular child
     *
     * @param  bool $var true if modular_twig
     *
     * @return bool      true if modular_twig
     */
    public function modular($var = null)
    {
        return $this->modularTwig($var);
    }

    /**
     * Gets and sets the modular_twig var that helps identify this page as a modular child page that will need
     * twig processing handled differently from a regular page.
     *
     * @param  bool $var true if modular_twig
     *
     * @return bool      true if modular_twig
     */
    public function modularTwig($var = null)
    {
        if ($var !== null) {
            $this->modular_twig = (bool)$var;
            if ($var) {
                $this->process['twig'] = true;
                $this->visible(false);
                // some routable logic
                if (empty($this->header->routable)) {
                    $this->routable = false;
                }
            }
        }

        return $this->modular_twig;
    }

    /**
     * Gets the configured state of the processing method.
     *
     * @param  string $process the process, eg "twig" or "markdown"
     *
     * @return bool            whether or not the processing method is enabled for this Page
     */
    public function shouldProcess($process)
    {
        return isset($this->process[$process]) ? (bool)$this->process[$process] : false;
    }

    /**
     * Gets and Sets the parent object for this page
     *
     * @param  Page $var the parent page object
     *
     * @return Page|null the parent page object if it exists.
     */
    public function parent(Page $var = null)
    {
        if ($var) {
            $this->parent = $var->path();

            return $var;
        }

        /** @var Pages $pages */
        $pages = Grav::instance()['pages'];

        return $pages->get($this->parent);
    }

    /**
     * Gets the top parent object for this page
     *
     * @return Page|null the top parent page object if it exists.
     */
    public function topParent()
    {
        $topParent = $this->parent();

        if (!$topParent) {
            return null;
        }

        while (true) {
            $theParent = $topParent->parent();
            if ($theParent !== null && $theParent->parent() !== null) {
                $topParent = $theParent;
            } else {
                break;
            }
        }

        return $topParent;
    }

    /**
     * Returns children of this page.
     *
     * @return \Grav\Common\Page\Collection
     */
    public function children()
    {
        /** @var Pages $pages */
        $pages = Grav::instance()['pages'];

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
        if ($collection instanceof Collection) {
            return $collection->isFirst($this->path());
        }

        return true;
    }

    /**
     * Check to see if this item is the last in an array of sub-pages.
     *
     * @return boolean True if item is last
     */
    public function isLast()
    {
        $collection = $this->parent()->collection('content', false);
        if ($collection instanceof Collection) {
            return $collection->isLast($this->path());
        }

        return true;
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
     *
     * @return Page             the sibling page
     */
    public function adjacentSibling($direction = 1)
    {
        $collection = $this->parent()->collection('content', false);
        if ($collection instanceof Collection) {
            return $collection->adjacentSibling($this->path(), $direction);
        }

        return false;
    }

    /**
     * Returns whether or not this page is the currently active page requested via the URL.
     *
     * @return bool True if it is active
     */
    public function active()
    {
        $uri_path = rtrim(Grav::instance()['uri']->path(), '/') ?: '/';
        $routes = Grav::instance()['pages']->routes();

        if (isset($routes[$uri_path])) {
            if ($routes[$uri_path] == $this->path()) {
                return true;
            }

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
        $uri = Grav::instance()['uri'];
        $pages = Grav::instance()['pages'];
        $uri_path = rtrim($uri->path(), '/');
        $routes = Grav::instance()['pages']->routes();

        if (isset($routes[$uri_path])) {
            /** @var Page $child_page */
            $child_page = $pages->dispatch($uri->route())->parent();
            if ($child_page) {
                while (!$child_page->root()) {
                    if ($this->path() == $child_page->path()) {
                        return true;
                    }
                    $child_page = $child_page->parent();
                }
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
        $home = Grav::instance()['config']->get('system.home.alias');
        $is_home = ($this->route() == $home || $this->rawRoute() == $home);

        return $is_home;
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
     * @param string $url the url of the page
     * @param bool   $all
     *
     * @return \Grav\Common\Page\Page page you were looking for if it exists
     */
    public function find($url, $all = false)
    {
        /** @var Pages $pages */
        $pages = Grav::instance()['pages'];

        return $pages->find($url, $all);
    }

    /**
     * Get a collection of pages in the current context.
     *
     * @param string|array $params
     * @param boolean      $pagination
     *
     * @return Collection
     * @throws \InvalidArgumentException
     */
    public function collection($params = 'content', $pagination = true)
    {
        if (is_string($params)) {
            $params = (array)$this->value('header.' . $params);
        } elseif (!is_array($params)) {
            throw new \InvalidArgumentException('Argument should be either header variable name or array of parameters');
        }

        if (!isset($params['items'])) {
            return [];
        }

        $collection = $this->evaluate($params['items']);
        if (!$collection instanceof Collection) {
            $collection = new Collection();
        }
        $collection->setParams($params);

        /** @var Uri $uri */
        $uri = Grav::instance()['uri'];
        /** @var Config $config */
        $config = Grav::instance()['config'];

        $process_taxonomy = isset($params['url_taxonomy_filters']) ? $params['url_taxonomy_filters'] : $config->get('system.pages.url_taxonomy_filters');

        if ($process_taxonomy) {
            foreach ((array)$config->get('site.taxonomies') as $taxonomy) {
                if ($uri->param($taxonomy)) {
                    $items = explode(',', $uri->param($taxonomy));
                    $collection->setParams(['taxonomies' => [$taxonomy => $items]]);

                    foreach ($collection as $page) {
                        // Don't filter modular pages
                        if ($page->modular()) {
                            continue;
                        }
                        foreach ($items as $item) {
                            $item = rawurldecode($item);
                            if (empty($page->taxonomy[$taxonomy]) || !in_array(htmlspecialchars_decode($item,
                                    ENT_QUOTES), $page->taxonomy[$taxonomy])
                            ) {
                                $collection->remove($page->path());
                            }
                        }
                    }
                }
            }
        }

        if (isset($params['dateRange'])) {
            $start = isset($params['dateRange']['start']) ? $params['dateRange']['start'] : 0;
            $end = isset($params['dateRange']['end']) ? $params['dateRange']['end'] : false;
            $field = isset($params['dateRange']['field']) ? $params['dateRange']['field'] : false;
            $collection->dateRange($start, $end, $field);
        }

        if (isset($params['order'])) {
            $by = isset($params['order']['by']) ? $params['order']['by'] : 'default';
            $dir = isset($params['order']['dir']) ? $params['order']['dir'] : 'asc';
            $custom = isset($params['order']['custom']) ? $params['order']['custom'] : null;
            $sort_flags = isset($params['order']['sort_flags']) ? $params['order']['sort_flags'] : null;

            if (is_array($sort_flags)) {
                $sort_flags = array_map('constant', $sort_flags); //transform strings to constant value
                $sort_flags = array_reduce($sort_flags, function($a, $b) { return $a | $b; }, 0); //merge constant values using bit or
            }

            $collection->order($by, $dir, $custom, $sort_flags);
        }

        /** @var Grav $grav */
        $grav = Grav::instance()['grav'];

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
    public function evaluate($value)
    {
        // Parse command.
        if (is_string($value)) {
            // Format: @command.param
            $cmd = $value;
            $params = [];
        } elseif (is_array($value) && count($value) == 1 && !is_int(key($value))) {
            // Format: @command.param: { attr1: value1, attr2: value2 }
            $cmd = (string)key($value);
            $params = (array)current($value);
        } else {
            $result = [];
            foreach ($value as $key => $val) {
                if (is_int($key)) {
                    $result = $result + $this->evaluate($val)->toArray();
                } else {
                    $result = $result + $this->evaluate([$key => $val])->toArray();
                }

            }

            return new Collection($result);
        }

        /** @var Pages $pages */
        $pages = Grav::instance()['pages'];

        $parts = explode('.', $cmd);
        $current = array_shift($parts);

        /** @var Collection $results */
        $results = new Collection();

        switch ($current) {
            case 'self@':
            case '@self':
                if (!empty($parts)) {
                    switch ($parts[0]) {
                        case 'modular':
                            // @self.modular: false (alternative to @self.children)
                            if (!empty($params) && $params[0] === false) {
                                $results = $this->children()->nonModular();
                                break;
                            }
                            $results = $this->children()->modular();
                            break;
                        case 'children':
                            $results = $this->children()->nonModular();
                            break;
                        case 'all':
                            $results = $this->children();
                            break;
                        case 'parent':
                            $collection = new Collection();
                            $results = $collection->addPage($this->parent());
                            break;
                        case 'siblings':
                            if (!$this->parent()) {
                                return new Collection();
                            }
                            $results = $this->parent()->children()->remove($this->path());
                            break;
                        case 'descendants':
                            $results = $pages->all($this)->remove($this->path())->nonModular();
                            break;
                    }
                }

                $results = $results->published();
                break;

            case 'page@':
            case '@page':
                $page = null;

                if (!empty($params)) {
                    $page = $this->find($params[0]);
                }

                // safety check in case page is not found
                if (!isset($page)) {
                    return $results;
                }

                // Handle a @page.descendants
                if (!empty($parts)) {
                    switch ($parts[0]) {
                        case 'modular':
                            $results = new Collection();
                            foreach ($page->children() as $child) {
                              $results = $results->addPage($child);
                            }
                            $results->modular();
                            break;
                        case 'page':
                        case 'self':
                            $results = new Collection();
                            $results = $results->addPage($page)->nonModular();
                            break;

                        case 'descendants':
                            $results = $pages->all($page)->remove($page->path())->nonModular();
                            break;

                        case 'children':
                            $results = $page->children()->nonModular();
                            break;
                    }
                } else {
                    $results = $page->children()->nonModular();
                }

                $results = $results->published();

                break;

            case 'root@':
            case '@root':
                if (!empty($parts) && $parts[0] == 'descendants') {
                    $results = $pages->all($pages->root())->nonModular()->published();
                } else {
                    $results = $pages->root()->children()->nonModular()->published();
                }
                break;

            case 'taxonomy@':
            case '@taxonomy':
                // Gets a collection of pages by using one of the following formats:
                // @taxonomy.category: blog
                // @taxonomy.category: [ blog, featured ]
                // @taxonomy: { category: [ blog, featured ], level: 1 }

                /** @var Taxonomy $taxonomy_map */
                $taxonomy_map = Grav::instance()['taxonomy'];

                if (!empty($parts)) {
                    $params = [implode('.', $parts) => $params];
                }
                $results = $taxonomy_map->findTaxonomy($params)->published();
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
     *
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
     *
     * @param bool $reorder
     *
     * @throws Exception
     */
    protected function doRelocation($reorder)
    {
        if (!$this->_original) {
            return;
        }

        // Do reordering.
        if ($reorder && $this->order() != $this->_original->order()) {
            /** @var Pages $pages */
            $pages = Grav::instance()['pages'];

            $parent = $this->parent();

            // Extract visible children from the parent page.
            $list = [];
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
                array_splice($list, min($this->order() - 1, count($list)), 0, [$this->path()]);
            }

            // Reorder all moved pages.
            foreach ($list as $order => $path) {
                if ($path == $this->path()) {
                    // Handle current page; we do want to change ordering number, but nothing else.
                    $this->order($order + 1);
                } else {
                    // Handle all the other pages.
                    $page = $pages->get($path);

                    if ($page && $page->exists() && !$page->_action && $page->order() != $order + 1) {
                        $page = $page->move($parent);
                        $page->order($order + 1);
                        $page->save(false);
                    }
                }
            }
        }
        if (is_dir($this->_original->path())) {
            if ($this->_action == 'move') {
                Folder::move($this->_original->path(), $this->path());
            } elseif ($this->_action == 'copy') {
                Folder::copy($this->_original->path(), $this->path());
            }
        }

        if ($this->name() != $this->_original->name()) {
            $path = $this->path();
            if (is_file($path . '/' . $this->_original->name())) {
                rename($path . '/' . $this->_original->name(), $path . '/' . $this->name());
            }
        }

        $this->_original = null;
    }

    protected function setPublishState()
    {
        // Handle publishing dates if no explict published option set
        if (Grav::instance()['config']->get('system.pages.publish_dates') && !isset($this->header->published)) {
            // unpublish if required, if not clear cache right before page should be unpublished
            if ($this->unpublishDate()) {
                if ($this->unpublishDate() < time()) {
                    $this->published(false);
                } else {
                    $this->published();
                    Grav::instance()['cache']->setLifeTime($this->unpublishDate());
                }
            }
            // publish if required, if not clear cache right before page is published
            if ($this->publishDate() && $this->publishDate() && $this->publishDate() > time()) {
                $this->published(false);
                Grav::instance()['cache']->setLifeTime($this->publishDate());
            }
        }
    }
}
