<?php

/**
 * Local GPM test server — simulates getgrav.org/downloads/grav.json
 *
 * Start with: php -S localhost:8043 bin/gpm-test-server.php
 *
 * Mode is controlled by writing a string to /tmp/gpm-test-mode:
 *   echo family  > /tmp/gpm-test-mode   (default) return latest in client's major.minor family
 *   echo global  > /tmp/gpm-test-mode   return globally latest (old server, no family filter)
 *   echo same    > /tmp/gpm-test-mode   return same version as client (no upgrade)
 */

$v       = $_GET['v'] ?? '';
$testing = isset($_GET['testing']);
$mode    = trim(@file_get_contents('/tmp/gpm-test-mode') ?: 'family');

// Simulated release list — newest first, mirrors a real GitHub releases page
$releases = [
    ['version' => '2.0.1',         'prerelease' => false],
    ['version' => '2.0.0',         'prerelease' => false],
    ['version' => '1.8.0-beta.29', 'prerelease' => true],
    ['version' => '1.8.0-beta.28', 'prerelease' => true],
    ['version' => '1.8.0-beta.25', 'prerelease' => true],
    ['version' => '1.7.51',        'prerelease' => false],
    ['version' => '1.7.50',        'prerelease' => false],
];

function extractFamily(string $ver): string
{
    $parts = explode('.', ltrim($ver, 'v'));
    return ($parts[0] ?? '0') . '.' . ($parts[1] ?? '0');
}

/**
 * Find the latest release in $family.
 * On stable channel: prefer a stable release, but fall back to latest prerelease if no stable
 * exists in that family yet (e.g. 1.8 is still all-beta).
 * On testing channel: just return the newest.
 */
function findLatestInFamily(array $releases, string $family, bool $testing): ?string
{
    $stableInFamily = null;
    $anyInFamily    = null;

    foreach ($releases as $r) {
        if (extractFamily($r['version']) !== $family) {
            continue;
        }
        if ($anyInFamily === null) {
            $anyInFamily = $r['version']; // newest in family (releases are sorted newest-first)
        }
        if (!$r['prerelease'] && $stableInFamily === null) {
            $stableInFamily = $r['version'];
        }
    }

    // On stable channel prefer a stable release; fall back to latest prerelease if no stable yet
    return ($testing ? $anyInFamily : ($stableInFamily ?? $anyInFamily));
}

function findGlobalLatest(array $releases, bool $testing): string
{
    foreach ($releases as $r) {
        if ($testing || !$r['prerelease']) {
            return $r['version'];
        }
    }
    return $releases[0]['version'];
}

// ── Resolve version to serve ──────────────────────────────────────────────────
$clientFamily = extractFamily($v);

switch ($mode) {
    case 'global':
        // Old server behaviour: always return the globally latest, ignore ?v
        $serveVersion = findGlobalLatest($releases, $testing);
        break;
    case 'same':
        // No upgrade scenario: mirror back whatever version the client has
        $serveVersion = $v ?: findGlobalLatest($releases, $testing);
        break;
    case 'family':
    default:
        // New server behaviour: return latest within the client's major.minor family
        $serveVersion = findLatestInFamily($releases, $clientFamily, $testing)
                     ?? findGlobalLatest($releases, $testing);
        break;
}

// ── Log to stderr (visible in php -S output) ─────────────────────────────────
$log = sprintf(
    "[%s] %s  v=%s  family=%s  testing=%s  mode=%s  → %s\n",
    date('H:i:s'),
    $_SERVER['REQUEST_URI'],
    $v,
    $clientFamily,
    $testing ? 'yes' : 'no',
    $mode,
    $serveVersion
);
file_put_contents('php://stderr', $log);

// ── Respond ───────────────────────────────────────────────────────────────────
header('Content-Type: application/json');
echo json_encode([
    'version'   => $serveVersion,
    'date'      => '2025-06-01T12:00:00Z',
    'assets'    => [
        'grav-update' => [
            'name'     => "grav-update-v{$serveVersion}.zip",
            'type'     => 'application/zip',
            'size'     => 3000000,
            'download' => "http://localhost:8043/download/grav-update/{$serveVersion}",
        ],
        'grav-admin' => [
            'name'     => "grav-admin-v{$serveVersion}.zip",
            'type'     => 'application/zip',
            'size'     => 5000000,
            'download' => "http://localhost:8043/download/grav-admin/{$serveVersion}",
        ],
        'grav' => [
            'name'     => "grav-v{$serveVersion}.zip",
            'type'     => 'application/zip',
            'size'     => 4000000,
            'download' => "http://localhost:8043/download/grav/{$serveVersion}",
        ],
    ],
    'url'       => "https://github.com/getgrav/grav/releases/tag/{$serveVersion}",
    'min_php'   => '8.1.0',
    'changelog' => new stdClass(),
], JSON_PRETTY_PRINT);
