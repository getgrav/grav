<?php

/**
 * @package    Grav\Framework\Flex
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Flex\Interfaces;

use Grav\Framework\Form\Interfaces\FormInterface;
use Grav\Framework\Route\Route;
use Serializable;

/**
 * Defines Forms for Flex Objects.
 *
 * @used-by \Grav\Framework\Flex\FlexForm
 * @since 1.6
 */
interface FlexFormInterface extends Serializable, FormInterface
{
    /**
     * Get media task route.
     *
     * @return string   Returns admin route for media tasks.
     */
    public function getMediaTaskRoute(): string;

    /**
     * Get route for uploading files by AJAX.
     *
     * @return Route|null       Returns Route object or null if file uploads are not enabled.
     */
    public function getFileUploadAjaxRoute();

    /**
     * Get route for deleting files by AJAX.
     *
     * @param string|null $field     Field where the file is associated into.
     * @param string|null $filename  Filename for the file.
     * @return Route|null       Returns Route object or null if file uploads are not enabled.
     */
    public function getFileDeleteAjaxRoute($field, $filename);

//    /**
//     * @return FlexObjectInterface
//     */
//    public function getObject();
}
