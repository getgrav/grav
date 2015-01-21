<?php
namespace Grav\Common\Markdown;

class ParsedownExtra extends \ParsedownExtra
{
    use ParsedownGravTrait;

    public function __construct($page)
    {
        parent::__construct();
        $this->init($page);
    }
}
