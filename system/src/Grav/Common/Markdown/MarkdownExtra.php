<?php
namespace Grav\Common\Markdown;

class MarkdownExtra extends \ParsedownExtra
{
    use MarkdownGravLinkTrait;

    public function __construct($page)
    {
        parent::__construct();
        $this->init($page);
    }
}
