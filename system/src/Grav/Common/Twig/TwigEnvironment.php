<?php
/**
 * @package    Grav.Common.Twig
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Twig;

class TwigEnvironment extends \Twig_Environment
{
    use WriteCacheFileTrait;
}
