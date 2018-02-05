<?php
/**
 * @package    Grav.Common.Twig
 *
 * @copyright  Copyright (C) 2014 - 2017 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Twig;

class TwigEnvironment extends \Twig_Environment
{
    use WriteCacheFileTrait;
}
