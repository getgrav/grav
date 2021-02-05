<?php

/**
 * @package    Grav\Framework\RequestHandler
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

declare(strict_types=1);

namespace Grav\Framework\RequestHandler\Exception;

use Psr\Http\Message\ServerRequestInterface;
use Throwable;
use function in_array;

/**
 * Class NotFoundException
 * @package Grav\Framework\RequestHandler\Exception
 */
class NotFoundException extends RequestException
{
    /** @var ServerRequestInterface */
    private $request;

    /**
     * NotFoundException constructor.
     * @param ServerRequestInterface $request
     * @param Throwable|null $previous
     */
    public function __construct(ServerRequestInterface $request, Throwable $previous = null)
    {
        if (in_array(strtoupper($request->getMethod()), ['PUT', 'PATCH', 'DELETE'])) {
            parent::__construct($request, 'Method Not Allowed', 405, $previous);
        } else {
            parent::__construct($request, 'Not Found', 404, $previous);
        }
    }

    public function getRequest(): ServerRequestInterface
    {
        return $this->request;
    }
}
