<?php
/**
 * @package    Grav.Common.Processors
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Processors;

use Grav\Common\Config\Config;
use Grav\Common\Uri;
use Grav\Common\Utils;

class InitializeProcessor extends ProcessorBase implements ProcessorInterface
{
    public $id = 'init';
    public $title = 'Initialize';

    public function process()
    {
        /** @var Config $config */
        $config = $this->container['config'];
        $config->debug();

        // Use output buffering to prevent headers from being sent too early.
        ob_start();
        if ($config->get('system.cache.gzip') && !@ob_start('ob_gzhandler')) {
            // Enable zip/deflate with a fallback in case of if browser does not support compressing.
            ob_start();
        }

        // Initialize the timezone.
        if ($config->get('system.timezone')) {
            date_default_timezone_set($this->container['config']->get('system.timezone'));
        }

        // FIXME: Initialize session should happen later after plugins have been loaded. This is a workaround to fix session issues in AWS.
        if (isset($this->container['session']) && $config->get('system.session.initialize', true)) {
            $this->container['session']->init();
        }

        /** @var Uri $uri */
        $uri = $this->container['uri'];
        $uri->init();

        // Redirect pages with trailing slash if configured to do so.
        $path = $uri->path() ?: '/';
        if ($path !== '/' && $config->get('system.pages.redirect_trailing_slash', false) && Utils::endsWith($path, '/')) {
            $this->container->redirect(rtrim($path, '/'));
        }

        $this->container->setLocale();
    }
}
