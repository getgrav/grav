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
 * Defines Forms for Flex Objects.
 *
 * @used-by \Grav\Framework\Flex\FlexForm
 * @since 1.6
 */
interface FlexFormInterface extends \Serializable, FormInterface
{
    /**
     * Get object associated to the form.
     *
     * @return FlexObjectInterface  Returns Flex Object associated to the form.
     * @api
     */
    public function getObject();

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
     * @param string $field     Field where the file is associated into.
     * @param string $filename  Filename for the file.
     *
     * @return Route|null       Returns Route object or null if file uploads are not enabled.
     */
    public function getFileDeleteAjaxRoute($field, $filename);
}
