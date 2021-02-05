<?php

/**
 * @package    Grav\Common\Service
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Service;

use Grav\Common\Page\Interfaces\PageInterface;
use Grav\Common\Twig\Twig;
use Pimple\Container;
use Pimple\ServiceProviderInterface;

/**
 * Class OutputServiceProvider
 * @package Grav\Common\Service
 */
class OutputServiceProvider implements ServiceProviderInterface
{
    /**
     * @param Container $container
     * @return void
     */
    public function register(Container $container)
    {
        $container['output'] = function ($c) {
            /** @var Twig $twig */
            $twig = $c['twig'];

            /** @var PageInterface $page */
            $page = $c['page'];

            return $twig->processSite($page->templateFormat());
        };
    }
}
