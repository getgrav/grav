<?php

/**
 * @package    Grav\Common\Markdown
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Markdown;

use Exception;
use Grav\Common\Page\Interfaces\PageInterface;
use Grav\Common\Page\Markdown\Excerpts;

/**
 * Class ParsedownExtra
 * @package Grav\Common\Markdown
 */
class ParsedownExtra extends \ParsedownExtra
{
    use ParsedownGravTrait;

    /**
     * ParsedownExtra constructor.
     *
     * @param Excerpts|PageInterface|null $excerpts
     * @param array|null $defaults
     * @throws Exception
     */
    public function __construct($excerpts = null, $defaults = null)
    {
        if (!$excerpts || $excerpts instanceof PageInterface || null !== $defaults) {
            // Deprecated in Grav 1.6.10
            if ($defaults) {
                $defaults = ['markdown' => $defaults];
            }
            $excerpts = new Excerpts($excerpts, $defaults);
            user_error(__CLASS__ . '::' . __FUNCTION__ . '($page, $defaults) is deprecated since Grav 1.6.10, use new ' . __CLASS__ . '(new ' . Excerpts::class . '($page, [\'markdown\' => $defaults])) instead.', E_USER_DEPRECATED);
        }

        parent::__construct();

        $this->init($excerpts, $defaults);
    }
}
