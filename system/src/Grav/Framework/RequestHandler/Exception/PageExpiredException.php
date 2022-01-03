<?php

/**
 * @package    Grav\Framework\RequestHandler
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

declare(strict_types=1);

namespace Grav\Framework\RequestHandler\Exception;

use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * Class PageExpiredException
 * @package Grav\Framework\RequestHandler\Exception
 */
class PageExpiredException extends RequestException
{
    /**
     * PageExpiredException constructor.
     * @param ServerRequestInterface $request
     * @param Throwable|null $previous
     */
    public function __construct(ServerRequestInterface $request, Throwable $previous = null)
    {
        parent::__construct($request, 'Page Expired', 400, $previous); // 419
    }
}
