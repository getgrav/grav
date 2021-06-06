<?php

include __DIR__.'/../vendor/autoload.php';

if ($argc < 2) {
    echo 'sabre/vobject ', Sabre\VObject\Version::VERSION, " manipulation benchmark\n";
    echo "\n";
    echo "This script can be used to measure the speed of opening a large amount of\n";
    echo "vcards, making a few alterations and serializing them again.\n";
    echo 'system.';
    echo "\n";
    echo 'Usage: '.$argv[0]." inputfile.vcf\n";
    exit();
}

list(, $inputFile) = $argv;

$input = file_get_contents($inputFile);

$splitter = new Sabre\VObject\Splitter\VCard($input);

$bench = new Hoa\Bench\Bench();

while (true) {
    $bench->parse->start();
    $vcard = $splitter->getNext();
    $bench->parse->pause();

    if (!$vcard) {
        break;
    }

    $bench->manipulate->start();
    $vcard->{'X-FOO'} = 'Random new value!';
    $emails = [];
    if (isset($vcard->EMAIL)) {
        foreach ($vcard->EMAIL as $email) {
            $emails[] = (string) $email;
        }
    }
    $bench->manipulate->pause();

    $bench->serialize->start();
    $vcard2 = $vcard->serialize();
    $bench->serialize->pause();

    $vcard->destroy();
}

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
