<?php

namespace Doctrine\Common\Cache;

use Grav\Common\Cache\SymfonyCacheProvider;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

/**
 * Filesystem cache driver (backwards compatibility).
 */
class FilesystemCache extends SymfonyCacheProvider
{
    public const EXTENSION = '.doctrinecache.data';

    /**
     * @param string $directory
     * @param string $extension
     * @param int    $umask
     */
    public function __construct($directory, $extension = self::EXTENSION, $umask = 0002)
    {
        parent::__construct(new FilesystemAdapter('', 0, $directory));
    }
}
