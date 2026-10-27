<?php

/**
 * @package    Grav\Common\Page
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Page\Interfaces;

/**
 * A page that can tell the id of its folder.
 *
 * Kept apart from PageInterface so other page implementations don't have to
 * add it.
 *
 * @see \Grav\Common\Page\PageIdentity
 */
interface PageUuidInterface
{
    /**
     * The id of the page's folder (UUID v4), or null when it has none.
     *
     * @return string|null
     */
    public function uuid(): ?string;
}
