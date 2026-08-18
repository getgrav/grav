<?php

/**
 * @package    Grav\Common\Assets
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Assets;

use Grav\Common\Assets\Traits\AssetUtilsTrait;
use Grav\Common\Config\Config;
use Grav\Common\Grav;
use Grav\Common\Uri;
use Grav\Common\Utils;
use Grav\Framework\Object\PropertyObject;
use RocketTheme\Toolbox\File\File;
use SplFileInfo;

/**
 * Class BaseAsset
 * @package Grav\Common\Assets
 */
abstract class BaseAsset extends PropertyObject
{
    use AssetUtilsTrait;

    protected const CSS_ASSET = 1;
    protected const JS_ASSET = 2;
    protected const JS_MODULE_ASSET = 3;

    /** @var string|false */
    protected $asset;
    /** @var string */
    protected $asset_type;
    /** @var int */
    protected $order;
    /** @var string */
    protected $group;
    /** @var string */
    protected $position;
    /** @var int */
    protected $priority;
    /** @var array */
    protected $attributes = [];

    /** @var string */
    protected $timestamp;
    /** @var int|false */
    protected $modified;
    /** @var bool */
    protected $remote;
    /** @var string */
    protected $query = '';

    // Private Bits
    /** @var bool */
    private $css_rewrite = false;
    /** @var bool */
    private $css_minify = false;

    /**
     * @return string
     */
    abstract function render();

    /**
     * BaseAsset constructor.
     * @param array $elements
     * @param string|null $key
     */
    public function __construct(array $elements = [], ?string $key = null)
    {
        $base_config = [
            'group' => 'head',
            'position' => 'pipeline',
            'priority' => 10,
            'modified' => null,
            'asset' => null
        ];

        // Merge base defaults
        $elements = array_merge($base_config, $elements);

        parent::__construct($elements, $key);
    }

    /**
     * @param string|false $asset
     * @param array $options
     * @return $this|false
     */
    public function init($asset, $options)
    {
        if (!$asset) {
            return false;
        }

        $config = Grav::instance()['config'];
        $uri = Grav::instance()['uri'];

        // set attributes
        foreach ($options as $key => $value) {
            if ($this->hasProperty($key)) {
                $this->setProperty($key, $value);
            } else {
                $this->attributes[$key] = $value;
            }
        }

        // Force priority to be an int
        $this->priority = (int) $this->priority;

        // Do some special stuff for CSS/JS (not inline)
        if (!Utils::startsWith($this->getType(), 'inline')) {
            $this->base_url = rtrim((string) $uri->rootUrl($config->get('system.absolute_urls')), '/') . '/';
            $this->remote = static::isRemoteLink($asset);

            // Move this to render?
            if (!$this->remote) {
                $asset_parts = parse_url($asset);
                if (isset($asset_parts['query'])) {
                    $this->query = $asset_parts['query'];
                    unset($asset_parts['query']);
                    $asset = Uri::buildUrl($asset_parts);
                }

                $locator = Grav::instance()['locator'];

                if ($locator->isStream($asset)) {
                    $path = $locator->findResource($asset, true);
                } else {
                    $path = GRAV_WEBROOT . $asset;
                }

                // If local file is missing return
                if ($path === false) {
                    return false;
                }

                $file = new SplFileInfo($path);

                $asset = $this->buildLocalLink($file->getPathname());

                $this->modified = $file->isFile() ? $file->getMTime() : false;

                // Rewrite the cache-busting token to this asset's own filemtime
                // instead of the global config/version-derived cache key, so
                // editing one file invalidates only that file's URL.
                //
                // Only ever replaces the token the flag itself generated — the
                // global cache key. An explicit, caller-supplied token set via the
                // public Assets::setTimestamp() API wins outright, whether or not
                // the config flag is on: pinning one release token across a fleet
                // is exactly what that API is for, and a config flag should not be
                // able to silently override an imperative call with no way out.
                //
                // Note: the rendered <link>/<script> tag for a *pipelined* bundle
                // still uses the global cache key (Pipeline::$timestamp, set from
                // Assets::render()), so this has no effect on pipelined output
                // URLs. It does still feed into the pipeline's internal bundle
                // uid hash (Pipeline::renderCss()/renderJs() hash the serialized
                // BaseAsset objects, which include $timestamp) — harmless, but
                // it does mean upgrading can produce one new bundle file instead
                // of reusing the previous one.
                if ($this->timestamp
                    && $this->modified
                    && $config->get('system.assets.enable_asset_timestamp')
                    && $this->timestamp === Grav::instance()['cache']->getKey()
                ) {
                    $this->timestamp = dechex($this->modified);
                }
            }
        }

        $this->asset = $asset;

        return $this;
    }

    /**
     * @return string|false
     */
    public function getAsset()
    {
        return $this->asset;
    }

    /**
     * @return bool
     */
    public function getRemote()
    {
        return $this->remote;
    }

    /**
     * @param string $position
     * @return $this
     */
    public function setPosition($position)
    {
        $this->position = $position;

        return $this;
    }

    /**
     * Receive asset location and return the SRI integrity hash
     *
     * @param string $input
     * @return string
     */
    public static function integrityHash($input)
    {
        $grav = Grav::instance();

        // Check the (default off) SRI flag before doing any URL work; this runs
        // for every rendered asset.
        $assetsConfig = $grav['config']->get('system.assets');
        if (empty($assetsConfig['enable_asset_sri'])) {
            return '';
        }

        $uri = $grav['uri'];

        if (!self::isRemoteLink($input)) {
            $input = preg_replace('#^' . $uri->rootUrl() . '#', '', $input);
            $asset = File::instance(GRAV_WEBROOT . $input);

            if ($asset->exists()) {
                $dataToHash = $asset->content();
                $hash = hash('sha256', $dataToHash, true);
                $hash_base64 = base64_encode($hash);

                return ' integrity="sha256-' . $hash_base64 . '"';
            }
        }

        return '';
    }


    /**
     *
     * Get the last modification time of asset
     *
     * @param  string $asset    the asset string reference
     *
     * @return string           the last modifcation time or false on error
     */
//    protected function getLastModificationTime($asset)
//    {
//        $file = GRAV_WEBROOT . $asset;
//        if (Grav::instance()['locator']->isStream($asset)) {
//            $file = $this->buildLocalLink($asset, true);
//        }
//
//        return file_exists($file) ? filemtime($file) : false;
//    }

    /**
     *
     * Build local links including grav asset shortcodes
     *
     * @param  string $asset    the asset string reference
     *
     * @return string|false     the final link url to the asset
     */
    protected function buildLocalLink($asset)
    {
        if ($asset) {
            return $this->base_url . ltrim(Utils::replaceFirstOccurrence(GRAV_WEBROOT, '', $asset), '/');
        }
        return false;
    }


    /**
     * Implements JsonSerializable interface.
     *
     * @return array
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return ['type' => $this->getType(), 'elements' => $this->getElements()];
    }

    /**
     * Placeholder for AssetUtilsTrait method
     *
     * @param string $file
     * @param string $dir
     * @param bool $local
     * @return string
     */
    protected function cssRewrite($file, $dir, $local)
    {
        return '';
    }

    /**
     * Finds relative JS urls() and rewrites the URL with an absolute one
     *
     * @param string $file the css source file
     * @param string $dir local relative path to the css file
     * @param bool $local is this a local or remote asset
     * @return string
     */
    protected function jsRewrite($file, $dir, $local)
    {
        return '';
    }
}
