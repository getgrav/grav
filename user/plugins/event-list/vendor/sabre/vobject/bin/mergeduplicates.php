#!/usr/bin/env php
<?php

namespace Sabre\VObject;

// This sucks.. we have to try to find the composer autoloader. But chances
// are, we can't find it this way. So we'll do our bestest
$paths = [
    __DIR__.'/../vendor/autoload.php',  // In case vobject is cloned directly
    __DIR__.'/../../../autoload.php',   // In case vobject is a composer dependency.
];

foreach ($paths as $path) {
    if (file_exists($path)) {
        include $path;
        break;
    }
}

if (!class_exists('Sabre\\VObject\\Version')) {
    fwrite(STDERR, "Composer autoloader could not be loaded.\n");
    exit(1);
}

echo 'sabre/vobject ', Version::VERSION, " duplicate contact merge tool\n";

if ($argc < 3) {
    echo "\n";
    echo 'Usage: ', $argv[0], " input.vcf output.vcf [debug.log]\n";
    exit(1);
}

$input = fopen($argv[1], 'r');
$output = fopen($argv[2], 'w');
$debug = isset($argv[3]) ? fopen($argv[3], 'w') : null;

$splitter = new Splitter\VCard($input);

// The following properties are ignored. If they appear in some vcards
// but not in others, we don't consider them for the sake of finding
// differences.
$ignoredProperties = [
    'PRODID',
    'VERSION',
    'REV',
    'UID',
    'X-ABLABEL',
];

$collectedNames = [];

$stats = [
    'Total vcards' => 0,
    'No FN property' => 0,
    'Ignored duplicates' => 0,
    'Merged values' => 0,
    'Error' => 0,
    'Unique cards' => 0,
    'Total written' => 0,
];

function writeStats()
{
    global $stats;
    foreach ($stats as $name => $value) {
        echo str_pad($name, 23, ' ', STR_PAD_RIGHT), str_pad($value, 6, ' ', STR_PAD_LEFT), "\n";
    }
    // Moving cursor back a few lines.
    echo "\033[".count($stats).'A';
}

function write($vcard)
{
    global $stats, $output;

    ++$stats['Total written'];
    fwrite($output, $vcard->serialize()."\n");
}

while ($vcard = $splitter->getNext()) {
    ++$stats['Total vcards'];
    writeStats();

    $fn = isset($vcard->FN) ? (string) $vcard->FN : null;

    if (empty($fn)) {
        // Immediately write this vcard, we don't compare it.
        ++$stats['No FN property'];
        ++$stats['Unique cards'];
        write($vcard);
        $vcard->destroy();
        continue;
    }

    if (!isset($collectedNames[$fn])) {
        $collectedNames[$fn] = $vcard;
        ++$stats['Unique cards'];
        continue;
    } else {
        // Starting comparison for all properties. We only check if properties
        // in the current vcard exactly appear in the earlier vcard as well.
        foreach ($vcard->children() as $newProp) {
            if (in_array($newProp->name, $ignoredProperties)) {
                // We don't care about properties such as UID and REV.
                continue;
            }
            $ok = false;
            foreach ($collectedNames[$fn]->select($newProp->name) as $compareProp) {
                if ($compareProp->serialize() === $newProp->serialize()) {
                    $ok = true;
                    break;
                }
            }

            if (!$ok) {
                if ('EMAIL' === $newProp->name || 'TEL' === $newProp->name) {
                    // We're going to make another attempt to find this
                    // property, this time just by value. If we find it, we
                    // consider it a success.
                    foreach ($collectedNames[$fn]->select($newProp->name) as $compareProp) {
                        if ($compareProp->getValue() === $newProp->getValue()) {
                            $ok = true;
                            break;
                        }
                    }

                    if (!$ok) {
                        // Merging the new value in the old vcard.
                        $collectedNames[$fn]->add(clone $newProp);
                        $ok = true;
                        ++$stats['Merged values'];
                    }
                }
            }

            if (!$ok) {
                // echo $newProp->serialize() . " does not appear in earlier vcard!\n";
                ++$stats['Error'];
                if ($debug) {
                    fwrite($debug, "Missing '".$newProp->name."' property in duplicate. Earlier vcard:\n".$collectedNames[$fn]->serialize()."\n\nLater:\n".$vcard->serialize()."\n\n");
                }

                $vcard->destroy();
                continue 2;
            }
        }
    }

    $vcard->destroy();
    ++$stats['Ignored duplicates'];
}

foreach ($collectedNames as $vcard) {
    // Overwriting any old PRODID
    $vcard->PRODID = '-//Sabre//Sabre VObject '.Version::VERSION.'//EN';
    write($vcard);
    writeStats();
}

echo str_repeat("\n", count($stats)), "\nDone.\n";
