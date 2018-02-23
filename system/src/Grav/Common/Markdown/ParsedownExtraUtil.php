<?php

// Escape '<' and '>' inside code fences
function escapeFences($text)
{
    $rx = "#(^|\n)```.*?\n```#s";
    return preg_replace_callback($rx, function ($m) {
        $match = $m[0];
        $ret = str_replace("<", "___LT___", $match);
        $ret = str_replace(">", "___GT___", $ret);
        return $ret;
    
    }, $text);
}

function unescapeFences($text)
{
    $text = str_replace("___LT___", "&lt;", $text);
    $text = str_replace("___GT___", "&gt;", $text);
    return $text;
}