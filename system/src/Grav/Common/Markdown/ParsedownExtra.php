<?php
namespace Grav\Common\Markdown;

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
     * @param $page
     * @param $defaults
     */
    public function __construct($page, $defaults)
    {
        parent::__construct();
        $this->init($page, $defaults);
    }
}
