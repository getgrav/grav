<?php

/**
 * @package    Grav\Framework\Flex
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Flex\Pages\Traits;

use Exception;
use Grav\Common\Config\Config;
use Grav\Common\Grav;
use Grav\Common\Markdown\Parsedown;
use Grav\Common\Markdown\ParsedownExtra;
use Grav\Common\Page\Header;
use Grav\Common\Page\Interfaces\PageInterface;
use Grav\Common\Page\Markdown\Excerpts;
use Grav\Common\Page\Media;
use Grav\Common\Twig\Twig;
use Grav\Common\Utils;
use Grav\Framework\File\Formatter\YamlFormatter;
use RocketTheme\Toolbox\Event\Event;
use stdClass;
use function in_array;
use function is_array;
use function is_string;

/**
 * Implements PageContentInterface.
 */
trait PageContentTrait
{
    /** @var array */
    protected static $headerProperties = [
        'slug'              => 'slug',
        'routes'            => false,
        'title'             => 'title',
        'language'          => 'language',
        'template'          => 'template',
        'menu'              => 'menu',
        'routable'          => 'routable',
        'visible'           => 'visible',
        'redirect'          => 'redirect',
        'external_url'      => false,
        'order_dir'         => 'orderDir',
        'order_by'          => 'orderBy',
        'order_manual'      => 'orderManual',
        'dateformat'        => 'dateformat',
        'date'              => 'date',
        'markdown_extra'    => false,
        'taxonomy'          => 'taxonomy',
        'max_count'         => 'maxCount',
        'process'           => 'process',
        'published'         => 'published',
        'publish_date'      => 'publishDate',
        'unpublish_date'    => 'unpublishDate',
        'expires'           => 'expires',
        'cache_control'     => 'cacheControl',
        'etag'              => 'eTag',
        'last_modified'     => 'lastModified',
        'ssl'               => 'ssl',
        'template_format'   => 'templateFormat',
        'debugger'          => false,
    ];

    /** @var array */
    protected static $calculatedProperties = [
        'name' => 'name',
        'parent' => 'parent',
        'parent_key' => 'parentStorageKey',
        'folder' => 'folder',
        'order' => 'order',
        'template' => 'template',
    ];

    /** @var object|null */
    protected $header;

    /** @var string|null */
    protected $_summary;

    /** @var string|null */
    protected $_content;

    /**
     * Method to normalize the route.
     *
     * @param string $route
     * @return string
     * @internal
     */
    public static function normalizeRoute($route): string
    {
        $case_insensitive = Grav::instance()['config']->get('system.force_lowercase_urls');

        return $case_insensitive ? mb_strtolower($route) : $route;
    }

    /**
     * @inheritdoc
     * @return Header
     */
    public function header($var = null)
    {
        if (null !== $var) {
            $this->setProperty('header', $var);
        }

        return $this->getProperty('header');
    }

    /**
     * @inheritdoc
     */
    public function summary($size = null, $textOnly = false): string
    {
        return $this->processSummary($size, $textOnly);
    }

    /**
     * @inheritdoc
     */
    public function setSummary($summary): void
    {
        $this->_summary = $summary;
    }

    /**
     * @inheritdoc
     * @throws Exception
     */
    public function content($var = null): string
    {
        if (null !== $var) {
            $this->_content = $var;
        }

        return $this->_content ?? $this->processContent($this->getRawContent());
    }

    /**
     * @inheritdoc
     */
    public function getRawContent(): string
    {
        return $this->_content ?? $this->getArrayProperty('markdown') ?? '';
    }

    /**
     * @inheritdoc
     */
    public function setRawContent($content): void
    {
        $this->_content = $content ?? '';
    }

    /**
     * @inheritdoc
     */
    public function rawMarkdown($var = null): string
    {
        if ($var !== null) {
            $this->setProperty('markdown', $var);
        }

        return $this->getProperty('markdown') ?? '';
    }

    /**
     * @inheritdoc
     *
     * Implement by calling:
     *
     * $test = new \stdClass();
     * $value = $this->pageContentValue($name, $test);
     * if ($value !== $test) {
     *     return $value;
     * }
     * return parent::value($name, $default);
     */
    abstract public function value($name, $default = null, $separator = null);

    /**
     * @inheritdoc
     */
    public function media($var = null): Media
    {
        if ($var instanceof Media) {
            $this->setProperty('media', $var);
        }

        return $this->getProperty('media');
    }

    /**
     * @inheritdoc
     */
    public function title($var = null): string
    {
        return $this->loadHeaderProperty(
            'title',
            $var,
            function ($value) {
                return trim($value ?? ($this->root() ? '<root>' : ucfirst($this->slug())));
            }
        );
    }

