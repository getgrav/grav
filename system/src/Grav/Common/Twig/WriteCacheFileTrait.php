<?php

/**
 * @package    Grav\Common\Twig
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Twig;

use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use function dirname;

/**
 * Trait WriteCacheFileTrait
 * @package Grav\Common\Twig
 */
trait WriteCacheFileTrait
{
    /** @var bool */
    protected static $umask;

    /**
     * This exists so template cache files use the same
     * group between apache and cli
     *
     * @param string $file
     * @param string $content
     * @return void
     */
    protected function writeCacheFile($file, $content)
    {
        if (empty($file)) {
            return;
        }

        if (!isset(self::$umask)) {
            self::$umask = Grav::instance()['config']->get('system.twig.umask_fix', false);
        }

        if (self::$umask) {
            $dir = dirname($file);
            if (!is_dir($dir)) {
                $old = umask(0002);
                Folder::create($dir);
                umask($old);
            }
            parent::writeCacheFile($file, $content);
            chmod($file, 0775);
        } else {
            parent::writeCacheFile($file, $content);
        }
    }
}
