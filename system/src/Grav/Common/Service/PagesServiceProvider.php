<?php

/**
 * @package    Grav\Common\Service
 *
 * @copyright  Copyright (C) 2015 - 2020 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Service;

use Grav\Common\Config\Config;
use Grav\Common\Grav;
use Grav\Common\Language\Language;
use Grav\Common\Page\Page;
use Grav\Common\Page\Pages;
use Grav\Common\Uri;
use Pimple\Container;
use Pimple\ServiceProviderInterface;
use SplFileInfo;
use function defined;

/**
 * Class PagesServiceProvider
 * @package Grav\Common\Service
 */
class PagesServiceProvider implements ServiceProviderInterface
{
    /**
     * @param Container $container
     * @return void
     */
    public function register(Container $container)
    {
        $container['pages'] = function (Grav $grav) {
            return new Pages($grav);
        };

        if (defined('GRAV_CLI')) {
            $container['page'] = static function (Grav $grav) {
                $path = $grav['locator']->findResource('system://pages/notfound.md');
                $page = new Page();
                $page->init(new SplFileInfo($path));
                $page->routable(false);

                return $page;
            };

            return;
        }

        $container['page'] = static function (Grav $grav) {
            /** @var Pages $pages */
            $pages = $grav['pages'];

            /** @var Config $config */
            $config = $grav['config'];

            /** @var Uri $uri */
            $uri = $grav['uri'];

            $path = $uri->path() ?: '/'; // Don't trim to support trailing slash default routes
            $page = $pages->dispatch($path);

            // Redirection tests
            if ($page) {
                // some debugger override logic
                if ($page->debugger() === false) {
                    $grav['debugger']->enabled(false);
                }

                if ($config->get('system.force_ssl')) {
                    $scheme = $uri->scheme(true);
                    if ($scheme !== 'https') {
                        $url = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
                        $grav->redirect($url);
                    }
                }

                $url = $pages->route($page->route());

                if ($uri->params()) {
                    if ($url === '/') { //Avoid double slash
                        $url = $uri->params();
                    } else {
                        $url .= $uri->params();
                    }
                }
                if ($uri->query()) {
                    $url .= '?' . $uri->query();
                }
                if ($uri->fragment()) {
                    $url .= '#' . $uri->fragment();
                }

                /** @var Language $language */
                $language = $grav['language'];

                // Language-specific redirection scenarios
                if ($language->enabled() && ($language->isLanguageInUrl() xor $language->isIncludeDefaultLanguage())) {
                    $grav->redirect($url);
                }
                // Default route test and redirect
                if ($config->get('system.pages.redirect_default_route') && $page->route() !== $path) {
                    $grav->redirect($url);
                }
            }

            // if page is not found, try some fallback stuff
            if (!$page || !$page->routable()) {
                // Try fallback URL stuff...
                $page = $grav->fallbackUrl($path);

                if (!$page) {
                    $path = $grav['locator']->findResource('system://pages/notfound.md');
                    $page = new Page();
                    $page->init(new SplFileInfo($path));
                    $page->routable(false);
                }
            }

            return $page;
        };
    }
}
