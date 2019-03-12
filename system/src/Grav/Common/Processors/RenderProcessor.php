<?php

/**
 * @package    Grav\Common\Processors
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Processors;

use Grav\Common\Page\Interfaces\PageInterface;
use Grav\Framework\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class RenderProcessor extends ProcessorBase
{
    public $id = 'render';
    public $title = 'Render';

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler) : ResponseInterface
    {
        $this->startTimer();

        $container = $this->container;
        $output =  $container['output'];

        if ($output instanceof ResponseInterface) {
            return $output;
        }

        ob_start();

        // Use internal Grav output.
        $container->output = $output;
        $container->fireEvent('onOutputGenerated');

        echo $container->output;

        // remove any output
        $container->output = '';

        $this->container->fireEvent('onOutputRendered');

        $html = ob_get_clean();

        /** @var PageInterface $page */
        $page = $this->container['page'];
        $this->stopTimer();

        return new Response($page->httpResponseCode(), $page->httpHeaders(), $html);
    }
}
