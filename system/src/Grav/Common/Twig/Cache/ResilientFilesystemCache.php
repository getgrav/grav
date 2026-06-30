<?php

/**
 * @package    Grav\Common\Twig
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Twig\Cache;

use Grav\Common\Grav;
use RuntimeException;
use Twig\Cache\FilesystemCache;

/**
 * Twig filesystem cache that never turns a failed cache write into a fatal error.
 *
 * Twig's {@see FilesystemCache::write()} writes the compiled template to a temp
 * file and renames it into place, throwing `Failed to write cache file` when
 * either step fails. On filesystems where rename() is not reliably atomic under
 * concurrent access — notably VirtualBox/Vagrant shared folders (vboxsf), some
 * network mounts, or a disk that has just filled — two requests racing to
 * compile the same template (e.g. a page save that clears the Twig cache
 * followed immediately by a front-end reload that recompiles it) can lose that
 * race and surface a 500 to the visitor (getgrav/grav#4184).
 *
 * The persisted cache file is only an optimization for FUTURE requests: Twig's
 * {@see \Twig\Environment::loadClass()} eval()s the freshly compiled source as
 * a "last line of defense" when the class can't be loaded from the cache, so
 * the CURRENT request still renders correctly even when the write never lands.
 * We therefore log the failure and carry on instead of throwing; the next
 * request simply recompiles and retries the write, which on an intermittent
 * filesystem usually succeeds.
 */
class ResilientFilesystemCache extends FilesystemCache
{
    /**
     * @param string $key
     * @param string $content
     * @return void
     */
    public function write(string $key, string $content): void
    {
        try {
            parent::write($key, $content);
        } catch (RuntimeException $e) {
            // Couldn't persist the compiled template (unreliable rename, full
            // disk, unwritable cache dir, ...). The current request still
            // renders via Twig's eval fallback, so degrade to a logged warning
            // rather than a fatal error and let a later request retry the write.
            $grav = Grav::instance();
            if (isset($grav['log'])) {
                $grav['log']->warning(
                    sprintf('Twig cache write failed, rendering without persisting it: %s', $e->getMessage())
                );
            }
        }
    }
}
