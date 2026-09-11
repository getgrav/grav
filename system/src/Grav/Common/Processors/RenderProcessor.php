<?php

/**
 * @package    Grav\Common\Processors
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Processors;

use Grav\Common\Page\Interfaces\PageInterface;
use Grav\Common\Page\Markdown\MarkdownOutput;
use Grav\Framework\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RocketTheme\Toolbox\Event\Event;

/**
 * Class RenderProcessor
 * @package Grav\Common\Processors
 */
class RenderProcessor extends ProcessorBase
{
    /** @var string */
    public $id = 'render';
    /** @var string */
    public $title = 'Render';

    /**
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->startTimer();

        $container = $this->container;
        $output =  $container['output'];

        if ($output instanceof ResponseInterface) {
            return $output;
        }

        /** @var PageInterface $page */
        $page = $container['page'];

        if ($container['debugger']->enabled()) {
            $page->cacheControl('no-store, no-cache, must-revalidate, max-age=0');
        }

        // Use internal Grav output.
        $container->output = $output;

        ob_start();

        $event = new Event(['page' => $page, 'output' => &$container->output]);
        $container->fireEvent('onOutputGenerated', $event);

        echo $container->output;

        $html = ob_get_clean();

        // remove any output
        $container->output = '';

        $event = new Event(['page' => $page, 'output' => $html]);
        $this->container->fireEvent('onOutputRendered', $event);

        $headers = $page->httpHeaders();
        if ($page->templateFormat() === MarkdownOutput::FORMAT && MarkdownOutput::tokenHeaderEnabled()) {
            $headers['X-Markdown-Tokens'] = (string)MarkdownOutput::estimateTokens($html);
        }

        $this->stopTimer();

        return new Response($page->httpResponseCode(), $headers, $html);
    }
}
