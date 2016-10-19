<?php
/**
 * @package    Grav.Common.Helpers
 *
 * @copyright  Copyright (C) 2014 - 2016 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Helpers;

use Grav\Common\Grav;
use Grav\Common\Uri;
use Grav\Common\Utils;
use Grav\Common\Page\Medium\Medium;
use RocketTheme\Toolbox\Event\Event;

class Excerpts
{
    /**
     * Process Grav image media URL from HTML tag
     *
     * @param $html         HTML tag e.g. `<img src="image.jpg" />`
     * @param $page         The current page object
     * @return string       Returns final HTML string
     */
    public static function processImageHtml($html, $page)
    {
        $excerpt = static::getExcerptFromHtml($html, 'img');

        $original_src = $excerpt['element']['attributes']['src'];
        $excerpt['element']['attributes']['href'] = $original_src;

        $excerpt = static::processLinkExcerpt($excerpt, $page, 'image');

        $excerpt['element']['attributes']['src'] = $excerpt['element']['attributes']['href'];
        unset ($excerpt['element']['attributes']['href']);

        $excerpt = static::processImageExcerpt($excerpt, $page);

        $excerpt['element']['attributes']['data-src'] = $original_src;

        $html = static::getHtmlFromExcerpt($excerpt);

        return $html;
    }

    /**
     * Get an Excerpt array from a chunk of HTML
     *
     * @param $html         Chunk of HTML
     * @param $tag          a tag, for example `img`
     * @return array|null   returns nested array excerpt
     */
    public static function getExcerptFromHtml($html, $tag)
    {
        $doc = new \DOMDocument();
        $doc->loadHtml($html);
        $images = $doc->getElementsByTagName($tag);
        $excerpt = null;

        foreach ($images as $image) {
            $attributes = [];
            foreach ($image->attributes as $name => $value) {
                $attributes[$name] = $value->value;
            }
            $excerpt = [
                'element' => [
                    'name'       => $image->tagName,
                    'attributes' => $attributes
                ]
            ];
        }

        return $excerpt;
    }

    /**
     * Rebuild HTML tag from an excerpt array
     *
     * @param $excerpt
     * @return string
     */
    public static function getHtmlFromExcerpt($excerpt)
    {
        $element = $excerpt['element'];
        $html = '<'.$element['name'];

        if (isset($element['attributes'])) {
            foreach ($element['attributes'] as $name => $value) {
                if ($value === null) {
                    continue;
                }
                $html .= ' '.$name.'="'.$value.'"';
            }
        }

        if (isset($element['text'])) {
            $html .= '>';
            $html .= $element['text'];
            $html .= '</'.$element['name'].'>';
        } else {
            $html .= ' />';
        }

        return $html;
    }

    /**
     * Process a Link excerpt
     *
     * @param $excerpt
     * @param $page
     * @param string $type
     * @return mixed
     */
    public static function processLinkExcerpt($excerpt, $page, $type = 'link')
    {
        $url = $excerpt['element']['attributes']['href'];

        $url_parts = parse_url(htmlspecialchars_decode(urldecode($url)));

        // if there is a query, then parse it and build action calls
        if (isset($url_parts['query'])) {
            $actions = array_reduce(explode('&', $url_parts['query']), function ($carry, $item) {
                $parts = explode('=', $item, 2);
                $value = isset($parts[1]) ? rawurldecode($parts[1]) : true;
                $carry[$parts[0]] = $value;

                return $carry;
            }, []);

            // valid attributes supported
            $valid_attributes = ['rel', 'target', 'id', 'class', 'classes'];

            // Unless told to not process, go through actions
            if (array_key_exists('noprocess', $actions)) {
                unset($actions['noprocess']);
            } else {
                // loop through actions for the image and call them
                foreach ($actions as $attrib => $value) {
                    $key = $attrib;

                    if (in_array($attrib, $valid_attributes)) {
                        // support both class and classes
                        if ($attrib == 'classes') {
                            $attrib = 'class';
                        }
                        $excerpt['element']['attributes'][$attrib] = str_replace(',', ' ', $value);
                        unset($actions[$key]);
                    }
                }
            }

            $url_parts['query'] = http_build_query($actions, null, '&', PHP_QUERY_RFC3986);
        }

        // if no query elements left, unset query
        if (empty($url_parts['query'])) {
            unset ($url_parts['query']);
        }

        // set path to / if not set
        if (empty($url_parts['path'])) {
            $url_parts['path'] = '';
        }

        // if special scheme, just return
        if(isset($url_parts['scheme']) && !Utils::startsWith($url_parts['scheme'], 'http')) {
            return $excerpt;
        }

        // handle paths and such
        $url_parts = Uri::convertUrl($page, $url_parts, $type);

        // build the URL from the component parts and set it on the element
        $excerpt['element']['attributes']['href'] = Uri::buildUrl($url_parts);

        return $excerpt;
    }

    /**
     * Process an image excerpt
     *
     * @param $excerpt
     * @param $page
     * @return mixed
     */
    public static function processImageExcerpt($excerpt, $page)
    {
        $url = $excerpt['element']['attributes']['src'];

        $url_parts = parse_url(htmlspecialchars_decode(urldecode($url)));

        if (isset($url_parts['scheme']) && !Utils::startsWith($url_parts['scheme'], 'http')) {
            $stream_path = $url_parts['scheme'] . '://' . $url_parts['host'] . $url_parts['path'];
            $url_parts['path'] = $stream_path;
            unset($url_parts['host']);
            unset($url_parts['scheme']);
        }

        $this_host = isset($url_parts['host']) && $url_parts['host'] == Grav::instance()['uri']->host();

        // if there is no host set but there is a path, the file is local
        if ((!isset($url_parts['host']) || $this_host) && isset($url_parts['path'])) {

            $path_parts = pathinfo($url_parts['path']);
            $media = null;

            // get the local path to page media if possible
            if ($path_parts['dirname'] == $page->url(false, false, false)) {
                // get the media objects for this page
                $media = $page->media();
            } else {
                // see if this is an external page to this one
                $base_url = rtrim(Grav::instance()['base_url_relative'] . Grav::instance()['pages']->base(), '/');
                $page_route = '/' . ltrim(str_replace($base_url, '', $path_parts['dirname']), '/');

                $ext_page = Grav::instance()['pages']->dispatch($page_route, true);
                if ($ext_page) {
                    $media = $ext_page->media();
                } else {
                    Grav::instance()->fireEvent('onMediaLocate', new Event(['route' => $page_route, 'media' => &$media]));
                }
            }

            // if there is a media file that matches the path referenced..
            if ($media && isset($media->all()[$path_parts['basename']])) {
                // get the medium object
                /** @var Medium $medium */
                $medium = $media->all()[$path_parts['basename']];

                // Process operations
                $medium = static::processMediaActions($medium, $url_parts);

                $alt = isset($excerpt['element']['attributes']['alt']) ? $excerpt['element']['attributes']['alt'] : '';
                $title = isset($excerpt['element']['attributes']['title']) ? $excerpt['element']['attributes']['title'] : '';
                $class = isset($excerpt['element']['attributes']['class']) ? $excerpt['element']['attributes']['class'] : '';
                $id = isset($excerpt['element']['attributes']['id']) ? $excerpt['element']['attributes']['id'] : '';

                $excerpt['element'] = $medium->parseDownElement($title, $alt, $class, $id, true);

            } else {
                // not a current page media file, see if it needs converting to relative
                $excerpt['element']['attributes']['src'] = Uri::buildUrl($url_parts);
            }
        }

        return $excerpt;
    }

    /**
     * Process media actions
     *
     * @param $medium
     * @param $url
     * @return mixed
     */
    public static function processMediaActions($medium, $url)
    {
        if (!is_array($url)) {
            $url_parts = parse_url($url);
        } else {
            $url_parts = $url;
        }

        $actions = [];

        // if there is a query, then parse it and build action calls
        if (isset($url_parts['query'])) {
            $actions = array_reduce(explode('&', $url_parts['query']), function ($carry, $item) {
                $parts = explode('=', $item, 2);
                $value = isset($parts[1]) ? $parts[1] : null;
                $carry[] = ['method' => $parts[0], 'params' => $value];

                return $carry;
            }, []);
        }

        if (Grav::instance()['config']->get('system.images.auto_fix_orientation')) {
            $actions[] = ['method' => 'fixOrientation', 'params' => ''];
        }

        // loop through actions for the image and call them
        foreach ($actions as $action) {
            $medium = call_user_func_array([$medium, $action['method']],
                explode(',', $action['params']));
        }

        if (isset($url_parts['fragment'])) {
            $medium->urlHash($url_parts['fragment']);
        }

        return $medium;
    }

}
