<?php

/**
 * @package    Grav\Common\Media
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Media\Interfaces;

/**
 * Class implements media player interface.
 */
interface MediaPlayerInterface extends MediaObjectInterface
{
    /**
     * Allows to set or remove the HTML5 default controls
     *
     * @param bool $status
     * @return $this
     */
    public function controls($status = true);

    /**
     * Allows to set the loop attribute
     *
     * @param bool $status
     * @return $this
     */
    public function loop($status = false);

    /**
     * Allows to set the autoplay attribute
     *
     * @param bool $status
     * @return $this
     */
    public function autoplay($status = false);

    /**
     * Allows to set the muted attribute
     *
     * @param bool $status
     * @return $this
     */
    public function muted($status = false);

    /**
     * Allows to set the preload behaviour
     *
     * @param string|null $preload
     * @return $this
     */
    public function preload($preload = null);
}
