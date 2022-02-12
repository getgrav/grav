<?php declare(strict_types=1);

namespace Grav\Framework\Contracts\Image;

/**
 * Image information interface.
 */
interface ImageInfoInterface
{
    /**
     * Get image width.
     *
     * @return int
     */
    public function width(): int;

    /**
     * Get image height.
     *
     * @return int
     */
    public function height(): int;
}
