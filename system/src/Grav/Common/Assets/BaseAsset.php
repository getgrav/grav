<?php
/**
 * @package    Grav.Common.Assets
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Assets;

use Grav\Common\Grav;
use Grav\Common\Uri;
use Grav\Common\Utils;
use Grav\Framework\Object\PropertyObject;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

abstract class BaseAsset extends PropertyObject
{
//    protected $base_url;

    protected $asset;

    protected $asset_type;
    protected $order;
    protected $group;
    protected $position;
    protected $priority;
    protected $attributes = [];

    protected $base_url;
    protected $timestamp;
    protected $modified;
    protected $remote;
    protected $query = '';

    abstract function render();

    public function __construct(array $elements = [], $key = null)
    {
        $base_config = [
            'group' => 'head',
            'position' => 'pipeline',
            'priority' => 10
        ];

        // Merge base defaults
        $elements = array_merge($base_config, $elements);

        parent::__construct($elements, $key);
    }

    public function init($asset, $options)
    {
        $config = Grav::instance()['config'];

        // set attributes
        foreach ($options as $key => $value) {
            if ($this->hasProperty($key)) {
                $this->setProperty($key, $value);
            } else {
                $this->attributes[$key] = $value;
            }
        }

        // Do some special stuff for CSS/JS (not inline)
        if (!Utils::startsWith($this->getType(), 'inline')) {
            $this->base_url = Grav::instance()['uri']->rootUrl($config->get('system.absolute_urls'));
            $this->remote = $this->isRemoteLink($asset);

            // Move this to render?
            if (!$this->remote) {

                $asset_parts = parse_url($asset);
                if (isset($asset_parts['query'])) {
                    $this->query = $asset_parts['query'];
                    unset($asset_parts['query']);
                    $asset = Uri::buildUrl($asset_parts);
                }

                $this->modified = $this->getLastModificationTime($asset);
                $asset = $this->buildLocalLink($asset);
            }
        }

        $this->asset = $asset;

//
//        $data = [
//            'asset'    => $asset,
//            'remote'   => $remote,
//            'priority' => intval($priority ?: 10),
//            'order'    => count($assembly),
//            'pipeline' => (bool) $pipeline,
//            'loading'  => $loading ?: '',
//            'group'    => $group ?: 'head',
//            'modified' => $modified,
//            'query'    => implode('&', $query),
//        ];
//
//        // check for dynamic array and merge with defaults
//        if (func_num_args() > 2) {
//            $dynamic_arg = func_get_arg(2);
//            if (is_array($dynamic_arg)) {
//                $data = array_merge($data, $dynamic_arg);
//            }
//        }

        return $this;
    }



    /**
     *
     * Get the last modification time of asset
     *
     * @param  string $asset    the asset string reference
     *
     * @return string           the last modifcation time or false on error
     */
    protected function getLastModificationTime($asset)
    {
        $file = GRAV_ROOT . $asset;
        if (Grav::instance()['locator']->isStream($asset)) {
            $file = $this->buildLocalLink($asset, true);
        }

        return file_exists($file) ? filemtime($file) : false;
    }

    /**
     *
     * Build local links including grav asset shortcodes
     *
     * @param  string $asset    the asset string reference
     * @param  bool   $absolute build absolute asset link
     *
     * @return string           the final link url to the asset
     */
    protected function buildLocalLink($asset, $absolute = false)
    {
        /** @var UniformResourceLocator $locator */
        $locator = Grav::instance()['locator'];

        if ($locator->isStream($asset)) {
            $asset = $locator->findResource($asset, $absolute);
        }

        $uri = $absolute ? $asset : $this->base_url . '/' . ltrim($asset, '/');


        return $asset ? $uri : false;
    }

    /**
     *
     * Determine whether a link is local or remote.
     *
     * Understands both "http://" and "https://" as well as protocol agnostic links "//"
     *
     * @param  string $link
     *
     * @return bool
     */
    public static function isRemoteLink($link)
    {
        $base = Grav::instance()['uri']->rootUrl(true);

        // sanity check for local URLs with absolute URL's enabled
        if (Utils::startsWith($link, $base)) {
            return false;
        }

        return ('http://' === substr($link, 0, 7) || 'https://' === substr($link, 0, 8) || '//' === substr($link, 0,
                2));
    }

    /**
     * TODO: Do we need?
     *
     * Build an HTML attribute string from an array.
     *
     * @param  array $attributes
     *
     * @return string
     */
    protected function renderAttributes()
    {
        $html = '';
        $no_key = ['loading'];

        foreach ($this->attributes as $key => $value) {
            if (is_numeric($key)) {
                $key = $value;
            }
            if (is_array($value)) {
                $value = implode(' ', $value);
            }

            if (in_array($key, $no_key)) {
                $element = htmlentities($value, ENT_QUOTES, 'UTF-8', false);
            } else {
                $element = $key . '="' . htmlentities($value, ENT_QUOTES, 'UTF-8', false) . '"';
            }

            $html .= ' ' . $element;
        }

        return $html;
    }

    protected function renderQueryString()
    {
        $querystring = '';

        if (!empty($this->query)) {
            if (Utils::contains($this->asset, '?')) {
                $querystring .=  '&' . $this->query;
            } else {
                $querystring .= '?' . $this->query;
            }
        }

        if ($this->timestamp) {
            if (Utils::contains($this->asset, '?') || $querystring) {
                $querystring .=  '&' . $this->timestamp;
            } else {
                $querystring .= '?' . $this->timestamp;
            }
        }

        return $querystring;
    }
}
