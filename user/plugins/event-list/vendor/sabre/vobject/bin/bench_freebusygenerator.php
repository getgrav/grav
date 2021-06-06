<?php

include __DIR__.'/../vendor/autoload.php';

if ($argc < 2) {
    echo 'sabre/vobject ', Sabre\VObject\Version::VERSION, " freebusy benchmark\n";
    echo "\n";
    echo "This script can be used to measure the speed of generating a\n";
    echo "free-busy report based on a calendar.\n";
    echo "\n";
    echo "The process will be repeated 100 times to get accurate stats\n";
    echo "\n";
    echo 'Usage: '.$argv[0]." inputfile.ics\n";
    exit();
}

list(, $inputFile) = $argv;

$bench = new Hoa\Bench\Bench();
$bench->parse->start();

$vcal = Sabre\VObject\Reader::read(fopen($inputFile, 'r'));

$bench->parse->stop();

$repeat = 100;
$start = new \DateTime('2000-01-01');
$end = new \DateTime('2020-01-01');
$timeZone = new \DateTimeZone('America/Toronto');

$bench->fb->start();

for ($i = 0; $i < $repeat; ++$i) {
    $fb = new Sabre\VObject\FreeBusyGenerator($start, $end, $vcal, $timeZone);
    $results = $fb->getResult();
}
$bench->fb->stop();

echo $bench,"\n";

function formatMemory($input)
{
    if (strlen($input) > 6) {
        return round($input / (1024 * 1024)).'M';
    } elseif (strlen($input) > 3) {
        return round($input / 1024).'K';
    }
}

unset($input, $splitter);

echo 'peak memory usage: '.formatMemory(memory_get_peak_usage()), "\n";
echo 'current memory usage: '.formatMemory(memory_get_usage()), "\n";
