<?php

/**
 * @package    Grav\Framework\RequestHandler
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

declare(strict_types=1);

namespace Grav\Framework\RequestHandler\Exception;

use Throwable;

/**
 * Class InvalidArgumentException
 * @package Grav\Framework\RequestHandler\Exception
 */
class InvalidArgumentException extends \InvalidArgumentException
{
    /** @var mixed|null */
    private $invalidMiddleware;

    /**
     * InvalidArgumentException constructor.
     *
     * @param string $message
     * @param mixed|null $invalidMiddleware
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct($message = '', $invalidMiddleware = null, $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);

        $this->invalidMiddleware = $invalidMiddleware;
    }

    /**
     * Return the invalid middleware
     *
     * @return mixed|null
     */
    public function getInvalidMiddleware()
    {
        return $this->invalidMiddleware;
    }
}
