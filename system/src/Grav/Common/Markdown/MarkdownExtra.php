<?php
namespace Grav\Common\Markdown;

class MarkdownExtra extends \ParsedownExtra
{
    use MarkdownGravLinkTrait;

    function __construct($page)
    {
        parent::__construct();
        $this->page = $page;
    }
}
