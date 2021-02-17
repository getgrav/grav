<?php

/**
 * @package    Grav\Common\GPM
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\GPM\Remote;

/**
 * Class Plugins
 * @package Grav\Common\GPM\Remote
 */
class Plugins extends AbstractPackageCollection
{
    /** @var string */
    protected $type = 'plugins';
    /** @var string */
    protected $repository = 'https://getgrav.org/downloads/plugins.json';

    /**
     * Local Plugins Constructor
     * @param bool $refresh
     * @param callable|null $callback Either a function or callback in array notation
     */
    public function __construct($refresh = false, $callback = null)
    {
        parent::__construct($this->repository, $refresh, $callback);
    }
}
