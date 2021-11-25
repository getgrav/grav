<?php

/**
 * @package    Grav\Common\Processors
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Processors;

use Grav\Common\Page\Interfaces\PageInterface;
use Grav\Framework\RequestHandler\Exception\RequestException;
use Grav\Plugin\Form\Forms;
use RocketTheme\Toolbox\Event\Event;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

/**
 * Class PagesProcessor
 * @package Grav\Common\Processors
 */
class PagesProcessor extends ProcessorBase
{
    /** @var string */
    public $id = 'pages';
    /** @var string */
    public $title = 'Pages';

    /**
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->startTimer();

        // Dump Cache state
        $this->container['debugger']->addMessage($this->container['cache']->getCacheStatus());

        $this->container['pages']->init();

        $route = $this->container['route'];

        $this->container->fireEvent('onPagesInitialized', new Event(
            [
                'pages' => $this->container['pages'],
                'route' => $route,
                'request' => $request
            ]
        ));
        $this->container->fireEvent('onPageInitialized', new Event(
            [
                'page' => $this->container['page'],
                'route' => $route,
                'request' => $request
            ]
        ));

        /** @var PageInterface $page */
        $page = $this->container['page'];

        if (!$page->routable()) {
            $exception = new RequestException($request, 'Page Not Found', 404);
            // If no page found, fire event
            $event = new Event([
                'page' => $page,
                'code' => $exception->getCode(),
                'message' => $exception->getMessage(),
                'exception' => $exception,
                'route' => $route,
                'request' => $request
            ]);
            $event->page = null;
            $event = $this->container->fireEvent('onPageNotFound', $event);

            if (isset($event->page)) {
                unset($this->container['page']);
                $this->container['page'] = $page = $event->page;
            } else {
                throw new RuntimeException('Page Not Found', 404);
            }

            $this->addMessage("Routed to page {$page->rawRoute()} (type: {$page->template()}) [Not Found fallback]");
        } else {
            $this->addMessage("Routed to page {$page->rawRoute()} (type: {$page->template()})");

            $task = $this->container['task'];
            $action = $this->container['action'];

            /** @var Forms $forms */
            $forms = $this->container['forms'] ?? null;
            $form = $forms ? $forms->getActiveForm() : null;

            $options = ['page' => $page, 'form' => $form, 'request' => $request];
            if ($task) {
                $event = new Event(['task' => $task] + $options);
                $this->container->fireEvent('onPageTask', $event);
                $this->container->fireEvent('onPageTask.' . $task, $event);
            } elseif ($action) {
                $event = new Event(['action' => $action] + $options);
                $this->container->fireEvent('onPageAction', $event);
                $this->container->fireEvent('onPageAction.' . $action, $event);
            }
        }

        $this->stopTimer();

        return $handler->handle($request);
    }
}
