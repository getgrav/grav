<?php

/**
 * @package    Grav\Common\Page
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Page\Markdown;

use Grav\Common\Grav;
use Grav\Common\Page\Interfaces\PageInterface;
use Grav\Common\Page\Medium\Link;
use Grav\Common\Uri;
use Grav\Common\Page\Medium\Medium;
use Grav\Common\Utils;
use RocketTheme\Toolbox\Event\Event;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

class Excerpts
{
    /** @var PageInterface */
    protected $page;
    /** @var array */
    protected $config;

    public function __construct(PageInterface $page = null, array $config = null)
    {
        $this->page = $page ?? Grav::instance()['page'] ?? null;

        // Add defaults to the configuration.
        if (null === $config || !isset($config['markdown'], $config['images'])) {
            $c = Grav::instance()['config'];
            $config = $config ?? [];
            $config += [
                'markdown' => $c->get('system.pages.markdown', []),
                'images' => $c->get('system.images', [])
            ];
        }

        $this->config = $config;
    }

    public function getPage(): PageInterface
    {
        return $this->page;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function fireInitializedEvent($markdown): void
    {
        $grav = Grav::instance();

        $grav->fireEvent('onMarkdownInitialized', new Event(['markdown' => $markdown, 'page' => $this->page]));
    }

    /**
     * Process a Link excerpt
     *
     * @param array $excerpt
     * @param string $type
     * @return array
     */
    public function processLinkExcerpt(array $excerpt, string $type = 'link'): array
    {
        $url = htmlspecialchars_decode(rawurldecode($excerpt['element']['attributes']['href']));

        $url_parts = $this->parseUrl($url);

        // If there is a query, then parse it and build action calls.
        if (isset($url_parts['query'])) {
            $actions = array_reduce(
                explode('&', $url_parts['query']),
                static function ($carry, $item) {
                    $parts = explode('=', $item, 2);
                    $value = isset($parts[1]) ? rawurldecode($parts[1]) : true;
                    $carry[$parts[0]] = $value;

                    return $carry;
                },
                []
            );

            // Valid attributes supported.
            $valid_attributes = Grav::instance()['config']->get('system.pages.markdown.valid_link_attributes');

            // Unless told to not process, go through actions.
            if (array_key_exists('noprocess', $actions)) {
                unset($actions['noprocess']);
            } else {
                // Loop through actions for the image and call them.
                foreach ($actions as $attrib => $value) {
                    $key = $attrib;

                    if (in_array($attrib, $valid_attributes, true)) {
                        // support both class and classes.
                        if ($attrib === 'classes') {
                            $attrib = 'class';
                        }
                        $excerpt['element']['attributes'][$attrib] = str_replace(',', ' ', $value);
                        unset($actions[$key]);
                    }
                }
            }

            $url_parts['query'] = http_build_query($actions, null, '&', PHP_QUERY_RFC3986);
        }

        // If no query elements left, unset query.
        if (empty($url_parts['query'])) {
            unset ($url_parts['query']);
        }

        // Set path to / if not set.
        if (empty($url_parts['path'])) {
            $url_parts['path'] = '';
        }

        // If scheme isn't http(s)..
        if (!empty($url_parts['scheme']) && !in_array($url_parts['scheme'], ['http', 'https'])) {
            // Handle custom streams.
            if ($type !== 'image' && !empty($url_parts['stream']) && !empty($url_parts['path'])) {
                $grav = Grav::instance();
                $url_parts['path'] = $grav['base_url_relative'] . '/' . $this->resolveStream("{$url_parts['scheme']}://{$url_parts['path']}");
                unset($url_parts['stream'], $url_parts['scheme']);
            }

            $excerpt['element']['attributes']['href'] = Uri::buildUrl($url_parts);

            return $excerpt;
        }

        // Handle paths and such.
        $url_parts = Uri::convertUrl($this->page, $url_parts, $type);

        // Build the URL from the component parts and set it on the element.
        $excerpt['element']['attributes']['href'] = Uri::buildUrl($url_parts);

        return $excerpt;
    }

    /**
     * Process an image excerpt
     *
     * @param array $excerpt
     * @return array
     */
    public function processImageExcerpt(array $excerpt): array
    {
        $url = htmlspecialchars_decode(urldecode($excerpt['element']['attributes']['src']));
        $url_parts = $this->parseUrl($url);

        $media = null;
        $filename = null;

        if (!empty($url_parts['stream'])) {
            $filename = $url_parts['scheme'] . '://' . ($url_parts['path'] ?? '');

            $media = $this->page->getMedia();

        } else {
            $grav = Grav::instance();

            // File is also local if scheme is http(s) and host matches.
            $local_file = isset($url_parts['path'])
                && (empty($url_parts['scheme']) || in_array($url_parts['scheme'], ['http', 'https'], true))
                && (empty($url_parts['host']) || $url_parts['host'] === $grav['uri']->host());

            if ($local_file) {
                $filename = basename($url_parts['path']);
                $folder = dirname($url_parts['path']);

                // Get the local path to page media if possible.
                if ($this->page && $folder === $this->page->url(false, false, false)) {
                    // Get the media objects for this page.
                    $media = $this->page->getMedia();
                } else {
                    // see if this is an external page to this one
                    $base_url = rtrim($grav['base_url_relative'] . $grav['pages']->base(), '/');
                    $page_route = '/' . ltrim(str_replace($base_url, '', $folder), '/');

                    /** @var PageInterface $ext_page */
                    $ext_page = $grav['pages']->dispatch($page_route, true);
                    if ($ext_page) {
                        $media = $ext_page->getMedia();
                    } else {
                        $grav->fireEvent('onMediaLocate', new Event(['route' => $page_route, 'media' => &$media]));
                    }
                }
            }
        }

        // If there is a media file that matches the path referenced..
        if ($media && $filename && isset($media[$filename])) {
            // Get the medium object.
            /** @var Medium $medium */
            $medium = $media[$filename];

            // Process operations
            $medium = $this->processMediaActions($medium, $url_parts);
            $element_excerpt = $excerpt['element']['attributes'];

            $alt = $element_excerpt['alt'] ?? '';
            $title = $element_excerpt['title'] ?? '';
            $class = $element_excerpt['class'] ?? '';
            $id = $element_excerpt['id'] ?? '';

            $excerpt['element'] = $medium->parsedownElement($title, $alt, $class, $id, true);

        } else {
            // Not a current page media file, see if it needs converting to relative.
            $excerpt['element']['attributes']['src'] = Uri::buildUrl($url_parts);
        }

        return $excerpt;
    }

    /**
     * Process media actions
     *
     * @param Medium $medium
     * @param string|array $url
     * @return Medium|Link
     */
    public function processMediaActions($medium, $url)
    {
        $url_parts = is_string($url) ? $this->parseUrl($url) : $url;
        $actions = [];


        // if there is a query, then parse it and build action calls
        if (isset($url_parts['query'])) {
            $actions = array_reduce(
                explode('&', $url_parts['query']),
                static function ($carry, $item) {
                    $parts = explode('=', $item, 2);
                    $value = $parts[1] ?? null;
                    $carry[] = ['method' => $parts[0], 'params' => $value];

                    return $carry;
                },
                []
            );
        }

        $config = $this->getConfig();
        if (!empty($config['images']['auto_fix_orientation'])) {
            $actions[] = ['method' => 'fixOrientation', 'params' => ''];
        }

        $defaults = $config['images']['defaults'] ?? [];
        if (count($defaults)) {
            foreach ($defaults as $method => $params) {
                $actions[] = [
                    'method' => $method,
                    'params' => $params,
                ];
            }
        }

        // loop through actions for the image and call them
        foreach ($actions as $action) {
            $matches = [];

            if (preg_match('/\[(.*)\]/', $action['params'], $matches)) {
                $args = [explode(',', $matches[1])];
            } else {
                $args = explode(',', $action['params']);
            }

            $medium = call_user_func_array([$medium, $action['method']], $args);
        }

        if (isset($url_parts['fragment'])) {
            $medium->urlHash($url_parts['fragment']);
        }

        return $medium;
    }

    /**
     * Variation of parse_url() which works also with local streams.
     *
     * @param string $url
     * @return array|bool
     */
    protected function parseUrl(string $url)
    {
        $url_parts = Utils::multibyteParseUrl($url);

        if (isset($url_parts['scheme'])) {
            /** @var UniformResourceLocator $locator */
            $locator = Grav::instance()['locator'];

            // Special handling for the streams.
            if ($locator->schemeExists($url_parts['scheme'])) {
                if (isset($url_parts['host'])) {
                    // Merge host and path into a path.
                    $url_parts['path'] = $url_parts['host'] . (isset($url_parts['path']) ? '/' . $url_parts['path'] : '');
                    unset($url_parts['host']);
                }

                $url_parts['stream'] = true;
            }
        }

        return $url_parts;
    }

    /**
     * @param string $url
     * @return bool|string
     */
    protected function resolveStream(string $url)
    {
        /** @var UniformResourceLocator $locator */
        $locator = Grav::instance()['locator'];

        if ($locator->isStream($url)) {
            return $locator->findResource($url, false) ?: $locator->findResource($url, false, true);
        }

        return $url;
    }
}
