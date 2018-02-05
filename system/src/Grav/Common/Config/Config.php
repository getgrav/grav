<?php
/**
 * @package    Grav.Common.Config
 *
 * @copyright  Copyright (C) 2014 - 2017 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Config;

use Grav\Common\Debugger;
use Grav\Common\Grav;
use Grav\Common\Data\Data;
use Grav\Common\Service\ConfigServiceProvider;
use Grav\Common\Utils;

class Config extends Data
{
    protected $checksum;
    protected $modified = false;
    protected $timestamp = 0;

    public function key()
    {
        return $this->checksum();
    }

    public function checksum($checksum = null)
    {
        if ($checksum !== null) {
            $this->checksum = $checksum;
        }

        return $this->checksum;
    }

    public function modified($modified = null)
    {
        if ($modified !== null) {
            $this->modified = $modified;
        }

        return $this->modified;
    }

    public function timestamp($timestamp = null)
    {
        if ($timestamp !== null) {
            $this->timestamp = $timestamp;
        }

        return $this->timestamp;
    }

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

    public function debug()
    {
        /** @var Debugger $debugger */
        $debugger = Grav::instance()['debugger'];

        $debugger->addMessage('Environment Name: ' . $this->environment);
        if ($this->modified()) {
            $debugger->addMessage('Configuration reloaded and cached.');
        }
    }

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

        // Override the media.upload_limit based on PHP values
        $upload_limit = Utils::getUploadLimit();
        $this->items['system']['media']['upload_limit'] = $upload_limit > 0 ? $upload_limit : 1024*1024*1024;
    }

    /**
     * @return mixed
     * @deprecated
     */
    public function getLanguages()
    {
        return Grav::instance()['languages'];
    }
}
