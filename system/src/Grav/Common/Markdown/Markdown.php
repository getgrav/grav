<?php
namespace Grav\Common\Markdown;

class Markdown extends \Parsedown
{
    use MarkdownGravLinkTrait;

    public function __construct($page)
    {
        $this->page = $page;
        $this->BlockTypes['{'] [] = "TwigTag";
    }

}
