<?php
/**
 * @package    Grav.Common.Markdown
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Markdown;

class ParsedownExtra extends \ParsedownExtra
{
    use ParsedownGravTrait;

    /**
     * ParsedownExtra constructor.
     *
     * @param $page
     * @param $defaults
     * @throws \Exception
     */
    public function __construct($page, $defaults)
    {
        parent::__construct();

        $this->init($page, $defaults);
    }
}
