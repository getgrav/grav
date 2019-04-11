<?php

/**
 * @package    Grav\Common\Markdown
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Markdown;

use Grav\Common\Page\Interfaces\PageInterface;

class ParsedownExtra extends \ParsedownExtra
{
    use ParsedownGravTrait;

    /**
     * ParsedownExtra constructor.
     *
     * @param PageInterface $page
     * @param array|null $defaults
     * @throws \Exception
     */
    public function __construct($page, $defaults)
    {
        parent::__construct();

        $this->init($page, $defaults);
    }
}
