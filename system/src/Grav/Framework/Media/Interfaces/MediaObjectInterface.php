<?php

/**
 * @package    Grav\Framework\Media
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Media\Interfaces;

use Psr\Http\Message\UploadedFileInterface;

/**
 * Class implements media object interface.
 *
 * @property UploadedFileInterface|null $uploaded_file
 */
interface MediaObjectInterface
{
    /**
     * Returns an array containing the file metadata
     *
     * @return array
     */
    public function getMeta();

    /**
     * Return URL to file.
     *
     * @param bool $reset
     * @return string
     */
    public function url($reset = true);

    /**
     * Get value by using dot notation for nested arrays/objects.
     *
     * @example $value = $this->get('this.is.my.nested.variable');
     *
     * @param string $name Dot separated path to the requested value.
     * @param mixed $default Default value (or null).
     * @param string|null $separator Separator, defaults to '.'
     * @return mixed Value.
     */
    public function get($name, $default = null, $separator = null);
}
