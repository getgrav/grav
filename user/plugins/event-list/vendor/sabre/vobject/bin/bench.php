#!/usr/bin/env php
<?php

include __DIR__.'/../vendor/autoload.php';

$data = stream_get_contents(STDIN);

$start = microtime(true);

$lol = Sabre\VObject\Reader::read($data);

echo 'time: '.(microtime(true) - $start)."\n";
