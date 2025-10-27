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
    $symfony_server = stripos(getenv('_'), 'symfony') !== false || stripos($_SERVER['SERVER_SOFTWARE'] ?? '', 'symfony') !== false || stripos($_ENV['SERVER_SOFTWARE'] ?? '', 'symfony') !== false;

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

    if ($path === '/___safe-upgrade-status') {
        $statusEndpoint = __DIR__ . '/user/plugins/admin/safe-upgrade-status.php';
        if (!\defined('GRAV_ROOT')) {
            // Minimal bootstrap so the status script has the expected constants.
            require_once __DIR__ . '/system/defines.php';
        }
        header('Content-Type: application/json; charset=utf-8');
        if (is_file($statusEndpoint)) {
            require $statusEndpoint;
        } else {
            http_response_code(404);
            echo json_encode([
                'status' => 'error',
                'message' => 'Safe upgrade status endpoint unavailable.',
            ]);
        }
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

$recoveryFlag = __DIR__ . '/user/data/recovery.flag';
if (PHP_SAPI !== 'cli' && is_file($recoveryFlag)) {
    require __DIR__ . '/system/recovery.php';
    return 0;
}

use Grav\Common\Grav;
use RocketTheme\Toolbox\Event\Event;

// Get the Grav instance
$grav = Grav::instance(['loader' => $loader]);

// Process the page
try {
    $grav->process();
} catch (\Error|\Exception $e) {
    $grav->fireEvent('onFatalException', new Event(['exception' => $e]));

    if (PHP_SAPI !== 'cli' && is_file($recoveryFlag)) {
        require __DIR__ . '/system/recovery.php';
        return 0;
    }

    throw $e;
}
