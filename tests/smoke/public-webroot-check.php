<?php

/**
 * Smoke check for the single-folder and public webroot layouts, used by CI on Linux and Windows.
 *
 * Usage: php tests/smoke/public-webroot-check.php <single|split>
 *
 * Run from the Grav folder. Serves the webroot with PHP's built-in server and system/router.php,
 * then checks the homepage, every local link on it, page media URLs, published system files and
 * a few private paths. Exits with 1 when any check fails.
 */

$mode = $argv[1] ?? '';
if (!in_array($mode, ['single', 'split'], true)) {
    fwrite(STDERR, "Usage: php tests/smoke/public-webroot-check.php <single|split>\n");
    exit(2);
}

$root = str_replace('\\', '/', getcwd());
$docroot = $mode === 'split' ? $root . '/public' : $root;
$router = $root . '/system/router.php';
$host = '127.0.0.1:8123';
$base = 'http://' . $host;

$failures = 0;
$check = static function (bool $ok, string $label) use (&$failures): void {
    echo ($ok ? '[pass] ' : '[FAIL] ') . $label . "\n";
    if (!$ok) {
        $failures++;
    }
};

$get = static function (string $path) use ($base): array {
    $context = stream_context_create(['http' => ['ignore_errors' => true, 'follow_location' => 0, 'timeout' => 30]]);
    $body = @file_get_contents($base . $path, false, $context);
    $status = 0;
    foreach ($http_response_header ?? [] as $header) {
        if (preg_match('`^HTTP/\S+\s+(\d{3})`', $header, $m)) {
            $status = (int) $m[1];
        }
    }

    return [$status, $body === false ? '' : $body];
};

$server = proc_open(
    [PHP_BINARY, '-S', $host, '-t', $docroot, $router],
    [0 => ['pipe', 'r'], 1 => ['file', $root . '/tmp/smoke-server.log', 'a'], 2 => ['file', $root . '/tmp/smoke-server.log', 'a']],
    $pipes,
    $docroot
);
if (!is_resource($server)) {
    fwrite(STDERR, "Could not start the PHP built-in server\n");
    exit(1);
}

try {
    $status = 0;
    for ($i = 0; $i < 60 && $status === 0; $i++) {
        usleep(250000);
        [$status] = $get('/robots.txt');
    }

    echo "== {$mode} layout, docroot {$docroot}\n";

    [$status, $html] = $get('/');
    $check($status === 200, "GET / returns 200 (got {$status})");

    preg_match_all('`(?:src|href)="(/[^"#?]*(?:\?[^"#]*)?)"`', $html, $m);
    $links = array_values(array_unique($m[1]));
    $check(count($links) > 0, count($links) . ' local links found on the homepage');
    foreach ($links as $link) {
        $clean = html_entity_decode($link);
        $sane = !str_contains($clean, '\\') && !preg_match('`^/[a-z]:/`i', $clean) && !str_contains($clean, '/public/') && !str_contains($clean, $root);
        $check($sane, "link has no filesystem path in it: {$clean}");
        [$status] = $get($clean);
        $check($status === 200, "GET {$clean} returns 200 (got {$status})");
    }

    $check(str_contains($html, 'smoke.png'), 'original page image is on the homepage');
    $check((bool) preg_match('`/images/[0-9a-f/]+-smoke\.png`', $html), 'resized page image points into /images/');
    $check(str_contains($html, 'github-markdown-alerts'), 'symlinked plugin adds its assets to the page');

    foreach (['/composer.json', '/vendor/autoload.php', '/system/defines.php', '/system/config/system.yaml', '/user/config/system.yaml', '/user/pages/01.home/default.md', '/bin/grav'] as $private) {
        [$status] = $get($private);
        $check($status !== 200, "GET {$private} is not served (got {$status})");
    }

    if ($mode === 'split') {
        [$status] = $get('/system/assets/jquery/jquery-3.x.min.js');
        $check($status === 200, "published /system/assets/jquery/jquery-3.x.min.js returns 200 (got {$status})");
        [$status] = $get('/system/.published.json');
        $check($status !== 200, "GET /system/.published.json is not served (got {$status})");
        $check(!file_exists($root . '/user') && !file_exists($root . '/index.php'), 'no user/ or index.php left in the Grav folder');
    }
} finally {
    proc_terminate($server);
    proc_close($server);
}

echo $failures ? "{$failures} check(s) failed\n" : "All checks passed\n";
exit($failures ? 1 : 0);
