<?php

include __DIR__.'/../vendor/autoload.php';

if ($argc < 4) {
    echo 'sabre/vobject ', Sabre\VObject\Version::VERSION, " RRULE benchmark\n";
    echo "\n";
    echo "This script can be used to measure the speed of the 'recurrence expansion'\n";
    echo 'system.';
    echo "\n";
    echo 'Usage: '.$argv[0]." inputfile.ics startdate enddate\n";
    exit();
}

list(, $inputFile, $startDate, $endDate) = $argv;

$bench = new Hoa\Bench\Bench();
$bench->parse->start();

echo "Parsing.\n";
$vobj = Sabre\VObject\Reader::read(fopen($inputFile, 'r'));

$bench->parse->stop();

echo "Expanding.\n";
$bench->expand->start();

$vobj->expand(new DateTime($startDate), new DateTime($endDate));

$bench->expand->stop();

echo $bench,"\n";
