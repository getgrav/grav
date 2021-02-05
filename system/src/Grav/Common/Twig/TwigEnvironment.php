<?php

/**
 * @package    Grav\Common\Twig
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Twig;

use Twig\Environment;

/**
 * Class TwigEnvironment
 * @package Grav\Common\Twig
 */
class TwigEnvironment extends Environment
{
    use WriteCacheFileTrait;
}
