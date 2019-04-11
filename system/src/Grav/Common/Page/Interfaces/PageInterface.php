<?php

/**
 * @package    Grav\Common\Page
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Page\Interfaces;

use Grav\Common\Media\Interfaces\MediaInterface;

/**
 * Class implements page interface.
 */
interface PageInterface extends PageContentInterface, PageRoutableInterface, PageTranslateInterface, MediaInterface, PageLegacyInterface
{
}