    /**
     * @inheritdoc
     */
    public function menu($var = null): string
    {
        return $this->loadHeaderProperty(
            'menu',
            $var,
            function ($value) {
                return trim($value ?: $this->title());
            }
        );
    }

    /**
     * @inheritdoc
     */
    public function visible($var = null): bool
    {
        $value = $this->loadHeaderProperty(
            'visible',
            $var,
            function ($value) {
                return ($value ?? $this->order() !== false) && !$this->isModule();
            }
        );

        return $value && $this->published();
    }

    /**
     * @inheritdoc
     */
    public function published($var = null): bool
    {
        return $this->loadHeaderProperty(
            'published',
            $var,
            static function ($value) {
                return (bool)($value ?? true);
            }
        );
    }

    /**
     * @inheritdoc
     */
    public function publishDate($var = null): ?int
    {
        return $this->loadHeaderProperty(
            'publish_date',
            $var,
            function ($value) {
                return $value ? Utils::date2timestamp($value, $this->getProperty('dateformat')) : null;
            }
        );
    }

    /**
     * @inheritdoc
     */
    public function unpublishDate($var = null): ?int
    {
        return $this->loadHeaderProperty(
            'unpublish_date',
            $var,
            function ($value) {
                return $value ? Utils::date2timestamp($value, $this->getProperty('dateformat')) : null;
            }
        );
    }

    /**
     * @inheritdoc
     */
    public function process($var = null): array
    {
        return $this->loadHeaderProperty(
            'process',
            $var,
            function ($value) {
                $value = array_replace(Grav::instance()['config']->get('system.pages.process', []), is_array($value) ? $value : []);
                foreach ($value as $process => $status) {
                    $value[$process] = (bool)$status;
                }

                return $value;
            }
        );
    }

    /**
     * @inheritdoc
     */
    public function slug($var = null)
    {
        return $this->loadHeaderProperty(
            'slug',
            $var,
            function ($value) {
                if (is_string($value)) {
                    return $value;
                }

                $folder = $this->folder();
                if (null === $folder) {
                    return null;
                }

                $folder = preg_replace(static::PAGE_ORDER_PREFIX_REGEX, '', $folder);
                if (null === $folder) {
                    return null;
                }

                return static::normalizeRoute($folder);
            }
        );
    }

    /**
     * @inheritdoc
     */
    public function order($var = null)
    {
        $property = $this->loadProperty(
            'order',
            $var,
            function ($value) {
                if (null === $value) {
                    $folder = $this->folder();
                    if (null !== $folder) {
                        preg_match(static::PAGE_ORDER_REGEX, $folder, $order);
                    }

                    $value = $order[1] ?? false;
                }

                if ($value === '') {
                    $value = false;
                }
                if ($value !== false) {
                    $value = (int)$value;
                }

                return $value;
            }
        );

        return $property !== false ? sprintf('%02d.', $property) : false;
    }

    /**
     * @inheritdoc
     */
    public function id($var = null): string
    {
        $property = 'id';
        $value = null === $var ? $this->getProperty($property) : null;
        if (null === $value) {
            $value = $this->language() . ($var ?? ($this->modified() . md5('flex-' . $this->getFlexType() . '-' . $this->getKey())));

            $this->setProperty($property, $value);
            if ($this->doHasProperty($property)) {
                $value = $this->getProperty($property);
            }
        }

        return $value;
    }

    /**
     * @inheritdoc
     */
    public function modified($var = null): int
    {
        $property = 'modified';
        $value = null === $var ? $this->getProperty($property) : null;
        if (null === $value) {
            $value = (int)($var ?: $this->getTimestamp());

            $this->setProperty($property, $value);
            if ($this->doHasProperty($property)) {
                $value = $this->getProperty($property);
            }
        }

        return $value;
    }

    /**
     * @inheritdoc
     */
    public function lastModified($var = null): bool
    {
        return $this->loadHeaderProperty(
            'last_modified',
            $var,
            static function ($value) {
                return (bool)($value ?? Grav::instance()['config']->get('system.pages.last_modified'));
            }
        );
    }

    /**
     * @inheritdoc
     */
    public function date($var = null): int
    {
        return $this->loadHeaderProperty(
            'date',
            $var,
            function ($value) {
                $value = $value ? Utils::date2timestamp($value, $this->getProperty('dateformat')) : false;

                return $value ?: $this->modified();
            }
        );
    }

    /**
     * @inheritdoc
     */
    public function dateformat($var = null): ?string
    {
        return $this->loadHeaderProperty(
            'dateformat',
            $var,
            static function ($value) {
                return $value;
            }
        );
    }

    /**
     * @inheritdoc
     */
    public function taxonomy($var = null): array
    {
        return $this->loadHeaderProperty(
            'taxonomy',
            $var,
            static function ($value) {
                if (is_array($value)) {
                    // make sure first level are arrays
                    array_walk($value, static function (&$val) {
                        $val = (array) $val;
                    });
                    // make sure all values are strings
                    array_walk_recursive($value, static function (&$val) {
                        $val = (string) $val;
                    });
                }

                return $value ?? [];
            }
        );
    }

