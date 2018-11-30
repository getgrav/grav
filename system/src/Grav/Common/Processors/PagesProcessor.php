<?php

/**
 * @package    Grav.Common.Processors
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Processors;

use Grav\Common\Page\Page;
use RocketTheme\Toolbox\Event\Event;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class PagesProcessor extends ProcessorBase
{
    public $id = 'pages';
    public $title = 'Pages';

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler) : ResponseInterface
    {
        $this->startTimer();

        // Dump Cache state
        $this->container['debugger']->addMessage($this->container['cache']->getCacheStatus());

        $this->container['pages']->init();
        $this->container->fireEvent('onPagesInitialized', new Event(['pages' => $this->container['pages']]));
        $this->container->fireEvent('onPageInitialized', new Event(['page' => $this->container['page']]));

        /** @var Page $page */
        $page = $this->container['page'];

        if (!$page->routable()) {
            // If no page found, fire event
            $event = $this->container->fireEvent('onPageNotFound', new Event(['page' => $page]));

            if (isset($event->page)) {
                unset ($this->container['page']);
                $this->container['page'] = $page = $event->page;
            } else {
                throw new \RuntimeException('Page Not Found', 404);
            }
        }

        $this->addMessage("Routed to page {$page->rawRoute()} (type: {$page->template()})");

        $this->stopTimer();

        return $handler->handle($request);
    }
}
