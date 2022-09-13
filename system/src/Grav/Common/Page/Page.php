<?php

/**
 * @package    Grav\Common\Page
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Page;

use Exception;
use Grav\Common\Cache;
use Grav\Common\Config\Config;
use Grav\Common\Data\Blueprint;
use Grav\Common\File\CompiledYamlFile;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use Grav\Common\Language\Language;
use Grav\Common\Markdown\Parsedown;
use Grav\Common\Markdown\ParsedownExtra;
use Grav\Common\Page\Interfaces\PageCollectionInterface;
use Grav\Common\Page\Interfaces\PageInterface;
use Grav\Common\Media\Traits\MediaTrait;
use Grav\Common\Page\Markdown\Excerpts;
use Grav\Common\Page\Traits\PageFormTrait;
use Grav\Common\Twig\Twig;
use Grav\Common\Uri;
use Grav\Common\Utils;
use Grav\Common\Yaml;
use Grav\Framework\Flex\Flex;
use InvalidArgumentException;
use RocketTheme\Toolbox\Event\Event;
use RocketTheme\Toolbox\File\MarkdownFile;
use RuntimeException;
use SplFileInfo;
use function dirname;
use function in_array;
use function is_array;
use function is_object;
use function is_string;
use function strlen;

define('PAGE_ORDER_PREFIX_REGEX', '/^[0-9]+\./u');

/**
 * Class Page
 * @package Grav\Common\Page
 */
class Page implements PageInterface
{
    use PageFormTrait;
    use MediaTrait;

    /** @var string|null Filename. Leave as null if page is folder. */
    protected $name;
    /** @var bool */
    protected $initialized = false;
    /** @var string */
    protected $folder;
    /** @var string */
    protected $path;
    /** @var string */
    protected $extension;
    /** @var string */
    protected $url_extension;
    /** @var string */
    protected $id;
    /** @var string */
    protected $parent;
    /** @var string */
    protected $template;
    /** @var int */
    protected $expires;
    /** @var string */
    protected $cache_control;
    /** @var bool */
    protected $visible;
    /** @var bool */
    protected $published;
    /** @var int */
    protected $publish_date;
    /** @var int|null */
    protected $unpublish_date;
    /** @var string */
    protected $slug;
    /** @var string|null */
    protected $route;
    /** @var string|null */
    protected $raw_route;
    /** @var string */
    protected $url;
    /** @var array */
    protected $routes;
    /** @var bool */
    protected $routable;
    /** @var int */
    protected $modified;
    /** @var string */
    protected $redirect;
    /** @var string */
    protected $external_url;
    /** @var object|null */
    protected $header;
    /** @var string */
    protected $frontmatter;
    /** @var string */
    protected $language;
    /** @var string|null */
    protected $content;
    /** @var array */
    protected $content_meta;
    /** @var string|null */
    protected $summary;
    /** @var string */
    protected $raw_content;
    /** @var array|null */
    protected $metadata;
    /** @var string */
    protected $title;
    /** @var int */
    protected $max_count;
    /** @var string */
    protected $menu;
    /** @var int */
    protected $date;
    /** @var string */
    protected $dateformat;
    /** @var array */
    protected $taxonomy;
    /** @var string */
    protected $order_by;
    /** @var string */
    protected $order_dir;
    /** @var array|string|null */
    protected $order_manual;
    /** @var bool */
    protected $modular_twig;
    /** @var array */
    protected $process;
    /** @var int|null */
    protected $summary_size;
    /** @var bool */
    protected $markdown_extra;
    /** @var bool */
    protected $etag;
    /** @var bool */
    protected $last_modified;
    /** @var string */
    protected $home_route;
    /** @var bool */
    protected $hide_home_route;
    /** @var bool */
    protected $ssl;
    /** @var string */
    protected $template_format;
    /** @var bool */
    protected $debugger;

    /** @var PageInterface|null Unmodified (original) version of the page. Used for copying and moving the page. */
    private $_original;
    /** @var string Action */
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
     * @param  SplFileInfo $file The file information for the .md file that the page represents
     * @param  string|null $extension
     * @return $this
     */
    public function init(SplFileInfo $file, $extension = null)
    {
        $config = Grav::instance()['config'];

        $this->initialized = true;

        // some extension logic
        if (empty($extension)) {
            $this->extension('.' . $file->getExtension());
        } else {
            $this->extension($extension);
        }

        // extract page language from page extension
        $language = trim(Utils::basename($this->extension(), 'md'), '.') ?: null;
        $this->language($language);

        $this->hide_home_route = $config->get('system.home.hide_in_urls', false);
        $this->home_route = $this->adjustRouteCase($config->get('system.home.alias'));
        $this->filePath($file->getPathname());
        $this->modified($file->getMTime());
        $this->id($this->modified() . md5($this->filePath()));
        $this->routable(true);
        $this->header();
        $this->date();
        $this->metadata();
        $this->url();
        $this->visible();
        $this->modularTwig(strpos($this->slug(), '_') === 0);
        $this->setPublishState();
        $this->published();
        $this->urlExtension();

        return $this;
    }

    #[\ReturnTypeWillChange]
    public function __clone()
    {
        $this->initialized = false;
        $this->header = $this->header ? clone $this->header : null;
    }

    /**
     * @return void
     */
    public function initialize(): void
    {
        if (!$this->initialized) {
            $this->initialized = true;
            $this->route = null;
            $this->raw_route = null;
            $this->_forms = null;
        }
    }

    /**
     * @return void
     */
    protected function processFrontmatter()
    {
        // Quick check for twig output tags in frontmatter if enabled
        $process_fields = (array)$this->header();
        if (Utils::contains(json_encode(array_values($process_fields)), '{{')) {
            $ignored_fields = [];
            foreach ((array)Grav::instance()['config']->get('system.pages.frontmatter.ignore_fields') as $field) {
                if (isset($process_fields[$field])) {
                    $ignored_fields[$field] = $process_fields[$field];
                    unset($process_fields[$field]);
                }
            }
            $text_header = Grav::instance()['twig']->processString(json_encode($process_fields, JSON_UNESCAPED_UNICODE), ['page' => $this]);
            $this->header((object)(json_decode($text_header, true) + $ignored_fields));
        }
    }

