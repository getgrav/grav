<?php
/**
 * CommonMark / GFM conformance harness for Grav's vendored Parsedown.
 *
 * Runs each spec example through the engine and reports a per-section
 * pass/fail matrix (strict and loosely-normalized), so the GFM gap is
 * visible and tracked rather than guessed. Engine-level: tests the raw
 * vendored \Parsedown (the plan's `(new \Parsedown())->text()`), not the
 * Grav wrapper (which adds special-char escaping on top).
 *
 * Usage:
 *   php tests/conformance/run.php [commonmark|gfm|both] [--save] [--section=NAME] [--show-fails=N]
 */

require __DIR__ . '/../../vendor/autoload.php';

$args = $argv;
array_shift($args);
$which = 'both';
$save = false;
$only = null;
$showFails = 0;
foreach ($args as $a) {
    if ($a === '--save') { $save = true; }
    elseif (str_starts_with($a, '--section=')) { $only = substr($a, 10); }
    elseif (str_starts_with($a, '--show-fails=')) { $showFails = (int) substr($a, 13); }
    elseif (in_array($a, ['commonmark', 'gfm', 'both'], true)) { $which = $a; }
}

/** Light, pre/code-protected normalization for a fairer "feature" comparison. */
function loose(string $html): string {
    $parts = preg_split('#(<pre.*?</pre>)#is', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
    $out = '';
    foreach ($parts as $i => $p) {
        $out .= ($i % 2 === 1) ? $p : preg_replace('/>\s+</', '><', $p);
    }
    $out = preg_replace('#\s*/>#', ' />', $out);   // normalize void-tag spacing
    return trim($out);
}

function makeParser(bool $gfm): \Parsedown {
    $p = new \Parsedown();
    // GFM linkifies bare URLs; CommonMark core does not.
    $p->setUrlsLinked($gfm);
    return $p;
}

/** @return array{0:array,1:array} [perSection, fails] */
function runSet(array $examples, bool $gfm, ?string $only): array {
    $sections = [];
    $fails = [];
    foreach ($examples as $e) {
        $section = ($e['extension'] ?? null ? '[ext] ' : '') . ($e['section'] ?? '?');
        if ($only !== null && stripos($section, $only) === false) { continue; }
        $sections[$section] ??= ['total' => 0, 'strict' => 0, 'loose' => 0];
        $sections[$section]['total']++;

        try {
            $actual = makeParser($gfm)->text($e['markdown']);
        } catch (\Throwable $t) {
            $actual = '!!EXCEPTION: ' . $t->getMessage();
        }
        $expected = $e['html'];

        $strict = rtrim($actual) === rtrim($expected);
        $loose  = loose($actual) === loose($expected);
        if ($strict) { $sections[$section]['strict']++; }
        if ($loose)  { $sections[$section]['loose']++; }
        if (!$loose) {
            $fails[$section][] = $e + ['actual' => $actual];
        }
    }
    return [$sections, $fails];
}

$base = __DIR__ . '/fixtures';
$datasets = [];
if ($which === 'commonmark' || $which === 'both') {
    $datasets['CommonMark'] = [json_decode(file_get_contents("$base/commonmark.json"), true), false];
}
if ($which === 'gfm' || $which === 'both') {
    $datasets['GFM'] = [json_decode(file_get_contents("$base/gfm.json"), true), true];
}

$baseline = [];
foreach ($datasets as $name => [$examples, $gfm]) {
    [$sections, $fails] = runSet($examples, $gfm, $only);
    ksort($sections);

    echo "\n══════════════════════════════════════════════════════════════════\n";
    echo " $name  (parser: base Parsedown, urlsLinked=" . ($gfm ? 'true' : 'false') . ")\n";
    echo "══════════════════════════════════════════════════════════════════\n";
    printf("%-42s %6s %8s %8s\n", 'Section', 'n', 'strict', 'loose');
    echo str_repeat('-', 68) . "\n";
    $tot = ['total' => 0, 'strict' => 0, 'loose' => 0];
    foreach ($sections as $s => $c) {
        printf("%-42s %6d %7d%% %7d%%\n", substr($s, 0, 42), $c['total'],
            round(100 * $c['strict'] / $c['total']), round(100 * $c['loose'] / $c['total']));
        foreach ($tot as $k => $_) { $tot[$k] += $c[$k]; }
        $baseline[$name][$s] = $c;
    }
    echo str_repeat('-', 68) . "\n";
    printf("%-42s %6d %7d%% %7d%%\n", 'TOTAL', $tot['total'],
        round(100 * $tot['strict'] / $tot['total']), round(100 * $tot['loose'] / $tot['total']));

    if ($showFails > 0) {
        echo "\n--- sample loose-failures ---\n";
        $shown = 0;
        foreach ($fails as $s => $list) {
            foreach ($list as $f) {
                if ($shown++ >= $showFails) { break 2; }
                echo "\n[$s #{$f['example']}]\nMD:  " . json_encode($f['markdown']) .
                     "\nEXP: " . json_encode($f['html']) .
                     "\nGOT: " . json_encode($f['actual']) . "\n";
            }
        }
    }
}

if ($save) {
    file_put_contents("$base/baseline.json", json_encode($baseline, JSON_PRETTY_PRINT));
    echo "\nBaseline written to $base/baseline.json\n";
}
