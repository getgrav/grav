<?php
namespace Grav\Common\Markdown;

class ParsedownExtra extends \ParsedownExtra
{
    use ParsedownGravTrait;

    public function __construct($page, $defaults)
    {
        parent::__construct();
        $this->init($page, $defaults);
    }
}
