<?php

/**
 * @package    Grav\Common\Processors
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Processors;

use Grav\Common\Debugger;
use Grav\Common\Grav;

/**
 * Class ProcessorBase
 * @package Grav\Common\Processors
 */
abstract class ProcessorBase implements ProcessorInterface
{
    /** @var Grav */
    protected $container;

    /** @var string */
    public $id = 'processorbase';
    /** @var string */
    public $title = 'ProcessorBase';

    /**
     * ProcessorBase constructor.
     * @param Grav $container
     */
    public function __construct(Grav $container)
    {
        $this->container = $container;
    }

    /**
     * @param string|null $id
     * @param string|null $title
     */
    protected function startTimer($id = null, $title = null): void
    {
        /** @var Debugger $debugger */
        $debugger = $this->container['debugger'];
        $debugger->startTimer($id ?? $this->id, $title ?? $this->title);
    }

    /**
     * @param string|null $id
     */
    protected function stopTimer($id = null): void
    {
        /** @var Debugger $debugger */
        $debugger = $this->container['debugger'];
        $debugger->stopTimer($id ?? $this->id);
    }

    /**
     * @param string $message
     * @param string $label
     * @param bool $isString
     */
    protected function addMessage($message, $label = 'info', $isString = true): void
    {
        /** @var Debugger $debugger */
        $debugger = $this->container['debugger'];
        $debugger->addMessage($message, $label, $isString);
    }
}
