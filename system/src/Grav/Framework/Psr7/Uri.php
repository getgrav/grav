<?php

declare(strict_types=1);

/**
 * @package    Grav\Framework\Psr7
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Psr7;

use Grav\Framework\Psr7\Traits\UriDecorationTrait;
use Psr\Http\Message\UriInterface;

class Uri implements UriInterface
{
    use UriDecorationTrait;

    public function __construct(string $uri = '')
    {
        $this->uri = new \Nyholm\Psr7\Uri($uri);
    }
}
