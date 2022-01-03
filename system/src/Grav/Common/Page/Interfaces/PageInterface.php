<?php

/**
 * @package    Grav\Common\Page
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Page\Interfaces;

use Grav\Common\Media\Interfaces\MediaInterface;

/**
 * Class implements page interface.
 */
interface PageInterface extends
    PageContentInterface,
    PageFormInterface,
    PageRoutableInterface,
    PageTranslateInterface,
    MediaInterface,
    PageLegacyInterface
{
}
