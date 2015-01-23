<?php
namespace Grav\Common\Markdown;

class Parsedown extends \Parsedown
{
    use ParsedownGravTrait;

    public function __construct($page)
    {
        $this->init($page);
    }

}
