<?php
/**
 * Dumps the engine's HTML output for every CommonMark + GFM example (both the
 * urlsLinked=false and =true code paths) to a snapshot file, and prints a
 * sha256 over the whole corpus. Run before and after an engine change to prove
 * the change is byte-identical.
 *
 * Usage: php tests/conformance/engine_snapshot.php /tmp/before.json
 */
require __DIR__ . '/../../vendor/autoload.php';

$outFile = $argv[1] ?? '/tmp/engine_snapshot.json';
$base = __DIR__ . '/fixtures';

$results = [];
foreach (['commonmark.json', 'gfm.json'] as $file) {
    $examples = json_decode(file_get_contents("$base/$file"), true);
    foreach ($examples as $i => $e) {
        foreach ([false, true] as $gfm) {
            $p = new \Parsedown();
            $p->setUrlsLinked($gfm);
            $key = $file . ':' . $i . ':' . ($gfm ? 'gfm' : 'cm');
            try {
                $results[$key] = $p->text($e['markdown']);
            } catch (\Throwable $t) {
                $results[$key] = 'EXCEPTION:' . get_class($t) . ':' . $t->getMessage();
            }
        }
    }
}

file_put_contents($outFile, json_encode($results, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
printf("Snapshot: %d outputs -> %s\nsha256: %s\n", count($results), $outFile, hash('sha256', json_encode($results)));
