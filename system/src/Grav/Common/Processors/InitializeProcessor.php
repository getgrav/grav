<?php
/**
 * @package    Grav.Common.Processors
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Processors;

class InitializeProcessor extends ProcessorBase implements ProcessorInterface
{
    public $id = 'init';
    public $title = 'Initialize';

    public function process()
    {
        $this->container['config']->debug();

        // Use output buffering to prevent headers from being sent too early.
        ob_start();
        if ($this->container['config']->get('system.cache.gzip')) {
            // Enable zip/deflate with a fallback in case of if browser does not support compressing.
            if (!@ob_start("ob_gzhandler")) {
                ob_start();
            }
        }

        // Initialize the timezone.
        if ($this->container['config']->get('system.timezone')) {
            date_default_timezone_set($this->container['config']->get('system.timezone'));
        }

        // FIXME: Initialize session should happen later after plugins have been loaded. This is a workaround to fix session issues in AWS.
        if ($this->container['config']->get('system.session.initialize', 1) && isset($this->container['session'])) {
            $this->container['session']->init();
        }

        // Initialize uri.
        $this->container['uri']->init();

        $this->container->setLocale();
    }
}
