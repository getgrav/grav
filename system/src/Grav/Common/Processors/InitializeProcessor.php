<?php

/**
 * @package    Grav\Common\Processors
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Processors;

use Grav\Common\Config\Config;
use Grav\Common\Grav;
use Grav\Common\Uri;
use Grav\Common\Utils;
use Grav\Framework\Session\Exceptions\SessionException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class InitializeProcessor extends ProcessorBase
{
    public $id = 'init';
    public $title = 'Initialize';

    /** @var bool */
    private static $cli_initialized = false;

    /**
     * @param Grav $grav
     */
    public static function initializeCli(Grav $grav)
    {
        if (!static::$cli_initialized) {
            static::$cli_initialized = true;

            $instance = new static($grav);
            $instance->processCli();
        }
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler) : ResponseInterface
    {
        $this->startTimer();

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
        $timezone = $config->get('system.timezone');
        if ($timezone) {
            date_default_timezone_set($timezone);
        }

        // FIXME: Initialize session should happen later after plugins have been loaded. This is a workaround to fix session issues in AWS.
        if (isset($this->container['session']) && $config->get('system.session.initialize', true)) {
            // TODO: remove in 2.0.
            $this->container['accounts'];

            try {
                $this->container['session']->init();
            } catch (SessionException $e) {
                $this->container['session']->init();
                $message = 'Session corruption detected, restarting session...';
                $this->addMessage($message);
                $this->container['messages']->add($message, 'error');
            }
        }

        /** @var Uri $uri */
        $uri = $this->container['uri'];
        $uri->init();

        // Redirect pages with trailing slash if configured to do so.
        $path = $uri->path() ?: '/';
        if ($path !== '/'
            && $config->get('system.pages.redirect_trailing_slash', false)
            && Utils::endsWith($path, '/')) {

            $redirect = (string) $uri::getCurrentRoute()->toString();
            $this->container->redirect($redirect);
        }

        $this->container->setLocale();
        $this->stopTimer();

        return $handler->handle($request);
    }

    public function processCli(): void
    {
        // Load configuration.
        $this->container['config']->init();
        $this->container['plugins']->setup();

        // Disable debugger.
        $this->container['debugger']->enabled(false);

        // Set timezone, locale.
        /** @var Config $config */
        $config = $this->container['config'];
        $timezone = $config->get('system.timezone');
        if ($timezone) {
            date_default_timezone_set($timezone);
        }
        $this->container->setLocale();

        // Load plugins.
        $this->container['plugins']->init();

        // Initialize URI.
        /** @var Uri $uri */
        $uri = $this->container['uri'];
        $uri->init();

        // Load accounts.
        // TODO: remove in 2.0.
        $this->container['accounts'];
    }
}
