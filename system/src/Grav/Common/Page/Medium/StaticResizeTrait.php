<?php

/**
 * @package    Grav\Common\Page
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Page\Medium;

use Grav\Common\Media\Traits\StaticResizeTrait as NewResizeTrait;

user_error('Grav\Common\Page\Medium\StaticResizeTrait is deprecated since Grav 1.7, use Grav\Common\Media\Traits\StaticResizeTrait instead', E_USER_DEPRECATED);

/**
 * Trait StaticResizeTrait
 * @package Grav\Common\Page\Medium
 * @deprecated 1.7 Use `Grav\Common\Media\Traits\StaticResizeTrait` instead
 */
trait StaticResizeTrait
{
    use NewResizeTrait;
}
