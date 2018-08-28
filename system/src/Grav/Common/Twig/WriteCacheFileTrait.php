<?php
/**
 * @package    Grav.Common.Twig
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Twig;

use Grav\Common\Grav;

trait WriteCacheFileTrait
{
    protected static $umask;

    /**
     * This exists so template cache files use the same
     * group between apache and cli
     *
     * @param $file
     * @param $content
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
            if (!is_dir(dirname($file))) {
                $old = umask(0002);
                mkdir(dirname($file), 0777, true);
                umask($old);
            }
            parent::writeCacheFile($file, $content);
            chmod($file, 0775);
        } else {
            parent::writeCacheFile($file, $content);
        }
    }
}
