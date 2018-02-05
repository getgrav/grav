<?php
/**
 * @package    Grav.Common.Service
 *
 * @copyright  Copyright (C) 2014 - 2017 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Service;

use Grav\Common\Page\Page;
use Grav\Common\Twig\Twig;
use Pimple\Container;
use Pimple\ServiceProviderInterface;

class OutputServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container)
    {
        $container['output'] = function ($c) {
            /** @var Twig $twig */
            $twig = $c['twig'];

            /** @var Page $page */
            $page = $c['page'];

            return $twig->processSite($page->templateFormat());
        };
    }
}
