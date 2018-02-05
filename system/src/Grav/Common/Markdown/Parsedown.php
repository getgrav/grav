<?php
/**
 * @package    Grav.Common.Markdown
 *
 * @copyright  Copyright (C) 2014 - 2017 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Markdown;

class Parsedown extends \Parsedown
{
    use ParsedownGravTrait;

    /**
     * Parsedown constructor.
     *
     * @param $page
     * @param $defaults
     */
    public function __construct($page, $defaults)
    {
        $this->init($page, $defaults);
    }

}
