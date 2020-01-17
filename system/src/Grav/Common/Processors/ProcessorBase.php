<?php

/**
 * @package    Grav\Common\Processors
 *
 * @copyright  Copyright (C) 2015 - 2020 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Processors;

use Grav\Common\Debugger;
use Grav\Common\Grav;

abstract class ProcessorBase implements ProcessorInterface
{
    /** @var Grav */
    protected $container;

    /** @var string */
    public $id = 'processorbase';
    /** @var string */
    public $title = 'ProcessorBase';

    public function __construct(Grav $container)
    {
        $this->container = $container;
    }

    protected function startTimer($id = null, $title = null): void
    {
        /** @var Debugger $debugger */
        $debugger = $this->container['debugger'];
        $debugger->startTimer($id ?? $this->id, $title ?? $this->title);
    }

    protected function stopTimer($id = null): void
    {
        /** @var Debugger $debugger */
        $debugger = $this->container['debugger'];
        $debugger->stopTimer($id ?? $this->id);
    }

    protected function addMessage($message, $label = 'info', $isString = true): void
    {
        /** @var Debugger $debugger */
        $debugger = $this->container['debugger'];
        $debugger->addMessage($message, $label, $isString);
    }
}
