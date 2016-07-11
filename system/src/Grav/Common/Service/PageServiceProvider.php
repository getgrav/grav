<?php
/**
 * @package    Grav.Common.Service
 *
 * @copyright  Copyright (C) 2014 - 2016 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Service;

use Pimple\Container;
use Pimple\ServiceProviderInterface;

class PageServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container) {

        $container['page'] = function ($c) {
            /** @var Grav $c */

            /** @var Pages $pages */
            $pages = $c['pages'];
            /** @var Language $language */
            $language = $c['language'];

            /** @var Uri $uri */
            $uri = $c['uri'];

            $path = $uri->path(); // Don't trim to support trailing slash default routes
            $path = $path ?: '/';

            $page = $pages->dispatch($path);

            // Redirection tests
            if ($page) {

                $url = $page->route();

                if ($uri->params()) {
                    if ($url == '/') { //Avoid double slash
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

                // Language-specific redirection scenarios
                if ($language->enabled()) {
                    if ($language->isLanguageInUrl() && !$language->isIncludeDefaultLanguage()) {
                        $c->redirect($url);
                    }
                    if (!$language->isLanguageInUrl() && $language->isIncludeDefaultLanguage()) {
                        $c->redirectLangSafe($url);
                    }
                }
                // Default route test and redirect
                if ($c['config']->get('system.pages.redirect_default_route') && $page->route() != $path) {
                    $c->redirectLangSafe($url);
                }
            }

            // if page is not found, try some fallback stuff
            if (!$page || !$page->routable()) {

                // Try fallback URL stuff...
                $c->fallbackUrl($path);

                // If no page found, fire event
                $event = $c->fireEvent('onPageNotFound');

                if (isset($event->page)) {
                    $page = $event->page;
                } else {
                    throw new \RuntimeException('Page Not Found', 404);
                }
            }

            return $page;
        };
    }
}