    /**
     * Return an array with the routes of other translated languages
     *
     * @param bool $onlyPublished only return published translations
     * @return array the page translated languages
     */
    public function translatedLanguages($onlyPublished = false)
    {
        $grav = Grav::instance();

        /** @var Language $language */
        $language = $grav['language'];

        $languages = $language->getLanguages();
        $defaultCode = $language->getDefault();

        $name = substr($this->name, 0, -strlen($this->extension()));
        $translatedLanguages = [];

        foreach ($languages as $languageCode) {
            $languageExtension = ".{$languageCode}.md";
            $path = $this->path . DS . $this->folder . DS . $name . $languageExtension;
            $exists = file_exists($path);

            // Default language may be saved without language file location.
            if (!$exists && $languageCode === $defaultCode) {
                $languageExtension = '.md';
                $path = $this->path . DS . $this->folder . DS . $name . $languageExtension;
                $exists = file_exists($path);
            }

            if ($exists) {
                $aPage = new Page();
                $aPage->init(new SplFileInfo($path), $languageExtension);
                $aPage->route($this->route());
                $aPage->rawRoute($this->rawRoute());
                $route = $aPage->header()->routes['default'] ?? $aPage->rawRoute();
                if (!$route) {
                    $route = $aPage->route();
                }

                if ($onlyPublished && !$aPage->published()) {
                    continue;
                }

                $translatedLanguages[$languageCode] = $route;
            }
        }

        return $translatedLanguages;
    }

    /**
     * Return an array listing untranslated languages available
     *
     * @param bool $includeUnpublished also list unpublished translations
     * @return array the page untranslated languages
     */
    public function untranslatedLanguages($includeUnpublished = false)
    {
        $grav = Grav::instance();

        /** @var Language $language */
        $language = $grav['language'];

        $languages = $language->getLanguages();
        $translated = array_keys($this->translatedLanguages(!$includeUnpublished));

        return array_values(array_diff($languages, $translated));
    }

    /**
     * Gets and Sets the raw data
     *
     * @param  string|null $var Raw content string
     * @return string      Raw content string
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
     * @param  object|array|null $var a YAML object representing the configuration for the file
     * @return \stdClass      the current YAML configuration
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
                try {
                    $this->raw_content = $file->markdown();
                    $this->frontmatter = $file->frontmatter();
                    $this->header = (object)$file->header();

                    if (!Utils::isAdminPlugin()) {
                        // If there's a `frontmatter.yaml` file merge that in with the page header
                        // note page's own frontmatter has precedence and will overwrite any defaults
                        $frontmatterFile = CompiledYamlFile::instance($this->path . '/' . $this->folder . '/frontmatter.yaml');
                        if ($frontmatterFile->exists()) {
                            $frontmatter_data = (array)$frontmatterFile->content();
                            $this->header = (object)array_replace_recursive(
                                $frontmatter_data,
                                (array)$this->header
                            );
                            $frontmatterFile->free();
                        }
                        // Process frontmatter with Twig if enabled
                        if (Grav::instance()['config']->get('system.pages.frontmatter.process_twig') === true) {
                            $this->processFrontmatter();
                        }
                    }
                } catch (Exception $e) {
                    $file->raw(Grav::instance()['language']->translate([
                        'GRAV.FRONTMATTER_ERROR_PAGE',
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
                $this->slug($this->header->slug);
            }
            if (isset($this->header->routes)) {
                $this->routes = (array)$this->header->routes;
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
                $this->taxonomy($this->header->taxonomy);
            }
            if (isset($this->header->max_count)) {
                $this->max_count = (int)$this->header->max_count;
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
                $this->expires = (int)$this->header->expires;
            }
            if (isset($this->header->cache_control)) {
                $this->cache_control = $this->header->cache_control;
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
            if (isset($this->header->debugger)) {
                $this->debugger = (bool)$this->header->debugger;
            }
            if (isset($this->header->append_url_extension)) {
                $this->url_extension = $this->header->append_url_extension;
            }
        }

        return $this->header;
    }

    /**
     * Get page language
     *
     * @param string|null $var
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
     * @param string $key
     * @param mixed $value
     */
    public function modifyHeader($key, $value)
    {
        $this->header->{$key} = $value;
    }

    /**
     * @return int
     */
    public function httpResponseCode()
    {
        return (int)($this->header()->http_response_code ?? 200);
    }

    /**
     * @return array
     */
    public function httpHeaders()
    {
        $headers = [];

        $grav = Grav::instance();
        $format = $this->templateFormat();
        $cache_control = $this->cacheControl();
        $expires = $this->expires();

        // Set Content-Type header
        $headers['Content-Type'] = Utils::getMimeByExtension($format, 'text/html');

        // Calculate Expires Headers if set to > 0
        if ($expires > 0) {
            $expires_date = gmdate('D, d M Y H:i:s', time() + $expires) . ' GMT';
            if (!$cache_control) {
                $headers['Cache-Control'] = 'max-age=' . $expires;
            }
            $headers['Expires'] = $expires_date;
        }

        // Set Cache-Control header
        if ($cache_control) {
            $headers['Cache-Control'] = strtolower($cache_control);
        }

        // Set Last-Modified header
        if ($this->lastModified()) {
            $last_modified_date = gmdate('D, d M Y H:i:s', $this->modified()) . ' GMT';
            $headers['Last-Modified'] = $last_modified_date;
        }

        // Ask Grav to calculate ETag from the final content.
        if ($this->eTag()) {
            $headers['ETag'] = '1';
        }

        // Set Vary: Accept-Encoding header
        if ($grav['config']->get('system.pages.vary_accept_encoding', false)) {
            $headers['Vary'] = 'Accept-Encoding';
        }


        // Added new Headers event
        $headers_obj = (object) $headers;
        Grav::instance()->fireEvent('onPageHeaders', new Event(['headers' => $headers_obj]));

        return (array)$headers_obj;
    }

