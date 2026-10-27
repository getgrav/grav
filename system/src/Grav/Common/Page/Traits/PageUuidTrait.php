<?php

/**
 * @package    Grav\Common\Page
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Page\Traits;

use Grav\Common\Page\PageIdentity;

/**
 * Read-only access to the id of a page's folder.
 *
 * @see PageIdentity
 */
trait PageUuidTrait
{
    /**
     * The id of the page's folder (UUID v4), or null when it has none.
     *
     * Reads the folder's `.page-id`. Grav writes one when page ids are on
     * (`system.pages.uuid`), see PageIdentity.
     *
     * @return string|null
     */
    public function uuid(): ?string
    {
        $path = $this->path();

        return is_string($path) && $path !== '' ? PageIdentity::read($path) : null;
    }
}
