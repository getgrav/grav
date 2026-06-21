<?php
/**
 * Extracts the example cases from the GitHub Flavored Markdown spec.txt
 * (github/cmark-gfm test/spec.txt) into the same JSON shape CommonMark
 * publishes: [{markdown, html, example, section, extension}, ...].
 *
 * Run: php tests/conformance/extract_gfm.php
 */

$src = __DIR__ . '/fixtures/gfm-spec.txt';
$out = __DIR__ . '/fixtures/gfm.json';

$lines = explode("\n", file_get_contents($src));

$examples = [];
$section = '';
$extension = null;        // current "extension" block label, if any
$num = 0;

$state = 'text';          // text | md | html
$fence = '';
$md = $html = '';

foreach ($lines as $line) {
    // Track section headings (ATX). The GFM spec uses ## Headings.
    if ($state === 'text' && preg_match('/^#{1,6}\s+(.*?)\s*#*\s*$/', $line, $m)) {
        $section = trim($m[1]);
        continue;
    }
    // Track extension regions: spec wraps GFM extensions in
    // <div class="extension"> ... </div>
    if ($state === 'text' && preg_match('/<div class="extension">/', $line)) {
        $extension = $section ?: 'extension';
        continue;
    }
    if ($state === 'text' && preg_match('#</div>#', $line)) {
        $extension = null;
        continue;
    }

    // Example fences are >= ~30 backticks followed by "example".
    if ($state === 'text' && preg_match('/^(`{10,})\s*example\b/', $line, $m)) {
        $state = 'md';
        $fence = $m[1];
        $md = $html = '';
        continue;
    }
    if ($state === 'md') {
        if ($line === '.') { $state = 'html'; continue; }
        $md .= $line . "\n";
        continue;
    }
    if ($state === 'html') {
        if (preg_match('/^' . preg_quote($fence, '/') . '\s*$/', $line)) {
            $num++;
            // spec.txt encodes tabs as → (U+2192)
            $examples[] = [
                'markdown'  => str_replace('→', "\t", $md),
                'html'      => str_replace('→', "\t", $html),
                'example'   => $num,
                'section'   => $section,
                'extension' => $extension,
            ];
            $state = 'text';
            continue;
        }
        $html .= $line . "\n";
        continue;
    }
}

file_put_contents($out, json_encode($examples, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

$bySection = [];
foreach ($examples as $e) {
    $key = ($e['extension'] ? '[ext] ' : '') . $e['section'];
    $bySection[$key] = ($bySection[$key] ?? 0) + 1;
}
printf("Extracted %d GFM examples to %s\n", count($examples), $out);
echo "Extension sections:\n";
foreach ($bySection as $k => $v) {
    if (str_starts_with($k, '[ext]')) {
        printf("  %-40s %d\n", $k, $v);
    }
}
