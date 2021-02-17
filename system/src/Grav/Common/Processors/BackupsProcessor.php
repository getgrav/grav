<?php

/**
 * @package    Grav\Common\Processors
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Processors;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Class BackupsProcessor
 * @package Grav\Common\Processors
 */
class BackupsProcessor extends ProcessorBase
{
    /** @var string */
    public $id = '_backups';
    /** @var string */
    public $title = 'Backups';

    /**
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->startTimer();
        $backups = $this->container['backups'];
        $backups->init();
        $this->stopTimer();

        return $handler->handle($request);
    }
}
