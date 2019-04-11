<?php

/**
 * @package    Grav\Common\Media
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Media\Interfaces;

/**
 * Class implements media collection interface.
 */
interface MediaCollectionInterface extends \Grav\Framework\Media\Interfaces\MediaCollectionInterface
{
    /**
     * Return media path.
     *
     * @return string|null
     */
    public function getPath();
}
