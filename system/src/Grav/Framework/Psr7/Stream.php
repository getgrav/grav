<?php

declare(strict_types=1);

/**
 * @package    Grav\Framework\Psr7
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Psr7;

use Grav\Framework\Psr7\Traits\StreamDecoratorTrait;
use Psr\Http\Message\StreamInterface;

class Stream implements StreamInterface
{
    use StreamDecoratorTrait;

    /**
     * @param StreamInterface $stream
     * @return static
     */
    public static function createFrom(StreamInterface $stream)
    {
        if ($stream instanceof self) {
            return $stream;
        }

        return new static($stream);
    }

    public function __construct($body = '')
    {
        if ($body instanceof StreamInterface) {
            $this->stream = $body;
        } else {
            $this->stream = new static(\Nyholm\Psr7\Stream::create($body));
        }
    }
}
