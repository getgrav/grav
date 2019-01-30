<?php

/**
 * @package    Grav\Framework\Flex
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Flex\Interfaces;

use Grav\Framework\Form\Interfaces\FormInterface;
use Grav\Framework\Route\Route;

/**
 * Class FlexForm
 * @package Grav\Framework\Flex
 */
interface FlexFormInterface extends \Serializable, FormInterface
{
    /**
     * @return FlexObjectInterface
     */
    public function getObject(): FlexObjectInterface;

    /**
     * @return string
     */
    public function getMediaTaskRoute(): string;

    /**
     * @return string
     */
    public function getMediaRoute(): string;

    /**
     * @return Route|null
     */
    public function getFileUploadAjaxRoute(): ?Route;

    /**
     * @param $field
     * @param $filename
     * @return Route|null
     */
    public function getFileDeleteAjaxRoute($field, $filename): ?Route;
}
