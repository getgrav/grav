<?php

/**
 * @package    Grav\Common\Processors
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Processors;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Class SchedulerProcessor
 * @package Grav\Common\Processors
 */
class SchedulerProcessor extends ProcessorBase
{
    /** @var string */
    public $id = '_scheduler';
    /** @var string */
    public $title = 'Scheduler';

    /**
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Nothing in a web request runs scheduled jobs, so building the scheduler and
        // firing onSchedulerInitialized here was pure overhead. The scheduler now
        // initializes its jobs on first real use (see Scheduler::initializeJobs()),
        // which the CLI runner, webhook, health check and job listings all go through.
        return $handler->handle($request);
    }
}
