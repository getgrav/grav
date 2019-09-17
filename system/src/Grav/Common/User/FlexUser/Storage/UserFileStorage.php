<?php

/**
 * @package    Grav\Common\User
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\User\FlexUser\Storage;

use Grav\Framework\Flex\Storage\FileStorage;

class UserFileStorage extends FileStorage
{
    /**
     * {@inheritdoc}
     * @see FlexStorageInterface::getMediaPath()
     */
    public function getMediaPath(string $key = null): ?string
    {
        // There is no media support for file storage (fallback to common location).
        return null;
    }
}