    /**
     * @inheritdoc
     */
    public function shouldProcess($process): bool
    {
        $test = $this->process();

        return !empty($test[$process]);
    }

    /**
     * @inheritdoc
     */
    public function isPage(): bool
    {
        return !in_array($this->template(), ['', 'folder'], true);
    }

    /**
     * @inheritdoc
     */
    public function isDir(): bool
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
     * @param Header|stdClass|array|null $value
     * @return Header
     */
    protected function offsetLoad_header($value)
    {
        if ($value instanceof Header) {
            return $value;
        }

        if (null === $value) {
            $value = [];
        } elseif ($value instanceof stdClass) {
            $value = (array)$value;
        }

        return new Header($value);
    }

    /**
     * @param Header|stdClass|array|null $value
     * @return Header
     */
    protected function offsetPrepare_header($value)
    {
        return $this->offsetLoad_header($value);
    }

    /**
     * @param Header|null $value
     * @return array
     */
    protected function offsetSerialize_header(?Header $value)
    {
        return $value ? $value->toArray() : [];
    }

    /**
     * @param string $name
     * @param mixed|null $default
     * @return mixed
     */
    protected function pageContentValue($name, $default = null)
    {
        switch ($name) {
            case 'frontmatter':
                $frontmatter = $this->getArrayProperty('frontmatter');
                if ($frontmatter === null) {
                    $header = $this->prepareStorage()['header'] ?? null;
                    if ($header) {
                        $formatter = new YamlFormatter();
                        $frontmatter = $formatter->encode($header);
                    } else {
                        $frontmatter = '';
                    }
                }
                return $frontmatter;
            case 'content':
                return $this->getProperty('markdown');
            case 'order':
                return (string)$this->order();
            case 'menu':
                return $this->menu();
            case 'ordering':
                return $this->order() !== false ? '1' : '0';
            case 'folder':
                $folder = $this->folder();

                return null !== $folder ? preg_replace(static::PAGE_ORDER_PREFIX_REGEX, '', $folder) : '';
            case 'slug':
                return $this->slug();
            case 'published':
                return $this->published();
            case 'visible':
                return $this->visible();
            case 'media':
                return $this->media()->all();
            case 'media.file':
                return $this->media()->files();
            case 'media.video':
                return $this->media()->videos();
            case 'media.image':
                return $this->media()->images();
            case 'media.audio':
                return $this->media()->audios();
        }

        return $default;
    }

    /**
     * @param int|null $size
     * @param bool $textOnly
     * @return string
     */
    protected function processSummary($size = null, $textOnly = false): string
    {
        $config = (array)Grav::instance()['config']->get('site.summary');
        $config_page = (array)$this->getNestedProperty('header.summary');
        if ($config_page) {
            $config = array_merge($config, $config_page);
        }

        // Summary is not enabled, return the whole content.
        if (empty($config['enabled'])) {
            return $this->content();
        }

        $content = $this->_summary ?? $this->content();
        if ($textOnly) {
            $content =  strip_tags($content);
        }
        $content_size = mb_strwidth($content, 'utf-8');
        $summary_size = $this->_summary !== null ? $content_size : $this->getProperty('summary_size');

        // Return calculated summary based on summary divider's position.
        $format = $config['format'] ?? '';

        // Return entire page content on wrong/unknown format.
        if ($format !== 'long' && $format !== 'short') {
            return $content;
        }

        if ($format === 'short' && null !== $summary_size) {
            // Slice the string on breakpoint.
            if ($content_size > $summary_size) {
                return mb_substr($content, 0, $summary_size);
            }

            return $content;
        }

        // If needed, get summary size from the config.
        $size = $size ?? $config['size'] ?? null;

        // Return calculated summary based on defaults.
        $size = is_numeric($size) ? (int)$size : -1;
        if ($size < 0) {
            $size = 300;
        }

        // If the size is zero or smaller than the summary limit, return the entire page content.
        if ($size === 0 || $content_size <= $size) {
            return $content;
        }

        // Only return string but not html, wrap whatever html tag you want when using.
        if ($textOnly) {
            return mb_strimwidth($content, 0, $size, '...', 'UTF-8');
        }

        $summary = Utils::truncateHTML($content, $size);

        return html_entity_decode($summary, ENT_COMPAT | ENT_HTML5, 'UTF-8');
    }

