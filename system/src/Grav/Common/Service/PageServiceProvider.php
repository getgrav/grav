<?php
/**
 * @package    Grav.Common.Service
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
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

class PageServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container)
    {
        $container['page'] = function ($c) {
            /** @var Grav $c */

            /** @var Pages $pages */
            $pages = $c['pages'];

            /** @var Config $config */
            $config = $c['config'];

            /** @var Uri $uri */
            $uri = $c['uri'];

            $path = $uri->path() ?: '/'; // Don't trim to support trailing slash default routes
            $page = $pages->dispatch($path);

            // Redirection tests
            if ($page) {
                // some debugger override logic
                if ($page->debugger() === false) {
                    $c['debugger']->enabled(false);
                }

                if ($config->get('system.force_ssl')) {
                    if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
                        $url = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
                        $c->redirect($url);
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
                $language = $c['language'];

                // Language-specific redirection scenarios
                if ($language->enabled() && ($language->isLanguageInUrl() xor $language->isIncludeDefaultLanguage())) {
                    $c->redirect($url);
                }
                // Default route test and redirect
                if ($config->get('system.pages.redirect_default_route') && $page->route() !== $path) {
                    $c->redirect($url);
                }
            }

            // if page is not found, try some fallback stuff
            if (!$page || !$page->routable()) {

                // Try fallback URL stuff...
                $page = $c->fallbackUrl($path);

                if (!$page) {
                    $path = $c['locator']->findResource('system://pages/notfound.md');
                    $page = new Page();
                    $page->init(new \SplFileInfo($path));
                    $page->routable(false);
                }
            }

            return $page;
        };
    }
}
