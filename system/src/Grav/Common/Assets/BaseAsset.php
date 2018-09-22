<?php
/**
 * @package    Grav.Common.Assets
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Assets;

use Grav\Common\Grav;
use Grav\Common\Assets;
use Grav\Common\Uri;
use Grav\Framework\Object\PropertyObject;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

abstract class BaseAsset extends PropertyObject
{
//    protected $base_url;

    protected $asset;

    protected $group;
    protected $position;
    protected $priority;
    protected $attributes;

    protected $base_url;
    protected $modified;
    protected $remote;
    protected $query;

    abstract function render();

    public function init($asset, $options) {

        // set attributes
        foreach ($options as $key => $value) {
            if ($this->hasProperty($key)) {
                $this->setProperty($key, $value);
            } else {
                $this->attributes[$key] = $value;
            }
        }

        $this->base_url = Grav::instance()['uri']->rootUrl(Grav::instance()['config']->get('system.absolute_urls'));
        $this->remote = Assets::isRemoteLink($asset);

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

        $uri = $absolute ? $asset : $this->base_url . ltrim($asset, '/');


        return $asset ? $uri : false;
    }
}
