<?php

/**
 * @package    Grav\Common\Twig\Exception
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Twig\Exception;

use RuntimeException;

/**
 * TwigException gets thrown when you use {% throw code message %} in twig.
 *
 * This allows Grav to catch 401, 403 and 404 exceptions and display proper error page.
 */
class TwigException extends RuntimeException
{
}