    /**
     * Get the summary.
     *
     * @param int|null $size Max summary size.
     * @param bool $textOnly Only count text size.
     * @return string
     */
    public function summary($size = null, $textOnly = false)
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
            $content = $textOnly ? strip_tags($this->content()) : $this->content();
            $summary_size = $this->summary_size;
        } else {
            $content = $textOnly ? strip_tags($this->summary) : $this->summary;
            $summary_size = mb_strwidth($content, 'utf-8');
        }

        // Return calculated summary based on summary divider's position
        $format = $config['format'];
        // Return entire page content on wrong/ unknown format
        if (!in_array($format, ['short', 'long'])) {
            return $content;
        }
        if (($format === 'short') && isset($summary_size)) {
            // Slice the string
            if (mb_strwidth($content, 'utf8') > $summary_size) {
                return mb_substr($content, 0, $summary_size);
            }

            return $content;
        }

        // Get summary size from site config's file
        if ($size === null) {
            $size = $config['size'];
        }

        // If the size is zero, return the entire page content
        if ($size === 0) {
            return $content;
            // Return calculated summary based on defaults
        }
        if (!is_numeric($size) || ($size < 0)) {
            $size = 300;
        }

        // Only return string but not html, wrap whatever html tag you want when using
        if ($textOnly) {
            if (mb_strwidth($content, 'utf-8') <= $size) {
                return $content;
            }

            return mb_strimwidth($content, 0, $size, '…', 'UTF-8');
        }

        $summary = Utils::truncateHtml($content, $size);

        return html_entity_decode($summary, ENT_COMPAT | ENT_HTML401, 'UTF-8');
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
     * @param  string|null $var Content
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
            $cache_id = md5('page' . $this->getCacheKey());
            $content_obj = $cache->fetch($cache_id);

            if (is_array($content_obj)) {
                $this->content = $content_obj['content'];
                $this->content_meta = $content_obj['content_meta'];
            } else {
                $this->content = $content_obj;
            }


            $process_markdown = $this->shouldProcess('markdown');
            $process_twig = $this->shouldProcess('twig') || $this->modularTwig();

            $cache_enable = $this->header->cache_enable ?? $config->get(
                'system.cache.enabled',
                true
            );
            $twig_first = $this->header->twig_first ?? $config->get(
                'system.pages.twig_first',
                false
            );

            // never cache twig means it's always run after content
            $never_cache_twig = $this->header->never_cache_twig ?? $config->get(
                'system.pages.never_cache_twig',
                true
            );

            // if no cached-content run everything
            if ($never_cache_twig) {
                if ($this->content === false || $cache_enable === false) {
                    $this->content = $this->raw_content;
                    Grav::instance()->fireEvent('onPageContentRaw', new Event(['page' => $this]));

                    if ($process_markdown) {
                        $this->processMarkdown();
                    }

                    // Content Processed but not cached yet
                    Grav::instance()->fireEvent('onPageContentProcessed', new Event(['page' => $this]));

                    if ($cache_enable) {
                        $this->cachePageContent();
                    }
                }

                if ($process_twig) {
                    $this->processTwig();
                }
            } else {
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
                            $this->processMarkdown($process_twig);
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
            }

            // Handle summary divider
            $delimiter = $config->get('site.summary.delimiter', '===');
            $divider_pos = mb_strpos($this->content, "<p>{$delimiter}</p>");
            if ($divider_pos !== false) {
                $this->summary_size = $divider_pos;
                $this->content = str_replace("<p>{$delimiter}</p>", '', $this->content);
            }

            // Fire event when Page::content() is called
            Grav::instance()->fireEvent('onPageContent', new Event(['page' => $this]));
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
     * @param string $name
     * @param mixed $value
     */
    public function addContentMeta($name, $value)
    {
        $this->content_meta[$name] = $value;
    }

    /**
     * Return the whole contentMeta array as it currently stands
     *
     * @param string|null $name
     *
     * @return mixed|null
     */
    public function getContentMeta($name = null)
    {
        if ($name) {
            return $this->content_meta[$name] ?? null;
        }

        return $this->content_meta;
    }

    /**
     * Sets the whole content meta array in one shot
     *
     * @param array $content_meta
     *
     * @return array
     */
    public function setContentMeta($content_meta)
    {
        return $this->content_meta = $content_meta;
    }

    /**
     * Process the Markdown content.  Uses Parsedown or Parsedown Extra depending on configuration
     *
     * @param bool $keepTwig If true, content between twig tags will not be processed.
     * @return void
     */
    protected function processMarkdown(bool $keepTwig = false)
    {
        /** @var Config $config */
        $config = Grav::instance()['config'];

        $markdownDefaults = (array)$config->get('system.pages.markdown');
        if (isset($this->header()->markdown)) {
            $markdownDefaults = array_merge($markdownDefaults, $this->header()->markdown);
        }

        // pages.markdown_extra is deprecated, but still check it...
        if (!isset($markdownDefaults['extra']) && (isset($this->markdown_extra) || $config->get('system.pages.markdown_extra') !== null)) {
            user_error('Configuration option \'system.pages.markdown_extra\' is deprecated since Grav 1.5, use \'system.pages.markdown.extra\' instead', E_USER_DEPRECATED);

            $markdownDefaults['extra'] = $this->markdown_extra ?: $config->get('system.pages.markdown_extra');
        }

        $extra = $markdownDefaults['extra'] ?? false;
        $defaults = [
            'markdown' => $markdownDefaults,
            'images' => $config->get('system.images', [])
        ];

        $excerpts = new Excerpts($this, $defaults);

        // Initialize the preferred variant of Parsedown
        if ($extra) {
            $parsedown = new ParsedownExtra($excerpts);
        } else {
            $parsedown = new Parsedown($excerpts);
        }

        $content = $this->content;
        if ($keepTwig) {
            $token = [
                '/' . Utils::generateRandomString(3),
                Utils::generateRandomString(3) . '/'
            ];
            // Base64 encode any twig.
            $content = preg_replace_callback(
                ['/({#.*?#})/mu', '/({{.*?}})/mu', '/({%.*?%})/mu'],
                static function ($matches) use ($token) { return $token[0] . base64_encode($matches[1]) . $token[1]; },
                $content
            );
        }

        $content = $parsedown->text($content);

        if ($keepTwig) {
            // Base64 decode the encoded twig.
            $content = preg_replace_callback(
                ['`' . $token[0] . '([A-Za-z0-9+/]+={0,2})' . $token[1] . '`mu'],
                static function ($matches) { return base64_decode($matches[1]); },
                $content
            );
        }

        $this->content = $content;
    }


    /**
     * Process the Twig page content.
     *
     * @return void
     */
    private function processTwig()
    {
        /** @var Twig $twig */
        $twig = Grav::instance()['twig'];
        $this->content = $twig->processPage($this, $this->content);
    }

    /**
     * Fires the onPageContentProcessed event, and caches the page content using a unique ID for the page
     *
     * @return void
     */
    public function cachePageContent()
    {
        /** @var Cache $cache */
        $cache = Grav::instance()['cache'];
        $cache_id = md5('page' . $this->getCacheKey());
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
     * @param string|null $content
     * @return void
     */
    public function setRawContent($content)
    {
        $this->content = $content ?? '';
    }

    /**
     * Get value from a page variable (used mostly for creating edit forms).
     *
     * @param string $name Variable name.
     * @param mixed $default
     * @return mixed
     */
    public function value($name, $default = null)
    {
        if ($name === 'content') {
            return $this->raw_content;
        }
        if ($name === 'route') {
            $parent = $this->parent();

            return $parent ? $parent->rawRoute() : '';
        }
        if ($name === 'order') {
            $order = $this->order();

            return $order ? (int)$this->order() : '';
        }
        if ($name === 'ordering') {
            return (bool)$this->order();
        }
        if ($name === 'folder') {
            return preg_replace(PAGE_ORDER_PREFIX_REGEX, '', $this->folder);
        }
        if ($name === 'slug') {
            return $this->slug();
        }
        if ($name === 'name') {
            $name = $this->name();
            $language = $this->language() ? '.' . $this->language() : '';
            $pattern = '%(' . preg_quote($language, '%') . ')?\.md$%';
            $name = preg_replace($pattern, '', $name);

            if ($this->isModule()) {
                return 'modular/' . $name;
            }

            return $name;
        }
        if ($name === 'media') {
            return $this->media()->all();
        }
        if ($name === 'media.file') {
            return $this->media()->files();
        }
        if ($name === 'media.video') {
            return $this->media()->videos();
        }
        if ($name === 'media.image') {
            return $this->media()->images();
        }
        if ($name === 'media.audio') {
            return $this->media()->audios();
        }

        $path = explode('.', $name);
        $scope = array_shift($path);

        if ($name === 'frontmatter') {
            return $this->frontmatter;
        }

        if ($scope === 'header') {
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
     * @param string|null $var
     * @return string
     */
    public function rawMarkdown($var = null)
    {
        if ($var !== null) {
            $this->raw_content = $var;
        }

        return $this->raw_content;
    }

    /**
     * @return bool
     * @internal
     */
    public function translated(): bool
    {
        return $this->initialized;
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
     * @param bool|array $reorder Internal use.
     */
    public function save($reorder = true)
    {
        // Perform move, copy [or reordering] if needed.
        $this->doRelocation();

        $file = $this->file();
        if ($file) {
            $file->filename($this->filePath());
            $file->header((array)$this->header());
            $file->markdown($this->raw_content);
            $file->save();
        }

        // Perform reorder if required
        if ($reorder && is_array($reorder)) {
            $this->doReorder($reorder);
        }

        // We need to signal Flex Pages about the change.
        /** @var Flex|null $flex */
        $flex = Grav::instance()['flex'] ?? null;
        $directory = $flex ? $flex->getDirectory('pages') : null;
        if (null !== $directory) {
            $directory->clearCache();
        }

        $this->_original = null;
    }

    /**
     * Prepare move page to new location. Moves also everything that's under the current page.
     *
     * You need to call $this->save() in order to perform the move.
     *
     * @param PageInterface $parent New parent page.
     * @return $this
     */
    public function move(PageInterface $parent)
    {
        if (!$this->_original) {
            $clone = clone $this;
            $this->_original = $clone;
        }

        $this->_action = 'move';

        if ($this->route() === $parent->route()) {
            throw new RuntimeException('Failed: Cannot set page parent to self');
        }
        if (Utils::startsWith($parent->rawRoute(), $this->rawRoute())) {
            throw new RuntimeException('Failed: Cannot set page parent to a child of current page');
        }

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
     * @param PageInterface $parent New parent page.
     * @return $this
     */
    public function copy(PageInterface $parent)
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
        if (empty($fields) && ($edit_mode === 'auto' || $edit_mode === 'normal')) {
            $blueprint = $pages->blueprints('default');
        }

        // override if you only want 'expert' mode
        if (!empty($fields) && $edit_mode === 'expert') {
            $blueprint = $pages->blueprints('');
        }

        return $blueprint;
    }

    /**
     * Returns the blueprint from the page.
     *
     * @param string $name Not used.
     * @return Blueprint Returns a Blueprint.
     */
    public function getBlueprint(string $name = '')
    {
        return $this->blueprints();
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
     * @return void
     * @throws Exception
     */
    public function validate()
    {
        $blueprints = $this->blueprints();
        $blueprints->validate($this->toArray());
    }

    /**
     * Filter page header from illegal contents.
     *
     * @return void
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
            'header' => (array)$this->header(),
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
        return Yaml::dump($this->toArray(), 20);
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
     * @return string
     */
    public function getCacheKey(): string
    {
        return $this->id();
    }

    /**
     * Gets and sets the associated media as found in the page folder.
     *
     * @param  Media|null $var Representation of associated media.
     * @return Media      Representation of associated media.
     */
    public function media($var = null)
    {
        if ($var) {
            $this->setMedia($var);
        }

        /** @var Media $media */
        $media = $this->getMedia();

        return $media;
    }

    /**
     * Get filesystem path to the associated media.
     *
     * @return string|null
     */
    public function getMediaFolder()
    {
        return $this->path();
    }

    /**
     * Get display order for the associated media.
     *
     * @return array Empty array means default ordering.
     */
    public function getMediaOrder()
    {
        $header = $this->header();

        return isset($header->media_order) ? array_map('trim', explode(',', (string)$header->media_order)) : [];
    }

    /**
     * Gets and sets the name field.  If no name field is set, it will return 'default.md'.
     *
     * @param  string|null $var The name of this page.
     * @return string      The name of this page.
     */
    public function name($var = null)
    {
        if ($var !== null) {
            $this->name = $var;
        }

        return $this->name ?: 'default.md';
    }

    /**
     * Returns child page type.
     *
     * @return string
     */
    public function childType()
    {
        return isset($this->header->child_type) ? (string)$this->header->child_type : '';
    }

    /**
     * Gets and sets the template field. This is used to find the correct Twig template file to render.
     * If no field is set, it will return the name without the .md extension
     *
     * @param  string|null $var the template name
     * @return string      the template name
     */
    public function template($var = null)
    {
        if ($var !== null) {
            $this->template = $var;
        }
        if (empty($this->template)) {
            $this->template = ($this->isModule() ? 'modular/' : '') . str_replace($this->extension(), '', $this->name());
        }

        return $this->template;
    }

    /**
     * Allows a page to override the output render format, usually the extension provided in the URL.
     * (e.g. `html`, `json`, `xml`, etc).
     *
     * @param string|null $var
     * @return string
     */
    public function templateFormat($var = null)
    {
        if (null !== $var) {
            $this->template_format = is_string($var) ? $var : null;
        }

        if (!isset($this->template_format)) {
            $this->template_format = ltrim($this->header->append_url_extension ?? Utils::getPageFormat(), '.');
        }

        return $this->template_format;
    }

    /**
     * Gets and sets the extension field.
     *
     * @param string|null $var
     * @return string
     */
    public function extension($var = null)
    {
        if ($var !== null) {
            $this->extension = $var;
        }
        if (empty($this->extension)) {
            $this->extension = '.' . Utils::pathinfo($this->name(), PATHINFO_EXTENSION);
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
        if (null === $this->url_extension) {
            $this->url_extension = Grav::instance()['config']->get('system.pages.append_url_extension', '');
        }

        return $this->url_extension;
    }

    /**
     * Gets and sets the expires field. If not set will return the default
     *
     * @param  int|null $var The new expires value.
     * @return int      The expires value
     */
    public function expires($var = null)
    {
        if ($var !== null) {
            $this->expires = $var;
        }

        return $this->expires ?? Grav::instance()['config']->get('system.pages.expires');
    }

    /**
     * Gets and sets the cache-control property.  If not set it will return the default value (null)
     * https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Cache-Control for more details on valid options
     *
     * @param string|null $var
     * @return string|null
     */
    public function cacheControl($var = null)
    {
        if ($var !== null) {
            $this->cache_control = $var;
        }

        return $this->cache_control ?? Grav::instance()['config']->get('system.pages.cache_control');
    }

    /**
     * Gets and sets the title for this Page.  If no title is set, it will use the slug() to get a name
     *
     * @param  string|null $var the title of the Page
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
     * @param  string|null $var the menu field for the page
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
     * @param  bool|null $var true if the page is visible
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
     * @param  bool|null $var true if the page is published
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
     * @param  string|null $var string representation of a date
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
     * @param  string|null $var string representation of a date
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
     * @param  bool|null $var true if the page is routable
     * @return bool      true if the page is routable
     */
    public function routable($var = null)
    {
        if ($var !== null) {
            $this->routable = (bool)$var;
        }

        return $this->routable && $this->published();
    }

    /**
     * @param bool|null $var
     * @return bool
     */
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
     * @param  array|null $var an Array of name value pairs where the name is the process and value is true or false
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
     * Returns the state of the debugger override setting for this page
     *
     * @return bool
     */
    public function debugger()
    {
        return !(isset($this->debugger) && $this->debugger === false);
    }

    /**
     * Function to merge page metadata tags and build an array of Metadata objects
     * that can then be rendered in the page.
     *
     * @param  array|null $var an Array of metadata values to set
     * @return array      an Array of metadata values for the page
     */
    public function metadata($var = null)
    {
        if ($var !== null) {
            $this->metadata = (array)$var;
        }

        // if not metadata yet, process it.
        if (null === $this->metadata) {
            $header_tag_http_equivs = ['content-type', 'default-style', 'refresh', 'x-ua-compatible', 'content-security-policy'];

            $this->metadata = [];

            // Set the Generator tag
            $metadata = [
                'generator' => 'GravCMS'
            ];

            $config = Grav::instance()['config'];

            $escape = !$config->get('system.strict_mode.twig_compat', false) || $config->get('system.twig.autoescape', true);

            // Get initial metadata for the page
            $metadata = array_merge($metadata, $config->get('site.metadata', []));

            if (isset($this->header->metadata) && is_array($this->header->metadata)) {
                // Merge any site.metadata settings in with page metadata
                $metadata = array_merge($metadata, $this->header->metadata);
            }

            // Build an array of meta objects..
            foreach ((array)$metadata as $key => $value) {
                // Lowercase the key
                $key = strtolower($key);
                // If this is a property type metadata: "og", "twitter", "facebook" etc
                // Backward compatibility for nested arrays in metas
                if (is_array($value)) {
                    foreach ($value as $property => $prop_value) {
                        $prop_key = $key . ':' . $property;
                        $this->metadata[$prop_key] = [
                            'name' => $prop_key,
                            'property' => $prop_key,
                            'content' => $escape ? htmlspecialchars($prop_value, ENT_QUOTES | ENT_HTML5, 'UTF-8') : $prop_value
                        ];
                    }
                } else {
                    // If it this is a standard meta data type
                    if ($value) {
                        if (in_array($key, $header_tag_http_equivs, true)) {
                            $this->metadata[$key] = [
                                'http_equiv' => $key,
                                'content' => $escape ? htmlspecialchars($value, ENT_COMPAT, 'UTF-8') : $value
                            ];
                        } elseif ($key === 'charset') {
                            $this->metadata[$key] = ['charset' => $escape ? htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8') : $value];
                        } else {
                            // if it's a social metadata with separator, render as property
                            $separator = strpos($key, ':');
                            $hasSeparator = $separator && $separator < strlen($key) - 1;
                            $entry = [
                                'content' => $escape ? htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8') : $value
                            ];

                            if ($hasSeparator && !Utils::startsWith($key, ['twitter', 'flattr'])) {
                                $entry['property'] = $key;
                            } else {
                                $entry['name'] = $key;
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
     * Reset the metadata and pull from header again
     */
    public function resetMetadata()
    {
        $this->metadata = null;
    }

    /**
     * Gets and Sets the slug for the Page. The slug is used in the URL routing. If not set it uses
     * the parent folder from the path
     *
     * @param  string|null $var the slug, e.g. 'my-blog'
     * @return string      the slug
     */
    public function slug($var = null)
    {
        if ($var !== null && $var !== '') {
            $this->slug = $var;
        }

        if (empty($this->slug)) {
            $this->slug = $this->adjustRouteCase(preg_replace(PAGE_ORDER_PREFIX_REGEX, '', $this->folder)) ?: null;
        }

        return $this->slug;
    }

    /**
     * Get/set order number of this page.
     *
     * @param int|null $var
     * @return string|bool
     */
    public function order($var = null)
    {
        if ($var !== null) {
            $order = $var ? sprintf('%02d.', (int)$var) : '';
            $this->folder($order . preg_replace(PAGE_ORDER_PREFIX_REGEX, '', $this->folder));

            return $order;
        }

        preg_match(PAGE_ORDER_PREFIX_REGEX, $this->folder, $order);

        return $order[0] ?? false;
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
     * Gets the URL with host information, aka Permalink.
     * @return string The permalink.
     */
    public function permalink()
    {
        return $this->url(true, false, true, true);
    }

    /**
     * Returns the canonical URL for a page
     *
     * @param bool $include_lang
     * @return string
     */
    public function canonical($include_lang = true)
    {
        return $this->url(true, true, $include_lang);
    }

    /**
     * Gets the url for the Page.
     *
     * @param bool $include_host Defaults false, but true would include http://yourhost.com
     * @param bool $canonical    True to return the canonical URL
     * @param bool $include_base Include base url on multisite as well as language code
     * @param bool $raw_route
     * @return string The url.
     */
    public function url($include_host = false, $canonical = false, $include_base = true, $raw_route = false)
    {
        // Override any URL when external_url is set
        if (isset($this->external_url)) {
            return $this->external_url;
        }

        $grav = Grav::instance();

        /** @var Pages $pages */
        $pages = $grav['pages'];

        /** @var Config $config */
        $config = $grav['config'];

        // get base route (multi-site base and language)
        $route = $include_base ? $pages->baseRoute() : '';

        // add full route if configured to do so
        if (!$include_host && $config->get('system.absolute_urls', false)) {
            $include_host = true;
        }

        if ($canonical) {
            $route .= $this->routeCanonical();
        } elseif ($raw_route) {
            $route .= $this->rawRoute();
        } else {
            $route .= $this->route();
        }

        /** @var Uri $uri */
        $uri = $grav['uri'];
        $url = $uri->rootUrl($include_host) . '/' . trim($route, '/') . $this->urlExtension();

        return Uri::filterPath($url);
    }

    /**
     * Gets the route for the page based on the route headers if available, else from
     * the parents route and the current Page's slug.
     *
     * @param  string|null $var Set new default route.
     * @return string|null  The route for the Page.
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
                if ($this->hide_home_route && $parent->route() === $this->home_route) {
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
        unset($this->route, $this->slug);
    }

    /**
     * Gets and Sets the page raw route
     *
     * @param string|null $var
     * @return null|string
     */
    public function rawRoute($var = null)
    {
        if ($var !== null) {
            $this->raw_route = $var;
        }

        if (empty($this->raw_route)) {
            $parent = $this->parent();
            $baseRoute = $parent ? (string)$parent->rawRoute() : null;

            $slug = $this->adjustRouteCase(preg_replace(PAGE_ORDER_PREFIX_REGEX, '', $this->folder));

            $this->raw_route = isset($baseRoute) ? $baseRoute . '/' . $slug : null;
        }

        return $this->raw_route;
    }

    /**
     * Gets the route aliases for the page based on page headers.
     *
     * @param  array|null $var list of route aliases
     * @return array  The route aliases for the Page.
     */
    public function routeAliases($var = null)
    {
        if ($var !== null) {
            $this->routes['aliases'] = (array)$var;
        }

        if (!empty($this->routes) && isset($this->routes['aliases'])) {
            return $this->routes['aliases'];
        }

        return [];
    }

    /**
     * Gets the canonical route for this page if its set. If provided it will use
     * that value, else if it's `true` it will use the default route.
     *
     * @param string|null $var
     * @return bool|string
     */
    public function routeCanonical($var = null)
    {
        if ($var !== null) {
            $this->routes['canonical'] = $var;
        }

        if (!empty($this->routes) && isset($this->routes['canonical'])) {
            return $this->routes['canonical'];
        }

        return $this->route();
    }

    /**
     * Gets and sets the identifier for this Page object.
     *
     * @param  string|null $var the identifier
     * @return string      the identifier
     */
    public function id($var = null)
    {
        if (null === $this->id) {
            // We need to set unique id to avoid potential cache conflicts between pages.
            $var = time() . md5($this->filePath());
        }
        if ($var !== null) {
            // store unique per language
            $active_lang = Grav::instance()['language']->getLanguage() ?: '';
            $id = $active_lang . $var;
            $this->id = $id;
        }

        return $this->id;
    }

    /**
     * Gets and sets the modified timestamp.
     *
     * @param  int|null $var modified unix timestamp
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
     * @param  string|null $var redirect url
     * @return string|null
     */
    public function redirect($var = null)
    {
        if ($var !== null) {
            $this->redirect = $var;
        }

        return $this->redirect ?: null;
    }

    /**
     * Gets and sets the option to show the etag header for the page.
     *
     * @param  bool|null $var show etag header
     * @return bool      show etag header
     */
    public function eTag($var = null): bool
    {
        if ($var !== null) {
            $this->etag = $var;
        }
        if (!isset($this->etag)) {
            $this->etag = (bool)Grav::instance()['config']->get('system.pages.etag');
        }

        return $this->etag ?? false;
    }

    /**
     * Gets and sets the option to show the last_modified header for the page.
     *
     * @param  bool|null $var show last_modified header
     * @return bool      show last_modified header
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
     * @param  string|null $var the file path
     * @return string|null      the file path
     */
    public function filePath($var = null)
    {
        if ($var !== null) {
            // Filename of the page.
            $this->name = Utils::basename($var);
            // Folder of the page.
            $this->folder = Utils::basename(dirname($var));
            // Path to the page.
            $this->path = dirname($var, 2);
        }

        return rtrim($this->path . '/' . $this->folder . '/' . ($this->name() ?: ''), '/');
    }

    /**
     * Gets the relative path to the .md file
     *
     * @return string The relative file path
     */
    public function filePathClean()
    {
        return str_replace(GRAV_ROOT . DS, '', $this->filePath());
    }

    /**
     * Returns the clean path to the page file
     *
     * @return string
     */
    public function relativePagePath()
    {
        return str_replace('/' . $this->name(), '', $this->filePathClean());
    }

    /**
     * Gets and sets the path to the folder where the .md for this Page object resides.
     * This is equivalent to the filePath but without the filename.
     *
     * @param  string|null $var the path
     * @return string|null      the path
     */
    public function path($var = null)
    {
        if ($var !== null) {
            // Folder of the page.
            $this->folder = Utils::basename($var);
            // Path to the page.
            $this->path = dirname($var);
        }

        return $this->path ? $this->path . '/' . $this->folder : null;
    }

    /**
     * Get/set the folder.
     *
     * @param string|null $var Optional path
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
     * @param  string|null $var string representation of a date
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
     * @param  string|null $var string representation of a date format
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
     * @param  string|null $var the order, either "asc" or "desc"
     * @return string      the order, either "asc" or "desc"
     * @deprecated 1.6
     */
    public function orderDir($var = null)
    {
        //user_error(__CLASS__ . '::' . __FUNCTION__ . '() is deprecated since Grav 1.6', E_USER_DEPRECATED);

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
     * @param  string|null $var supported options include "default", "title", "date", and "folder"
     * @return string      supported options include "default", "title", "date", and "folder"
     * @deprecated 1.6
     */
    public function orderBy($var = null)
    {
        //user_error(__CLASS__ . '::' . __FUNCTION__ . '() is deprecated since Grav 1.6', E_USER_DEPRECATED);

        if ($var !== null) {
            $this->order_by = $var;
        }

        return $this->order_by;
    }

    /**
     * Gets the manual order set in the header.
     *
     * @param  string|null $var supported options include "default", "title", "date", and "folder"
     * @return array
     * @deprecated 1.6
     */
    public function orderManual($var = null)
    {
        //user_error(__CLASS__ . '::' . __FUNCTION__ . '() is deprecated since Grav 1.6', E_USER_DEPRECATED);

        if ($var !== null) {
            $this->order_manual = $var;
        }

        return (array)$this->order_manual;
    }

    /**
     * Gets and sets the maxCount field which describes how many sub-pages should be displayed if the
     * sub_pages header property is set for this page object.
     *
     * @param  int|null $var the maximum number of sub-pages
     * @return int      the maximum number of sub-pages
     * @deprecated 1.6
     */
    public function maxCount($var = null)
    {
        //user_error(__CLASS__ . '::' . __FUNCTION__ . '() is deprecated since Grav 1.6', E_USER_DEPRECATED);

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
     * @param  array|null $var an array of taxonomies
     * @return array      an array of taxonomies
     */
    public function taxonomy($var = null)
    {
        if ($var !== null) {
            // make sure first level are arrays
            array_walk($var, static function (&$value) {
                $value = (array) $value;
            });
            // make sure all values are strings
            array_walk_recursive($var, static function (&$value) {
                $value = (string) $value;
            });
            $this->taxonomy = $var;
        }

        return $this->taxonomy;
    }

    /**
     * Gets and sets the modular var that helps identify this page is a modular child
     *
     * @param  bool|null $var true if modular_twig
     * @return bool      true if modular_twig
     * @deprecated 1.7 Use ->isModule() or ->modularTwig() method instead.
     */
    public function modular($var = null)
    {
        user_error(__METHOD__ . '() is deprecated since Grav 1.7, use ->isModule() or ->modularTwig() method instead', E_USER_DEPRECATED);

        return $this->modularTwig($var);
    }

    /**
     * Gets and sets the modular_twig var that helps identify this page as a modular child page that will need
     * twig processing handled differently from a regular page.
     *
     * @param  bool|null $var true if modular_twig
     * @return bool      true if modular_twig
     */
    public function modularTwig($var = null)
    {
        if ($var !== null) {
            $this->modular_twig = (bool)$var;
            if ($var) {
                $this->visible(false);
                // some routable logic
                if (empty($this->header->routable)) {
                    $this->routable = false;
                }
            }
        }

        return $this->modular_twig ?? false;
    }

    /**
     * Gets the configured state of the processing method.
     *
     * @param  string $process the process, eg "twig" or "markdown"
     * @return bool            whether or not the processing method is enabled for this Page
     */
    public function shouldProcess($process)
    {
        return (bool)($this->process[$process] ?? false);
    }

    /**
     * Gets and Sets the parent object for this page
     *
     * @param  PageInterface|null $var the parent page object
     * @return PageInterface|null the parent page object if it exists.
     */
    public function parent(PageInterface $var = null)
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
     * Gets the top parent object for this page. Can return page itself.
     *
     * @return PageInterface The top parent page object.
     */
    public function topParent()
    {
        $topParent = $this;

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
     * @return PageCollectionInterface|Collection
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
     * @return bool True if item is first.
     */
    public function isFirst()
    {
        $parent = $this->parent();
        $collection = $parent ? $parent->collection('content', false) : null;
        if ($collection instanceof Collection) {
            return $collection->isFirst($this->path());
        }

        return true;
    }

    /**
     * Check to see if this item is the last in an array of sub-pages.
     *
     * @return bool True if item is last
     */
    public function isLast()
    {
        $parent = $this->parent();
        $collection = $parent ? $parent->collection('content', false) : null;
        if ($collection instanceof Collection) {
            return $collection->isLast($this->path());
        }

        return true;
    }

    /**
     * Gets the previous sibling based on current position.
     *
     * @return PageInterface the previous Page item
     */
    public function prevSibling()
    {
        return $this->adjacentSibling(-1);
    }

    /**
     * Gets the next sibling based on current position.
     *
     * @return PageInterface the next Page item
     */
    public function nextSibling()
    {
        return $this->adjacentSibling(1);
    }

    /**
     * Returns the adjacent sibling based on a direction.
     *
     * @param  int $direction either -1 or +1
     * @return PageInterface|false             the sibling page
     */
    public function adjacentSibling($direction = 1)
    {
        $parent = $this->parent();
        $collection = $parent ? $parent->collection('content', false) : null;
        if ($collection instanceof Collection) {
            return $collection->adjacentSibling($this->path(), $direction);
        }

        return false;
    }

    /**
     * Returns the item in the current position.
     *
     * @return int|null   The index of the current page.
     */
    public function currentPosition()
    {
        $parent = $this->parent();
        $collection = $parent ? $parent->collection('content', false) : null;
        if ($collection instanceof Collection) {
            return $collection->currentPosition($this->path());
        }

        return 1;
    }

    /**
     * Returns whether or not this page is the currently active page requested via the URL.
     *
     * @return bool True if it is active
     */
    public function active()
    {
        $uri_path = rtrim(urldecode(Grav::instance()['uri']->path()), '/') ?: '/';
        $routes = Grav::instance()['pages']->routes();

        return isset($routes[$uri_path]) && $routes[$uri_path] === $this->path();
    }

    /**
     * Returns whether or not this URI's URL contains the URL of the active page.
     * Or in other words, is this page's URL in the current URL
     *
     * @return bool True if active child exists
     */
    public function activeChild()
    {
        $grav = Grav::instance();
        /** @var Uri $uri */
        $uri = $grav['uri'];
        /** @var Pages $pages */
        $pages = $grav['pages'];
        $uri_path = rtrim(urldecode($uri->path()), '/');
        $routes = $pages->routes();

        if (isset($routes[$uri_path])) {
            $page = $pages->find($uri->route());
            /** @var PageInterface|null $child_page */
            $child_page = $page ? $page->parent() : null;
            while ($child_page && !$child_page->root()) {
                if ($this->path() === $child_page->path()) {
                    return true;
                }
                $child_page = $child_page->parent();
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

        return $this->route() === $home || $this->rawRoute() === $home;
    }

    /**
     * Returns whether or not this page is the root node of the pages tree.
     *
     * @return bool True if it is the root
     */
    public function root()
    {
        return !$this->parent && !$this->name && !$this->visible;
    }

    /**
     * Helper method to return an ancestor page.
     *
     * @param bool|null $lookup Name of the parent folder
     * @return PageInterface page you were looking for if it exists
     */
    public function ancestor($lookup = null)
    {
        /** @var Pages $pages */
        $pages = Grav::instance()['pages'];

        return $pages->ancestor($this->route, $lookup);
    }

    /**
     * Helper method to return an ancestor page to inherit from. The current
     * page object is returned.
     *
     * @param string $field Name of the parent folder
     * @return PageInterface
     */
    public function inherited($field)
    {
        [$inherited, $currentParams] = $this->getInheritedParams($field);

        $this->modifyHeader($field, $currentParams);

        return $inherited;
    }

    /**
     * Helper method to return an ancestor field only to inherit from. The
     * first occurrence of an ancestor field will be returned if at all.
     *
     * @param string $field Name of the parent folder
     *
     * @return array
     */
    public function inheritedField($field)
    {
        [$inherited, $currentParams] = $this->getInheritedParams($field);

        return $currentParams;
    }

    /**
     * Method that contains shared logic for inherited() and inheritedField()
     *
     * @param string $field Name of the parent folder
     * @return array
     */
    protected function getInheritedParams($field)
    {
        $pages = Grav::instance()['pages'];

        /** @var Pages $pages */
        $inherited = $pages->inherited($this->route, $field);
        $inheritedParams = $inherited ? (array)$inherited->value('header.' . $field) : [];
        $currentParams = (array)$this->value('header.' . $field);
        if ($inheritedParams && is_array($inheritedParams)) {
            $currentParams = array_replace_recursive($inheritedParams, $currentParams);
        }

        return [$inherited, $currentParams];
    }

    /**
     * Helper method to return a page.
     *
     * @param string $url the url of the page
     * @param bool $all
     *
     * @return PageInterface page you were looking for if it exists
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
     * @param bool $pagination
     *
     * @return PageCollectionInterface|Collection
     * @throws InvalidArgumentException
     */
    public function collection($params = 'content', $pagination = true)
    {
        if (is_string($params)) {
            // Look into a page header field.
            $params = (array)$this->value('header.' . $params);
        } elseif (!is_array($params)) {
            throw new InvalidArgumentException('Argument should be either header variable name or array of parameters');
        }

        $params['filter'] = ($params['filter'] ?? []) + ['translated' => true];
        $context = [
            'pagination' => $pagination,
            'self' => $this
        ];

        /** @var Pages $pages */
        $pages = Grav::instance()['pages'];

        return $pages->getCollection($params, $context);
    }

    /**
     * @param string|array $value
     * @param bool $only_published
     * @return PageCollectionInterface|Collection
     */
    public function evaluate($value, $only_published = true)
    {
        $params = [
            'items' => $value,
            'published' => $only_published
        ];
        $context = [
            'event' => false,
            'pagination' => false,
            'url_taxonomy_filters' => false,
            'self' => $this
        ];

        /** @var Pages $pages */
        $pages = Grav::instance()['pages'];

        return $pages->getCollection($params, $context);
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
     * @return bool
     */
    public function isModule(): bool
    {
        return $this->modularTwig();
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
     * Returns whether or not the current folder exists
     *
     * @return bool
     */
    public function folderExists()
    {
        return file_exists($this->path());
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
     * Reorders all siblings according to a defined order
     *
     * @param array|null $new_order
     */
    protected function doReorder($new_order)
    {
        if (!$this->_original) {
            return;
        }

        $pages = Grav::instance()['pages'];
        $pages->init();

        $this->_original->path($this->path());

        $parent = $this->parent();
        $siblings = $parent ? $parent->children() : null;

        if ($siblings) {
            $siblings->order('slug', 'asc', $new_order);

            $counter = 0;

            // Reorder all moved pages.
            foreach ($siblings as $slug => $page) {
                $order = (int)trim($page->order(), '.');
                $counter++;

                if ($order) {
                    if ($page->path() === $this->path() && $this->folderExists()) {
                        // Handle current page; we do want to change ordering number, but nothing else.
                        $this->order($counter);
                        $this->save(false);
                    } else {
                        // Handle all the other pages.
                        $page = $pages->get($page->path());
                        if ($page && $page->folderExists() && !$page->_action) {
                            $page = $page->move($this->parent());
                            $page->order($counter);
                            $page->save(false);
                        }
                    }
                }
            }
        }
    }

    /**
     * Moves or copies the page in filesystem.
     *
     * @internal
     * @return void
     * @throws Exception
     */
    protected function doRelocation()
    {
        if (!$this->_original) {
            return;
        }

        if (is_dir($this->_original->path())) {
            if ($this->_action === 'move') {
                Folder::move($this->_original->path(), $this->path());
            } elseif ($this->_action === 'copy') {
                Folder::copy($this->_original->path(), $this->path());
            }
        }

        if ($this->name() !== $this->_original->name()) {
            $path = $this->path();
            if (is_file($path . '/' . $this->_original->name())) {
                rename($path . '/' . $this->_original->name(), $path . '/' . $this->name());
            }
        }
    }

    /**
     * @return void
     */
    protected function setPublishState()
    {
        // Handle publishing dates if no explicit published option set
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
            if ($this->publishDate() && $this->publishDate() > time()) {
                $this->published(false);
                Grav::instance()['cache']->setLifeTime($this->publishDate());
            }
        }
    }

    /**
     * @param string $route
     * @return string
     */
    protected function adjustRouteCase($route)
    {
        $case_insensitive = Grav::instance()['config']->get('system.force_lowercase_urls');

        return $case_insensitive ? mb_strtolower($route) : $route;
    }

    /**
     * Gets the Page Unmodified (original) version of the page.
     *
     * @return PageInterface The original version of the page.
     */
    public function getOriginal()
    {
        return $this->_original;
    }

    /**
     * Gets the action.
     *
     * @return string|null The Action string.
     */
    public function getAction()
    {
        return $this->_action;
    }
}
