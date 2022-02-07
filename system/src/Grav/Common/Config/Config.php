<?php

/**
 * @package    Grav\Common\Config
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Config;

use Grav\Common\Debugger;
use Grav\Common\Grav;
use Grav\Common\Data\Data;
use Grav\Common\Service\ConfigServiceProvider;
use Grav\Common\Utils;
use function is_array;

/**
 * Class Config
 * @package Grav\Common\Config
 */
class Config extends Data
{
    /** @var string */
    public $environment;

    /** @var string */
    protected $key;
    /** @var string */
    protected $checksum;
    /** @var int */
    protected $timestamp = 0;
    /** @var bool */
    protected $modified = false;

    /**
     * @return string
     */
    public function key()
    {
        if (null === $this->key) {
            $this->key = md5($this->checksum . $this->timestamp);
        }

        return $this->key;
    }

    /**
     * @param string|null $checksum
     * @return string|null
     */
    public function checksum($checksum = null)
    {
        if ($checksum !== null) {
            $this->checksum = $checksum;
        }

        return $this->checksum;
    }

    /**
     * @param bool|null $modified
     * @return bool
     */
    public function modified($modified = null)
    {
        if ($modified !== null) {
            $this->modified = $modified;
        }

        return $this->modified;
    }

    /**
     * @param int|null $timestamp
     * @return int
     */
    public function timestamp($timestamp = null)
    {
        if ($timestamp !== null) {
            $this->timestamp = $timestamp;
        }

        return $this->timestamp;
    }

    /**
     * @return $this
     */
    public function reload()
    {
        $grav = Grav::instance();

        // Load new configuration.
        $config = ConfigServiceProvider::load($grav);

        /** @var Debugger $debugger */
        $debugger = $grav['debugger'];

        if ($config->modified()) {
            // Update current configuration.
            $this->items = $config->toArray();
            $this->checksum($config->checksum());
            $this->modified(true);

            $debugger->addMessage('Configuration was changed and saved.');
        }

        return $this;
    }

    /**
     * @return void
     */
    public function debug()
    {
        /** @var Debugger $debugger */
        $debugger = Grav::instance()['debugger'];

        $debugger->addMessage('Environment Name: ' . $this->environment);
        if ($this->modified()) {
            $debugger->addMessage('Configuration reloaded and cached.');
        }
    }

    /**
     * @return void
     */
    public function init()
    {
        $setup = Grav::instance()['setup']->toArray();
        foreach ($setup as $key => $value) {
            if ($key === 'streams' || !is_array($value)) {
                // Optimized as streams and simple values are fully defined in setup.
                $this->items[$key] = $value;
            } else {
                $this->joinDefaults($key, $value);
            }
        }

        // Legacy value - Override the media.upload_limit based on PHP values
        $this->items['system']['media']['upload_limit'] = Utils::getUploadLimit();
    }

    /**
     * @return mixed
     * @deprecated 1.5 Use Grav::instance()['languages'] instead.
     */
    public function getLanguages()
    {
        user_error(__CLASS__ . '::' . __FUNCTION__ . '() is deprecated since Grav 1.5, use Grav::instance()[\'languages\'] instead', E_USER_DEPRECATED);

        return Grav::instance()['languages'];
    }
}
