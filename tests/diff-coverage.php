<?php

/**
 * Diff coverage: which changed lines in system/src does the unit suite actually run?
 *
 * Runs the unit suite with code coverage, then lists every executable line changed since <base>
 * (committed or not) that no test executes. Files that changed but never load in a test are
 * listed too; for those, only docblock or signature changes are fine without a test.
 *
 * Usage:
 *   composer diff-coverage                # changes since the latest tag
 *   composer diff-coverage -- 2.2.32      # changes since a tag, branch or commit
 *   composer diff-coverage -- --no-run    # reuse tests/_output/coverage.xml from the last run
 *   composer diff-coverage -- --strict    # exit 1 when a changed line is not covered
 *
 * Needs Xdebug or PCOV in the PHP that runs it. Set GRAV_COVERAGE_PHP to use another PHP binary
 * for the test run, e.g. GRAV_COVERAGE_PHP=/opt/homebrew/opt/php@8.3/bin/php.
 */

$root = dirname(__DIR__);
chdir($root);

$args = array_slice($argv, 1);
$run = !in_array('--no-run', $args, true);
$strict = in_array('--strict', $args, true);
$positional = array_values(array_filter($args, static fn($a) => !str_starts_with($a, '--')));
$base = $positional[0] ?? trim((string) shell_exec('git describe --tags --abbrev=0 2>/dev/null'));
if ($base === '') {
    fwrite(STDERR, "No base given and no tag found. Usage: php tests/diff-coverage.php <base>\n");
    exit(2);
}

$clover = $root . '/tests/_output/coverage.xml';

if ($run) {
    $php = getenv('GRAV_COVERAGE_PHP') ?: PHP_BINARY;
    $probe = escapeshellarg($php) . " -r 'echo (extension_loaded(\"xdebug\") || extension_loaded(\"pcov\")) ? 1 : 0;'";
    if (trim((string) shell_exec($probe)) !== '1') {
        fwrite(STDERR, "{$php} has neither Xdebug nor PCOV loaded. Set GRAV_COVERAGE_PHP to a PHP that has one.\n");
        exit(2);
    }

    @unlink($clover);
    $command = 'XDEBUG_MODE=coverage ' . escapeshellarg($php) . ' -d memory_limit=-1 vendor/bin/codecept run unit --no-colors --coverage-xml'
        . ' -o ' . escapeshellarg('coverage: enabled: true')
        . ' -o ' . escapeshellarg('coverage: include: [system/src/*]')
        . ' -o ' . escapeshellarg('settings: memory_limit: 4096M');
    fwrite(STDERR, "Running the unit suite with coverage (a few minutes)...\n");
    exec($command . ' 2>&1', $output, $status);
    $summary = preg_grep('/^(OK \(|Tests: |FAILURES|ERRORS)/', $output);
    fwrite(STDERR, implode("\n", $summary ?: array_slice($output, -5)) . "\n\n");
    if ($status !== 0) {
        fwrite(STDERR, "The test run failed; coverage below may be incomplete.\n\n");
    }
}

if (!is_file($clover)) {
    fwrite(STDERR, "No coverage report at {$clover}.\n");
    exit(2);
}

// Changed lines per file, against the working tree so uncommitted work counts too.
$diff = (string) shell_exec('git diff -U0 ' . escapeshellarg($base) . ' -- system/src');
$changed = [];
$file = null;
foreach (explode("\n", $diff) as $line) {
    if (str_starts_with($line, '+++ ')) {
        $path = substr($line, 4);
        $file = $path === '/dev/null' ? null : substr($path, 2);
    } elseif ($file && preg_match('/^@@ -\S+ \+(\d+)(?:,(\d+))? @@/', $line, $m)) {
        $count = isset($m[2]) ? (int) $m[2] : 1;
        for ($i = 0; $i < $count; $i++) {
            $changed[$file][(int) $m[1] + $i] = true;
        }
    }
}

// Executable statements and their hit counts from the Clover report.
$coverage = [];
$xml = simplexml_load_file($clover);
foreach ($xml->xpath('//file') as $node) {
    $name = ltrim(str_replace($root, '', (string) $node['name']), '/');
    foreach ($node->line as $l) {
        if ((string) $l['type'] === 'stmt') {
            $coverage[$name][(int) $l['num']] = (int) $l['count'];
        }
    }
}

ksort($changed);
$total = $covered = 0;
$lines = [];
foreach ($changed as $path => $numbers) {
    if (!str_ends_with($path, '.php')) {
        continue;
    }
    if (!isset($coverage[$path])) {
        $lines[] = "{$path}: never loaded by a test";
        continue;
    }
    $source = file($root . '/' . $path, FILE_IGNORE_NEW_LINES) ?: [];
    $executable = array_intersect_key($coverage[$path], $numbers);
    ksort($executable);
    $missed = array_keys(array_filter($executable, static fn($hits) => $hits === 0));
    $total += count($executable);
    $covered += count($executable) - count($missed);
    if (!$executable) {
        continue;
    }
    $lines[] = sprintf('%s: %d/%d changed statements covered', $path, count($executable) - count($missed), count($executable));
    foreach ($missed as $number) {
        $lines[] = sprintf('    %d: %s', $number, mb_strimwidth(trim($source[$number - 1] ?? ''), 0, 110, '…'));
    }
}

printf("Changes since %s: %d/%d changed statements covered (%d%%)\n\n", $base, $covered, $total, $total ? intdiv($covered * 100, $total) : 100);
echo implode("\n", $lines), "\n";

exit($strict && $covered < $total ? 1 : 0);
