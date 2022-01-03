<?php

/**
 * @package    Grav\Framework\Flex
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Flex\Interfaces;

/**
 * Defines Forms for Flex Objects.
 *
 * @used-by \Grav\Framework\Flex\FlexForm
 * @since 1.7
 */
interface FlexObjectFormInterface extends FlexFormInterface
{
    /**
     * Get object associated to the form.
     *
     * @return FlexObjectInterface  Returns Flex Object associated to the form.
     * @api
     */
    public function getObject();
}
