<?php
namespace Grav\Common\Markdown;

class Markdown extends \Parsedown
{
    use MarkdownGravLinkTrait;

    public function __construct($page)
    {
        $this->init($page);
    }

}
