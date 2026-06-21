<?php
/**
 * Markdown render micro-benchmark.
 *
 *   php tests/conformance/bench_markdown.php [iterations] [doc.md]
 *
 * Boots Grav, then renders one representative page many times under several
 * markdown configurations, reporting median / mean / p95 render time and peak
 * memory. The render (text()) is timed; the parser is constructed fresh each
 * iteration (outside the timer) so each measurement is a clean page render.
 */

namespace Grav;

use Grav\Common\Grav;
use Grav\Common\Markdown\Parsedown;
use Grav\Common\Page\Markdown\Excerpts;

$dir = __DIR__;
while ($dir !== '/' && !is_file($dir . '/vendor/autoload.php')) {
    $dir = dirname($dir);
}
require $dir . '/vendor/autoload.php';

$iterations = (int)($argv[1] ?? 3000);
$warmup     = (int)($iterations / 10);
$docPath    = $argv[2] ?? __DIR__ . '/fixtures/bench_doc.md';

// --- boot Grav (mirrors tests/_bootstrap.php) -------------------------------
$grav = Grav::instance();
$grav['config']->init();
$grav['config']->set('system.languages.supported', []);
foreach (array_keys($grav['setup']->getStreams()) as $stream) {
    @stream_wrapper_unregister($stream);
}
$grav['streams'];
$grav['uri']->init();
$grav['config']->set('system.cache.enabled', false);
$locator = $grav['locator'];
if (is_dir(dirname(__DIR__, 1) . '/fake/nested-site/user/pages')) {
    $locator->addPath('page', '', 'tests/fake/nested-site/user/pages', false);
}
$grav['pages']->init();
$page = $grav['pages']->find('/item2/item2-2');
if ($page === null) {                       // portable: any page works (used only for URL resolution)
    foreach ($grav['pages']->instances() as $p) {
        $page = $p;
        break;
    }
}
if ($page === null) {
    fwrite(STDERR, "No page available to construct Excerpts.\n");
    exit(1);
}

// --- load the document ------------------------------------------------------
$raw = file_get_contents($docPath);
if ($raw === false) {
    fwrite(STDERR, "Cannot read doc: $docPath\n");
    exit(1);
}
$doc = preg_replace('/^---\R.*?\R---\R/s', '', $raw); // strip YAML front matter
$bytes = strlen($doc);

// --- benchmark one configuration --------------------------------------------
function bench(callable $makeParser, string $doc, int $iterations, int $warmup): array
{
    for ($i = 0; $i < $warmup; $i++) {
        $makeParser()->text($doc);
    }
    // Per-render transient peak memory (PHP 8.2+).
    $renderPeakKB = 0.0;
    if (function_exists('memory_reset_peak_usage')) {
        memory_reset_peak_usage();
        $before = memory_get_peak_usage();
        $makeParser()->text($doc);
        $renderPeakKB = (memory_get_peak_usage() - $before) / 1024;
    }

    $times = [];
    for ($i = 0; $i < $iterations; $i++) {
        $parser = $makeParser();              // construction excluded from the timer
        $t0 = hrtime(true);
        $parser->text($doc);
        $times[] = (hrtime(true) - $t0) / 1e6; // ms
    }
    sort($times);
    $n = count($times);
    $sum = array_sum($times);
    return [
        'median' => $times[(int)($n * 0.5)],
        'mean'   => $sum / $n,
        'p95'    => $times[(int)($n * 0.95)],
        'min'    => $times[0],
        'opsPerSec' => 1000.0 / ($sum / $n),
        'renderKB' => $renderPeakKB,
    ];
}

$mk = static function (array $markdown) use ($page) {
    return static function () use ($page, $markdown) {
        return new Parsedown(new Excerpts($page, ['markdown' => $markdown, 'images' => []]));
    };
};

$gfmOff = ['task_lists' => false, 'marks' => false, 'tagfilter' => false, 'autolinks' => false];
$only = static function (string $feature) use ($gfmOff) {
    return ['extra' => false, 'gfm' => array_merge($gfmOff, [$feature => true])];
};

$configs = [
    'baseline (gfm off, ~1.7)'      => ['extra' => false, 'gfm' => $gfmOff],
    '+ task_lists only'             => $only('task_lists'),
    '+ marks only'                  => $only('marks'),
    '+ tagfilter only'              => $only('tagfilter'),
    '+ autolinks only'              => $only('autolinks'),
    'enhanced (all gfm on)'         => ['extra' => false],
];

printf("\nGrav %s  |  PHP %s  |  doc %d bytes  |  %d iterations (warmup %d)\n",
    defined('GRAV_VERSION') ? GRAV_VERSION : '?', PHP_VERSION, $bytes, $iterations, $warmup);
printf("%-32s %9s %9s %9s %9s %12s %10s\n", 'config', 'median', 'mean', 'p95', 'min', 'ops/sec', 'renderKB');
printf("%s\n", str_repeat('-', 96));

$results = [];
foreach ($configs as $label => $markdown) {
    $r = bench($mk($markdown), $doc, $iterations, $warmup);
    $results[$label] = $r;
    printf("%-32s %8.4f %8.4f %8.4f %8.4f %12.0f %9.1f\n",
        $label, $r['median'], $r['mean'], $r['p95'], $r['min'], $r['opsPerSec'], $r['renderKB']);
}

$base = $results['baseline (gfm off, ~1.7)']['min'];
$enh  = $results['enhanced (all gfm on)']['min'];
printf("\nEnhancement overhead (min, least noisy): %+.1f%%  (%.4f -> %.4f ms)\n",
    ($enh - $base) / $base * 100, $base, $enh);
