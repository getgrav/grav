<?php
namespace Grav\Common\Markdown;

class Markdown extends \Parsedown
{
    use MarkdownGravLinkTrait;

    function __construct($page)
    {
        $this->page = $page;
    }

}
