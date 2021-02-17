<?php

/**
 * @package    Grav\Common\GPM
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\GPM\Remote;

/**
 * Class Themes
 * @package Grav\Common\GPM\Remote
 */
class Themes extends AbstractPackageCollection
{
    /** @var string */
    protected $type = 'themes';
    /** @var string */
    protected $repository = 'https://getgrav.org/downloads/themes.json';

    /**
     * Local Themes Constructor
     * @param bool $refresh
     * @param callable|null $callback Either a function or callback in array notation
     */
    public function __construct($refresh = false, $callback = null)
    {
        parent::__construct($this->repository, $refresh, $callback);
    }
}
