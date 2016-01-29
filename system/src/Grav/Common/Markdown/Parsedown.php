<?php
namespace Grav\Common\Markdown;

/**
 * Class Parsedown
 * @package Grav\Common\Markdown
 */
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
