<?php

/**
 * @package    Grav\Common\Media
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Media;

use Grav\Common\Grav;
use Grav\Common\Media\Interfaces\MediaCollectionInterface;
use Grav\Common\Page\Interfaces\PageInterface;
use function is_string;
use function rawurlencode;
use function rtrim;

/**
 * Rewrites page media URLs to go through the page route.
 *
 * By default `Medium::url()` returns the file's path on disk with GRAV_ROOT
 * stripped, so a page's media is linked as `/user/pages/02.my-page/report.pdf`.
 * The web server answers that path itself and Grav is never started, which is
 * why `onPageFallBackUrl` listeners (the Login plugin's `access` check, for one)
 * only ever see media requested through the page route.
 *
 * When `system.pages.media_route_urls` is enabled, every page medium is stamped
 * with a `url` override pointing at `<page route>/<filename>`, so links emitted
 * by templates and markdown go through `Grav::fallbackUrl()` and those listeners
 * run. `ImageMedium::url()` honours the override only for unmodified originals,
 * so resized and cropped derivatives keep serving straight from `images/`.
 *
 * This on its own hides the on-disk path; it does not block it. Denying
 * `user/pages` at the web server (see the commented rule in `.htaccess` and the
 * files under `webserver-configs/`) is what closes it, and that rule must not be
 * enabled unless this setting is on, or every media URL on the site becomes a
 * 403.
 *
 * @package Grav\Common\Media
 */
final class MediaRouteUrls
{
    /**
     * Stamp a route-based `url` override on each of a page's media items.
     *
     * No-op unless `system.pages.media_route_urls` is enabled, so the default
     * install pays nothing for this.
     *
     * @param PageInterface $page
     * @param MediaCollectionInterface|null $media
     * @return void
     */
    public static function apply(PageInterface $page, $media): void
    {
        if (!$media instanceof MediaCollectionInterface) {
            return;
        }

        $grav = Grav::instance();
        if (!$grav['config']->get('system.pages.media_route_urls', false)) {
            return;
        }

        // Modules are not routable on their own, but `Pages::find()` resolves
        // them when asked for every page, which is how `Grav::fallbackUrl()`
        // reaches media stored in a `_module` folder. Their route is still the
        // right address for that media.
        $route = $page->url();
        if (!is_string($route) || $route === '') {
            return;
        }

        $base = rtrim($route, '/');
        foreach ($media->all() as $filename => $medium) {
            // The filename is decoded again by `Grav::fallbackUrl()`, which
            // reads it back through `rawurldecode()`.
            $medium->set('url', $base . '/' . rawurlencode((string)$filename));
        }
    }
}
