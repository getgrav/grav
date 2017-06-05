<?php
/**
 * @package    Grav.Common.Processors
 *
 * @copyright  Copyright (C) 2014 - 2017 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Processors;

use Grav\Common\Uri;
use Grav\Common\Utils;

class InitializeProcessor extends ProcessorBase implements ProcessorInterface
{
    public $id = 'init';
    public $title = 'Initialize';

    public function process()
    {
        $config = $this->container['config'];
        $config->debug();

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

        // Initialize uri, session.
        $this->container['session']->init();

        /** @var Uri $uri */
        $uri = $this->container['uri'];
        $uri->init();

        // Redirect pages with trailing slash if configured to do so.
        $path = $uri->path() ?: '/';
        if ($config->get('system.pages.redirect_trailing_slash', false) && $path !== '/' && Utils::endsWith($path, '/')) {
            $this->container->redirect(rtrim($path, '/'));
        }

        $this->container->setLocale();
    }
}