    /**
     * Gets and Sets the content based on content portion of the .md file
     *
     * @param  string $content
     * @return string
     * @throws Exception
     */
    protected function processContent($content): string
    {
        $content = is_string($content) ? $content : '';
        $grav = Grav::instance();

        /** @var Config $config */
        $config = $grav['config'];

        $process_markdown = $this->shouldProcess('markdown');
        $process_twig = $this->shouldProcess('twig') || $this->isModule();
        $cache_enable = $this->getNestedProperty('header.cache_enable') ?? $config->get('system.cache.enabled', true);

        $twig_first = $this->getNestedProperty('header.twig_first') ?? $config->get('system.pages.twig_first', false);
        $never_cache_twig = $this->getNestedProperty('header.never_cache_twig') ?? $config->get('system.pages.never_cache_twig', false);

        if ($cache_enable) {
            $cache = $this->getCache('render');
            $key = md5($this->getCacheKey() . '-content');
            $cached = $cache->get($key);
            if ($cached && $cached['checksum'] === $this->getCacheChecksum()) {
                $this->_content = $cached['content'] ?? '';
                $this->_content_meta = $cached['content_meta'] ?? null;

                if ($process_twig && $never_cache_twig) {
                    $this->_content = $this->processTwig($this->_content);
                }
            }
        }

        if (null === $this->_content) {
            $markdown_options = [];
            if ($process_markdown) {
                // Build markdown options.
                $markdown_options = (array)$config->get('system.pages.markdown');
                $markdown_page_options = (array)$this->getNestedProperty('header.markdown');
                if ($markdown_page_options) {
                    $markdown_options = array_merge($markdown_options, $markdown_page_options);
                }

                // pages.markdown_extra is deprecated, but still check it...
                if (!isset($markdown_options['extra'])) {
                    $extra = $this->getNestedProperty('header.markdown_extra') ?? $config->get('system.pages.markdown_extra');
                    if (null !== $extra) {
                        user_error('Configuration option \'system.pages.markdown_extra\' is deprecated since Grav 1.5, use \'system.pages.markdown.extra\' instead', E_USER_DEPRECATED);

                        $markdown_options['extra'] = $extra;
                    }
                }
            }
            $options = [
                'markdown' => $markdown_options,
                'images' => $config->get('system.images', [])
            ];

            $this->_content = $content;
            $grav->fireEvent('onPageContentRaw', new Event(['page' => $this]));

            if ($twig_first && !$never_cache_twig) {
                if ($process_twig) {
                    $this->_content = $this->processTwig($this->_content);
                }

                if ($process_markdown) {
                    $this->_content = $this->processMarkdown($this->_content, $options);
                }

                // Content Processed but not cached yet
                $grav->fireEvent('onPageContentProcessed', new Event(['page' => $this]));
            } else {
                if ($process_markdown) {
                    $options['keep_twig'] = $process_twig;
                    $this->_content = $this->processMarkdown($this->_content, $options);
                }

                // Content Processed but not cached yet
                $grav->fireEvent('onPageContentProcessed', new Event(['page' => $this]));

                if ($cache_enable && $never_cache_twig) {
                    $this->cachePageContent();
                }

                if ($process_twig) {
                    \assert(is_string($this->_content));
                    $this->_content = $this->processTwig($this->_content);
                }
            }

            if ($cache_enable && !$never_cache_twig) {
                $this->cachePageContent();
            }
        }

        \assert(is_string($this->_content));

        // Handle summary divider
        $delimiter = $config->get('site.summary.delimiter', '===');
        $divider_pos = mb_strpos($this->_content, "<p>{$delimiter}</p>");
        if ($divider_pos !== false) {
            $this->setProperty('summary_size', $divider_pos);
            $this->_content = str_replace("<p>{$delimiter}</p>", '', $this->_content);
        }

        // Fire event when Page::content() is called
        $grav->fireEvent('onPageContent', new Event(['page' => $this]));

        return $this->_content;
    }

    /**
     * Process the Twig page content.
     *
     * @param  string $content
     * @return string
     */
    protected function processTwig($content): string
    {
        /** @var Twig $twig */
        $twig = Grav::instance()['twig'];

        /** @var PageInterface $this */
        return $twig->processPage($this, $content);
    }

    /**
     * Process the Markdown content.
     *
     * Uses Parsedown or Parsedown Extra depending on configuration.
     *
     * @param string $content
     * @param array  $options
     * @return string
     * @throws Exception
     */
    protected function processMarkdown($content, array $options = []): string
    {
        /** @var PageInterface $self */
        $self = $this;

        $excerpts = new Excerpts($self, $options);

        // Initialize the preferred variant of markdown parser.
        if (isset($options['extra'])) {
            $parsedown = new ParsedownExtra($excerpts);
        } else {
            $parsedown = new Parsedown($excerpts);
        }

        $keepTwig = (bool)($options['keep_twig'] ?? false);
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

        return $content;
    }

    abstract protected function loadHeaderProperty(string $property, $var, callable $filter);
}
