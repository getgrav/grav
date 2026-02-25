#!/usr/bin/env php
<?php
/**
 * Grav Standalone Upgrade Script
 *
 * Self-contained PHP file to upgrade any Grav 1.7.x installation to 1.8.0.
 * No Grav dependencies required — drop into GRAV_ROOT and run via CLI or browser.
 *
 * Usage (CLI):   php upgrade.php
 * Usage (Web):   Navigate to http://yoursite.com/upgrade.php
 *
 * @package    Grav\Upgrade
 * @copyright  Copyright (c) 2015 - 2025 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

// Configuration
$gravDownloadUrl = 'https://getgrav.org/download/core/grav-update/latest';
$minPhpVersion = '8.3.0';

// Detect environment
$isCli = PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg';
$gravRoot = $isCli ? getcwd() : dirname(__FILE__);

// Output helpers
function out($msg, $isCli) {
    if ($isCli) {
        echo $msg . "\n";
    } else {
        echo htmlspecialchars($msg) . "<br>\n";
        if (ob_get_level()) { ob_flush(); }
        flush();
    }
}

function outError($msg, $isCli) {
    if ($isCli) {
        fwrite(STDERR, "ERROR: " . $msg . "\n");
    } else {
        echo "<strong style='color:red'>ERROR: " . htmlspecialchars($msg) . "</strong><br>\n";
        if (ob_get_level()) { ob_flush(); }
        flush();
    }
}

function outSuccess($msg, $isCli) {
    if ($isCli) {
        echo "\033[32m" . $msg . "\033[0m\n";
    } else {
        echo "<strong style='color:green'>" . htmlspecialchars($msg) . "</strong><br>\n";
        if (ob_get_level()) { ob_flush(); }
        flush();
    }
}

// Start output
if (!$isCli) {
    echo '<!DOCTYPE html><html><head><title>Grav Upgrade</title>';
    echo '<style>body{font-family:monospace;padding:20px;max-width:800px;margin:0 auto;}</style>';
    echo '</head><body>';
    echo '<h1>Grav Standalone Upgrade</h1><pre>';
}

out("Grav Standalone Upgrade Script", $isCli);
out("==============================", $isCli);
out("", $isCli);

// Step 1: Validate environment
out("[1/7] Validating environment...", $isCli);

// Check PHP version
if (version_compare(PHP_VERSION, $minPhpVersion, '<')) {
    outError("PHP {$minPhpVersion}+ required. You are running PHP " . PHP_VERSION, $isCli);
    if (!$isCli) {
        out("If your web server shows a different PHP version, try running this script via CLI:", $isCli);
        out("  php8.3 upgrade.php", $isCli);
    }
    exit(1);
}
out("  PHP " . PHP_VERSION . " — OK", $isCli);

// Verify this is a Grav installation
if (!is_file($gravRoot . '/index.php') || !is_dir($gravRoot . '/system') || !is_dir($gravRoot . '/bin')) {
    outError("This does not appear to be a Grav installation.", $isCli);
    outError("Place this script in your Grav root directory.", $isCli);
    exit(1);
}
out("  Grav root: {$gravRoot} — OK", $isCli);

// Check write permissions
if (!is_writable($gravRoot . '/system')) {
    outError("The system/ directory is not writable.", $isCli);
    exit(1);
}
out("  Write permissions — OK", $isCli);

// Read current version
$currentVersion = 'unknown';
$definesFile = $gravRoot . '/system/defines.php';
if (is_file($definesFile)) {
    $content = file_get_contents($definesFile);
    if (preg_match("/define\('GRAV_VERSION',\s*'([^']+)'\)/", $content, $m)) {
        $currentVersion = $m[1];
    }
}
out("  Current Grav version: {$currentVersion}", $isCli);

// Check if ZipArchive is available
if (!class_exists('ZipArchive')) {
    outError("PHP ZipArchive extension is required but not available.", $isCli);
    exit(1);
}
out("  ZipArchive — OK", $isCli);
out("", $isCli);

// Step 2: Confirm upgrade
if ($isCli) {
    out("[2/7] About to upgrade Grav from {$currentVersion} to latest 1.8.x", $isCli);
    out("  Press Enter to continue or Ctrl+C to abort...", $isCli);
    if (defined('STDIN')) {
        fgets(STDIN);
    }
} else {
    // In browser mode, check for confirmation parameter
    if (!isset($_GET['confirm']) || $_GET['confirm'] !== 'yes') {
        echo "</pre>";
        echo "<p>Ready to upgrade Grav from <strong>{$currentVersion}</strong> to latest 1.8.x</p>";
        echo "<p><a href='?confirm=yes' style='background:#4CAF50;color:white;padding:10px 20px;text-decoration:none;border-radius:4px;'>Confirm Upgrade</a></p>";
        echo "</body></html>";
        exit(0);
    }
    out("[2/7] Upgrade confirmed.", $isCli);
}
out("", $isCli);

// Step 3: Download update package
out("[3/7] Downloading Grav update package...", $isCli);
$tmpDir = $gravRoot . '/tmp';
if (!is_dir($tmpDir)) {
    @mkdir($tmpDir, 0775, true);
}
$zipPath = $tmpDir . '/grav-upgrade-' . date('YmdHis') . '.zip';

$downloaded = false;

// Try curl first
if (function_exists('curl_init')) {
    $ch = curl_init($gravDownloadUrl);
    $fp = fopen($zipPath, 'w');
    if ($fp) {
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        fclose($fp);

        if ($httpCode === 200 && filesize($zipPath) > 0) {
            $downloaded = true;
        } else {
            @unlink($zipPath);
            if ($error) {
                out("  curl failed: {$error}", $isCli);
            }
        }
    }
}

// Fallback to file_get_contents
if (!$downloaded && ini_get('allow_url_fopen')) {
    $context = stream_context_create([
        'http' => [
            'timeout' => 300,
            'follow_location' => true,
        ]
    ]);
    $data = @file_get_contents($gravDownloadUrl, false, $context);
    if ($data !== false && strlen($data) > 0) {
        file_put_contents($zipPath, $data);
        $downloaded = true;
    }
}

if (!$downloaded) {
    outError("Failed to download update package.", $isCli);
    outError("You can manually download it and place it at: {$zipPath}", $isCli);
    exit(1);
}

$sizeMb = round(filesize($zipPath) / 1048576, 1);
out("  Downloaded {$sizeMb}MB to {$zipPath}", $isCli);
out("", $isCli);

// Step 4: Create backup of system/ and vendor/
out("[4/7] Backing up system/ and vendor/...", $isCli);
$backupDir = $gravRoot . '/backup/pre-upgrade-' . date('YmdHis');
@mkdir($backupDir, 0775, true);

function recursiveCopy($src, $dst) {
    if (is_link($src)) {
        $target = readlink($src);
        if ($target !== false) {
            @symlink($target, $dst);
        }
        return;
    }

    if (is_dir($src)) {
        @mkdir($dst, 0775, true);
        $files = scandir($src);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            recursiveCopy($src . '/' . $file, $dst . '/' . $file);
        }
    } else {
        @copy($src, $dst);
    }
}

recursiveCopy($gravRoot . '/system', $backupDir . '/system');
out("  Backed up system/", $isCli);
recursiveCopy($gravRoot . '/vendor', $backupDir . '/vendor');
out("  Backed up vendor/", $isCli);

// Also backup key root files
foreach (['index.php', 'composer.json', 'composer.lock'] as $rootFile) {
    if (is_file($gravRoot . '/' . $rootFile)) {
        @copy($gravRoot . '/' . $rootFile, $backupDir . '/' . $rootFile);
    }
}
out("  Backed up root files (index.php, composer.json, composer.lock)", $isCli);
out("  Backup location: {$backupDir}", $isCli);
out("", $isCli);

// Step 5: Set maintenance mode and clear opcache
out("[5/7] Setting maintenance mode...", $isCli);
@file_put_contents($gravRoot . '/.upgrading', date('Y-m-d H:i:s'));
if (function_exists('opcache_reset')) {
    @opcache_reset();
    out("  OPcache cleared", $isCli);
}
out("  Maintenance mode enabled", $isCli);
out("", $isCli);

// Step 6: Extract update
out("[6/7] Extracting update package...", $isCli);
$zip = new ZipArchive();
$result = $zip->open($zipPath);
if ($result !== true) {
    outError("Failed to open zip file (error code: {$result})", $isCli);
    @unlink($gravRoot . '/.upgrading');
    exit(1);
}

$extractDir = $tmpDir . '/grav-extract-' . uniqid();
@mkdir($extractDir, 0775, true);
$zip->extractTo($extractDir);
$zip->close();

// Find the extracted package root (usually grav-update/ or grav/)
$extractedRoot = null;
$entries = scandir($extractDir);
foreach ($entries as $entry) {
    if ($entry === '.' || $entry === '..') continue;
    if (is_dir($extractDir . '/' . $entry)) {
        $extractedRoot = $extractDir . '/' . $entry;
        break;
    }
}

if (!$extractedRoot || !is_file($extractedRoot . '/system/defines.php')) {
    outError("Invalid update package structure.", $isCli);
    @unlink($gravRoot . '/.upgrading');
    exit(1);
}

// Read target version
$targetVersion = 'unknown';
$targetDefines = file_get_contents($extractedRoot . '/system/defines.php');
if (preg_match("/define\('GRAV_VERSION',\s*'([^']+)'\)/", $targetDefines, $m)) {
    $targetVersion = $m[1];
}
out("  Target version: {$targetVersion}", $isCli);

// Items to skip during upgrade (user data, caches, etc.)
$ignores = ['backup', 'cache', 'images', 'logs', 'tmp', 'user', '.htaccess', 'robots.txt'];

// Copy files from extracted package to Grav root
$sourceEntries = scandir($extractedRoot);
$copied = 0;
foreach ($sourceEntries as $entry) {
    if ($entry === '.' || $entry === '..' || in_array($entry, $ignores)) continue;

    $src = $extractedRoot . '/' . $entry;
    $dst = $gravRoot . '/' . $entry;

    if (is_dir($src)) {
        // Remove old directory and copy new one
        function recursiveDelete($dir) {
            if (!is_dir($dir)) return;
            $files = scandir($dir);
            foreach ($files as $file) {
                if ($file === '.' || $file === '..') continue;
                $path = $dir . '/' . $file;
                if (is_dir($path) && !is_link($path)) {
                    recursiveDelete($path);
                } else {
                    @unlink($path);
                }
            }
            @rmdir($dir);
        }

        if (is_dir($dst) && !is_link($dst)) {
            recursiveDelete($dst);
        }
        recursiveCopy($src, $dst);

        // Set executable permissions on bin files
        if ($entry === 'bin') {
            $binFiles = glob($dst . '/*');
            if ($binFiles) {
                foreach ($binFiles as $binFile) {
                    @chmod($binFile, 0755);
                }
            }
        }
    } else {
        @unlink($dst);
        @copy($src, $dst);
    }
    $copied++;
}
out("  Copied {$copied} items", $isCli);
out("", $isCli);

// Step 7: Cleanup and finalize
out("[7/7] Finalizing...", $isCli);

// Remove maintenance mode
@unlink($gravRoot . '/.upgrading');
out("  Maintenance mode disabled", $isCli);

// Clear opcache
if (function_exists('opcache_reset')) {
    @opcache_reset();
    out("  OPcache cleared", $isCli);
}

// Clear Grav cache
$cacheDir = $gravRoot . '/cache';
if (is_dir($cacheDir)) {
    $cacheDirs = glob($cacheDir . '/*');
    if ($cacheDirs) {
        foreach ($cacheDirs as $dir) {
            if (is_dir($dir) && !is_link($dir) && basename($dir) !== '.gitkeep') {
                recursiveDelete($dir);
            }
        }
    }
    out("  Cache cleared", $isCli);
}

// Clean up temp files
@unlink($zipPath);
if (is_dir($extractDir)) {
    recursiveDelete($extractDir);
}
out("  Temporary files cleaned up", $isCli);

// Clear stat cache
clearstatcache(true);

out("", $isCli);
outSuccess("Upgrade complete! Grav {$currentVersion} -> {$targetVersion}", $isCli);
out("", $isCli);
out("Backup saved to: {$backupDir}", $isCli);
out("", $isCli);
out("IMPORTANT: Delete this upgrade.php file from your Grav root for security.", $isCli);

if (!$isCli) {
    echo "</pre></body></html>";
}
