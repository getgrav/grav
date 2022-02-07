<?php

/**
 * @package    Grav\Installer
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Installer;

use Throwable;

/**
 * Class InstallException
 * @package Grav\Installer
 */
class InstallException extends \RuntimeException
{
    /**
     * InstallException constructor.
     * @param string $message
     * @param Throwable $previous
     */
    public function __construct(string $message, Throwable $previous)
    {
        parent::__construct($message, $previous->getCode(), $previous);
    }
}
