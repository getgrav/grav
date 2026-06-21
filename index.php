<?php

/**
 * @package    Grav.Core
 *
 * @copyright  Copyright (c) 2015 - 2024 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav;

\define('GRAV_REQUEST_TIME', microtime(true));

\define('GRAV_PHP_MIN', '8.3.0');

if (PHP_SAPI === 'cli-server') {
    // The Symfony local server (`symfony server:start`, also used by `bin/grav server`)
    // handles routing and static files itself, so Grav's own router is not needed. When
    // php-fpm is unavailable Symfony falls back to PHP's built-in server (this cli-server
    // SAPI), where SERVER_SOFTWARE is set by PHP ("PHP x.y.z Development Server") and the
    // `_` env var points at php rather than symfony, so neither is reliable. The
    // SYMFONY_* route variables that the Symfony CLI injects into the worker's environment
    // are the dependable signal in that case.
    $symfony_server = stripos((string) getenv('_'), 'symfony') !== false
        || stripos($_SERVER['SERVER_SOFTWARE'] ?? '', 'symfony') !== false
        || stripos($_ENV['SERVER_SOFTWARE'] ?? '', 'symfony') !== false
        || getenv('SYMFONY_DEFAULT_ROUTE_HOST') !== false
        || isset($_SERVER['SYMFONY_DEFAULT_ROUTE_HOST']);

    if (!isset($_SERVER['PHP_CLI_ROUTER']) && !$symfony_server) {
        die("PHP webserver requires a router to run Grav, please use: <pre>php -S {$_SERVER['SERVER_NAME']}:{$_SERVER['SERVER_PORT']} system/router.php</pre>");
    }
}

if (PHP_SAPI !== 'cli') {
    if (!isset($_SERVER['argv']) && !ini_get('register_argc_argv')) {
        $queryString = $_SERVER['QUERY_STRING'] ?? '';
        $_SERVER['argv'] = $queryString !== '' ? [$queryString] : [];
        $_SERVER['argc'] = $queryString !== '' ? 1 : 0;
    }

    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $path = parse_url($requestUri, PHP_URL_PATH) ?? '/';
    $path = str_replace('\\', '/', $path);

    $scriptDir = str_replace('\\', '/', dirname($scriptName));
    if ($scriptDir && $scriptDir !== '/' && $scriptDir !== '.') {
        if (strpos($path, $scriptDir) === 0) {
            $path = substr($path, strlen($scriptDir));
            $path = $path === '' ? '/' : $path;
        }
    }

    // Fast static asset serving for plugins that bundle SPA apps.
    // Checks for a plugin-asset-map.php file that maps route prefixes to
    // physical directories, serving files directly without booting Grav.
    $assetMapFile = __DIR__ . '/user/config/plugin-asset-map.php';
    if (is_file($assetMapFile)) {
        $assetMap = require $assetMapFile;
        foreach ($assetMap as $routePrefix => $diskPath) {
            if (str_starts_with($path, $routePrefix)) {
                $relPath = substr($path, strlen($routePrefix));
                $filePath = __DIR__ . '/' . ltrim($diskPath, '/') . $relPath;
                $realFile = realpath($filePath);
                $realBase = realpath(__DIR__ . '/' . ltrim($diskPath, '/'));
                if ($realFile && $realBase && str_starts_with($realFile, $realBase) && is_file($realFile)) {
                    $ext = strtolower(pathinfo($realFile, PATHINFO_EXTENSION));
                    $mimeMap = [
                        'js' => 'text/javascript', 'mjs' => 'text/javascript',
                        'css' => 'text/css', 'json' => 'application/json',
                        'svg' => 'image/svg+xml', 'png' => 'image/png',
                        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
                        'webp' => 'image/webp', 'avif' => 'image/avif',
                        'woff2' => 'font/woff2', 'woff' => 'font/woff',
                        'ico' => 'image/x-icon',
                    ];
                    header('Content-Type: ' . ($mimeMap[$ext] ?? 'application/octet-stream'));
                    header('Content-Length: ' . filesize($realFile));
                    header('Cache-Control: ' . (str_contains($relPath, '/immutable/') ? 'public, max-age=31536000, immutable' : 'public, max-age=3600'));
                    readfile($realFile);
                    exit;
                }
            }
        }
    }

}

// Maintenance mode during core upgrade
if (file_exists(__DIR__ . '/.upgrading')) {
    if (time() - filemtime(__DIR__ . '/.upgrading') > 300) {
        @unlink(__DIR__ . '/.upgrading'); // Stale flag (>5 min), remove it
    } else {
        http_response_code(503);
        header('Retry-After: 60');
        echo '<!DOCTYPE html><html><head><title>Upgrading</title></head>';
        echo '<body><h1>Site Upgrading</h1><p>Please try again in a moment.</p></body></html>';
        exit;
    }
}

// Ensure vendor libraries exist
$autoload = __DIR__ . '/vendor/autoload.php';
if (!is_file($autoload)) {
    die('Please run: <i>bin/grav install</i>');
}

// Register the auto-loader.
$loader = require $autoload;

// Set timezone to default, falls back to system if php.ini not set
date_default_timezone_set(@date_default_timezone_get());

// Set internal encoding.
@ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');

use Grav\Common\Grav;
use RocketTheme\Toolbox\Event\Event;

// Get the Grav instance
$grav = Grav::instance(['loader' => $loader]);

// Process the page
try {
    $grav->process();
} catch (\Error|\Exception $e) {
    $grav->fireEvent('onFatalException', new Event(['exception' => $e]));

    throw $e;
}
