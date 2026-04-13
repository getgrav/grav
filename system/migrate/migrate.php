<?php
/**
 * Grav 2.0 Migration Wizard — standalone single-file PHP wizard.
 *
 * This file is shipped inside the Grav 2.0 release package at
 * system/migrate/migrate.php. The grav-plugin-migrate-to-2 kickoff extracts
 * it to a Grav 1.7/1.8 site's webroot, drops a `.migrating` token file, and
 * redirects here. From that point on NO 1.x code is loaded — this file only
 * uses PHP stdlib.
 *
 * Structure: GETs render the HTML shell; POSTs are JSON action endpoints
 * consumed by the Alpine.js front-end embedded at the bottom of this file.
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

define('MIGRATE_WEBROOT', __DIR__);
define('MIGRATE_FLAG', MIGRATE_WEBROOT . DIRECTORY_SEPARATOR . '.migrating');
define('MIGRATE_STATE_VERSION', 1);
define('MIGRATE_PHP_MIN', '8.3.0');

// ---------------------------------------------------------------------------
// State
// ---------------------------------------------------------------------------

/**
 * .migrating file structure (persisted across requests):
 *
 * {
 *   "version": 1,
 *   "token": "hex",
 *   "created": 1713000000,
 *   "source": {grav_version, root, admin_user, trigger},
 *   "stage_dir": "grav-2",
 *   "staged_zip": "tmp/grav-2.0-staged.zip",
 *   "wizard_url": "/migrate.php?token=...",
 *   "current_stage": "auth",       // one of STAGES below
 *   "completed": ["auth","preflight",...],
 *   "auth": { authenticated_user, authenticated_at },
 *   "preflight": { passed, checks: [...] },
 *   "snapshot": { path, size, created_at },
 *   "stage": { stage_dir, extracted_at },
 *   ...
 * }
 */

const STAGES = [
    'auth', 'preflight', 'snapshot', 'stage',
    'import', 'evaluate', 'install', 'test',
    'promote', 'cleanup',
];

function load_state(): array
{
    if (!is_file(MIGRATE_FLAG)) {
        throw new RuntimeException('No active migration. The .migrating file is missing.');
    }
    $raw = @file_get_contents(MIGRATE_FLAG);
    if ($raw === false) {
        throw new RuntimeException('Cannot read .migrating.');
    }
    $data = json_decode($raw, true);
    if (!is_array($data) || empty($data['token'])) {
        throw new RuntimeException('.migrating is malformed.');
    }

    $data += [
        'version' => MIGRATE_STATE_VERSION,
        'current_stage' => 'auth',
        'completed' => [],
        'stage_dir' => 'grav-2',
    ];

    return $data;
}

function save_state(array $state): void
{
    $json = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        throw new RuntimeException('Failed to encode state.');
    }
    $tmp = MIGRATE_FLAG . '.tmp';
    if (file_put_contents($tmp, $json) === false) {
        throw new RuntimeException('Failed to write state tmp file.');
    }
    if (!rename($tmp, MIGRATE_FLAG)) {
        @unlink($tmp);
        throw new RuntimeException('Failed to replace state file.');
    }
    @chmod(MIGRATE_FLAG, 0600);
}

function redact_state(array $state): array
{
    unset($state['token']);
    return $state;
}

function advance_stage(array &$state, string $stage, array $data = []): void
{
    $state[$stage] = $data + [$stage => true];
    if (!in_array($stage, $state['completed'] ?? [], true)) {
        $state['completed'][] = $stage;
    }
    $idx = array_search($stage, STAGES, true);
    $state['current_stage'] = $idx !== false && isset(STAGES[$idx + 1])
        ? STAGES[$idx + 1]
        : 'done';
    save_state($state);
}

// ---------------------------------------------------------------------------
// Auth (against the 1.x user/accounts/*.yaml files)
// ---------------------------------------------------------------------------

function user_accounts_dir(): string
{
    return MIGRATE_WEBROOT . DIRECTORY_SEPARATOR . 'user' . DIRECTORY_SEPARATOR . 'accounts';
}

function parse_minimal_yaml(string $content): array
{
    if (function_exists('yaml_parse')) {
        $parsed = @yaml_parse($content);
        if (is_array($parsed)) {
            return $parsed;
        }
    }
    // Tiny fallback — handles the flat + nested-map subset used by account
    // files. Doesn't support arrays or multi-line strings, which is fine here.
    $lines = preg_split("/\r\n|\n|\r/", $content) ?: [];
    $root = [];
    $stack = [['indent' => -1, 'ref' => &$root]];
    foreach ($lines as $line) {
        $stripped = rtrim($line);
        $t = ltrim($stripped);
        if ($t === '' || $t[0] === '#') {
            continue;
        }
        $indent = strlen($stripped) - strlen($t);
        $indent = strlen(str_replace("\t", '  ', substr($stripped, 0, $indent)));

        while (count($stack) > 1 && $indent <= $stack[count($stack) - 1]['indent']) {
            array_pop($stack);
        }
        if (!preg_match('/^([A-Za-z0-9_.-]+):\s*(.*)$/', $t, $m)) {
            continue;
        }
        $key = $m[1];
        $val = trim($m[2]);

        $parent = &$stack[count($stack) - 1]['ref'];
        if ($val === '' || $val === '~' || strtolower($val) === 'null') {
            $parent[$key] = [];
            $stack[] = ['indent' => $indent, 'ref' => &$parent[$key]];
        } else {
            if ($val === 'true') {
                $val = true;
            } elseif ($val === 'false') {
                $val = false;
            } elseif (preg_match('/^-?\d+$/', $val)) {
                $val = (int)$val;
            } elseif (preg_match('/^"(.*)"$/', $val, $mm) || preg_match("/^'(.*)'$/", $val, $mm)) {
                $val = $mm[1];
            }
            $parent[$key] = $val;
        }
        unset($parent);
    }

    return $root;
}

function load_user_account(string $username): ?array
{
    $path = user_accounts_dir() . DIRECTORY_SEPARATOR . $username . '.yaml';
    if (!is_file($path)) {
        return null;
    }
    $raw = @file_get_contents($path);
    if ($raw === false) {
        return null;
    }
    $data = parse_minimal_yaml($raw);
    $data['_username'] = $username;
    return $data;
}

function account_is_super_admin(array $account): bool
{
    // Grav stores super as access.admin.super: true
    $access = $account['access'] ?? null;
    if (is_array($access) && isset($access['admin']) && is_array($access['admin'])) {
        return !empty($access['admin']['super']);
    }
    return false;
}

function verify_credentials(string $username, string $password): array
{
    $account = load_user_account($username);
    if (!$account) {
        throw new RuntimeException('Unknown user.');
    }
    if (($account['state'] ?? 'enabled') !== 'enabled') {
        throw new RuntimeException('Account is disabled.');
    }
    $hash = (string)($account['hashed_password'] ?? '');
    if ($hash === '' || !password_verify($password, $hash)) {
        throw new RuntimeException('Invalid credentials.');
    }
    if (!account_is_super_admin($account)) {
        throw new RuntimeException('A super-admin account is required to run migration.');
    }
    return $account;
}

// ---------------------------------------------------------------------------
// Session / CSRF
// ---------------------------------------------------------------------------

function start_wizard_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_name('GRAV_MIGRATE');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => dirname($_SERVER['SCRIPT_NAME'] ?? '/') ?: '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        @session_start();
    }
}

function require_token(array $state): void
{
    start_wizard_session();

    $supplied = $_GET['token'] ?? $_POST['token'] ?? $_SERVER['HTTP_X_MIGRATE_TOKEN'] ?? null;
    $sessionToken = $_SESSION['migrate_token'] ?? null;

    if ($supplied && hash_equals($state['token'], (string)$supplied)) {
        $_SESSION['migrate_token'] = $state['token'];
        return;
    }
    if ($sessionToken && hash_equals($state['token'], (string)$sessionToken)) {
        return;
    }

    throw new RuntimeException('Invalid or missing migration token.');
}

function require_authenticated(array $state): void
{
    start_wizard_session();
    if (empty($_SESSION['migrate_user'])) {
        throw new RuntimeException('Authentication required.');
    }
}

// ---------------------------------------------------------------------------
// Utilities
// ---------------------------------------------------------------------------

function fmt_bytes(int $n): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    $v = (float)$n;
    while ($v >= 1024 && $i < count($units) - 1) {
        $v /= 1024;
        $i++;
    }
    return sprintf('%.1f %s', $v, $units[$i]);
}

function disk_free_at(string $path): int
{
    $free = @disk_free_space($path);
    return is_numeric($free) ? (int)$free : -1;
}

function dir_size(string $path): int
{
    if (!is_dir($path)) {
        return 0;
    }
    $total = 0;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
        $path, FilesystemIterator::SKIP_DOTS
    ));
    foreach ($it as $f) {
        if ($f->isFile()) {
            $total += $f->getSize();
        }
    }
    return $total;
}

/**
 * Copy a directory tree. Symlinks are not followed; they're recorded in
 * $skipped for operator decision. Returns ['files'=>int,'skipped'=>string[]].
 */
function fs_copy_tree(string $src, string $dst, array &$skipped = []): array
{
    if (!is_dir($src)) {
        return ['files' => 0, 'skipped' => $skipped];
    }
    if (!is_dir($dst) && !mkdir($dst, 0775, true) && !is_dir($dst)) {
        throw new RuntimeException("Cannot create destination: {$dst}");
    }

    $files = 0;
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($it as $entry) {
        /** @var SplFileInfo $entry */
        $rel = substr($entry->getPathname(), strlen($src) + 1);
        $target = $dst . DIRECTORY_SEPARATOR . $rel;

        if ($entry->isLink()) {
            $skipped[] = $entry->getPathname();
            continue;
        }
        if ($entry->isDir()) {
            if (!is_dir($target) && !@mkdir($target, 0775, true) && !is_dir($target)) {
                throw new RuntimeException("Cannot mkdir {$target}");
            }
        } elseif ($entry->isFile()) {
            $parent = dirname($target);
            if (!is_dir($parent) && !@mkdir($parent, 0775, true) && !is_dir($parent)) {
                throw new RuntimeException("Cannot mkdir {$parent}");
            }
            if (!@copy($entry->getPathname(), $target)) {
                throw new RuntimeException("Cannot copy {$entry->getPathname()} → {$target}");
            }
            $files++;
        }
    }
    return ['files' => $files, 'skipped' => $skipped];
}

function fs_rmtree(string $path): void
{
    if (!file_exists($path) && !is_link($path)) {
        return;
    }
    if (is_link($path) || !is_dir($path)) {
        @unlink($path);
        return;
    }
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $f) {
        if ($f->isLink() || $f->isFile()) {
            @unlink($f->getPathname());
        } elseif ($f->isDir()) {
            @rmdir($f->getPathname());
        }
    }
    @rmdir($path);
}

/**
 * Read a plugin/theme blueprint YAML and return its parsed map.
 */
function read_blueprint(string $path): ?array
{
    $f = $path . DIRECTORY_SEPARATOR . 'blueprints.yaml';
    if (!is_file($f)) {
        return null;
    }
    $raw = @file_get_contents($f);
    if ($raw === false) {
        return null;
    }
    return parse_minimal_yaml($raw);
}

/**
 * Mirror of Grav\Common\GPM\Local\Package::resolveCompatibility() for use
 * inside this standalone wizard. Reads blueprint + dep inference.
 */
function resolve_compatibility(?array $blueprint): array
{
    if (!$blueprint) {
        return ['grav' => ['1.7'], 'api' => [], 'source' => 'default'];
    }

    $compat = $blueprint['compatibility'] ?? null;
    if (is_array($compat) && isset($compat['grav']) && is_array($compat['grav'])) {
        return [
            'grav' => array_map('strval', $compat['grav']),
            'api' => isset($compat['api']) && is_array($compat['api']) ? array_map('strval', $compat['api']) : [],
            'source' => 'blueprint',
        ];
    }

    $deps = $blueprint['dependencies'] ?? [];
    if (is_array($deps)) {
        foreach ($deps as $dep) {
            if (!is_array($dep) || ($dep['name'] ?? '') !== 'grav') {
                continue;
            }
            $v = (string)($dep['version'] ?? '');
            if (!preg_match('/(\d+\.\d+(?:\.\d+)?)/', $v, $m)) {
                continue;
            }
            if (version_compare($m[1], '2.0', '>=')) {
                return ['grav' => ['2.0'], 'api' => [], 'source' => 'inferred'];
            }
            if (version_compare($m[1], '1.8', '>=')) {
                return ['grav' => ['1.8'], 'api' => [], 'source' => 'inferred'];
            }
            return ['grav' => ['1.7'], 'api' => [], 'source' => 'inferred'];
        }
    }

    return ['grav' => ['1.7'], 'api' => [], 'source' => 'default'];
}

function fetch_curated_compat(string $slug): ?array
{
    $url = 'https://getgrav.org/gpm/compatibility/v1/' . urlencode($slug);
    $ctx = stream_context_create([
        'http' => ['timeout' => 3, 'ignore_errors' => true, 'user_agent' => 'grav-migrate/1.0'],
    ]);
    $body = @file_get_contents($url, false, $ctx);
    if ($body === false || $body === '') {
        return null;
    }
    $data = json_decode($body, true);
    return is_array($data) ? $data : null;
}

function classify_plugin(array $compat, ?array $curated): string
{
    $grav = $compat['grav'] ?? [];
    $curatedGrav = is_array($curated['grav'] ?? null) ? $curated['grav'] : [];

    if (in_array('2.0', $grav, true) || in_array('2.0', $curatedGrav, true)) {
        return 'works';
    }
    if ($curated && isset($curated['grav']) && !in_array('2.0', $curatedGrav, true)) {
        return 'incompatible';
    }
    if (in_array('1.7', $grav, true) || in_array('1.8', $grav, true)) {
        return 'needs_update';
    }
    return 'unknown';
}

function run_subprocess(array $cmd, ?string $cwd = null): array
{
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $proc = proc_open($cmd, $descriptors, $pipes, $cwd);
    if (!is_resource($proc)) {
        throw new RuntimeException('Failed to start subprocess: ' . implode(' ', $cmd));
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]) ?: '';
    $stderr = stream_get_contents($pipes[2]) ?: '';
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($proc);
    return ['code' => $code, 'stdout' => $stdout, 'stderr' => $stderr];
}

function php_binary(): string
{
    if (defined('PHP_BINARY') && PHP_BINARY) {
        return PHP_BINARY;
    }
    return 'php';
}

// ---------------------------------------------------------------------------
// Stage handlers
// ---------------------------------------------------------------------------

function action_state(array $state): array
{
    return ['state' => redact_state($state)];
}

function action_auth(array $state): array
{
    if (in_array('auth', $state['completed'] ?? [], true)) {
        return ['already' => true];
    }
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    if ($username === '' || $password === '') {
        throw new RuntimeException('Username and password required.');
    }

    $account = verify_credentials($username, $password);

    start_wizard_session();
    $_SESSION['migrate_user'] = $account['_username'];

    advance_stage($state, 'auth', [
        'authenticated_user' => $account['_username'],
        'authenticated_at' => time(),
    ]);

    return ['authenticated_user' => $account['_username']];
}

function action_preflight(array $state): array
{
    require_authenticated($state);

    $checks = [];

    $checks[] = [
        'name' => 'PHP version',
        'pass' => version_compare(PHP_VERSION, MIGRATE_PHP_MIN, '>='),
        'detail' => 'Running ' . PHP_VERSION . ', need ≥ ' . MIGRATE_PHP_MIN,
    ];

    foreach (['zip', 'json', 'mbstring', 'fileinfo'] as $ext) {
        $checks[] = [
            'name' => "PHP ext: {$ext}",
            'pass' => extension_loaded($ext),
            'detail' => extension_loaded($ext) ? 'loaded' : 'missing',
        ];
    }

    $writableRoot = is_writable(MIGRATE_WEBROOT);
    $checks[] = [
        'name' => 'Webroot writable',
        'pass' => $writableRoot,
        'detail' => MIGRATE_WEBROOT,
    ];

    $userDir = MIGRATE_WEBROOT . DIRECTORY_SEPARATOR . 'user';
    $userSize = dir_size($userDir);
    $free = disk_free_at(MIGRATE_WEBROOT);
    $needed = max($userSize * 2, 200 * 1024 * 1024); // 2x user/ or 200MB min
    $checks[] = [
        'name' => 'Disk space',
        'pass' => $free < 0 ? true : $free >= $needed,
        'detail' => sprintf(
            'user/ ≈ %s, free ≈ %s, need ≈ %s',
            fmt_bytes($userSize),
            $free < 0 ? 'unknown' : fmt_bytes($free),
            fmt_bytes($needed)
        ),
    ];

    $stageDir = MIGRATE_WEBROOT . DIRECTORY_SEPARATOR . ($state['stage_dir'] ?? 'grav-2');
    $checks[] = [
        'name' => 'Stage dir available',
        'pass' => !is_dir($stageDir),
        'detail' => is_dir($stageDir) ? "{$stageDir} already exists" : 'ok',
    ];

    $zipPath = MIGRATE_WEBROOT . DIRECTORY_SEPARATOR . ($state['staged_zip'] ?? 'tmp/grav-2.0-staged.zip');
    $checks[] = [
        'name' => 'Staged 2.0 zip present',
        'pass' => is_file($zipPath),
        'detail' => is_file($zipPath) ? fmt_bytes((int)filesize($zipPath)) : "missing: {$zipPath}",
    ];

    $passed = !in_array(false, array_column($checks, 'pass'), true);

    if ($passed) {
        advance_stage($state, 'preflight', ['passed' => true, 'checks' => $checks]);
    }

    return ['passed' => $passed, 'checks' => $checks];
}

function action_snapshot(array $state): array
{
    require_authenticated($state);

    $backupDir = MIGRATE_WEBROOT . DIRECTORY_SEPARATOR . 'backup';
    if (!is_dir($backupDir) && !mkdir($backupDir, 0775, true) && !is_dir($backupDir)) {
        throw new RuntimeException('Cannot create backup/ directory.');
    }

    $zipName = 'pre-migration-' . date('YmdHis') . '.zip';
    $zipPath = $backupDir . DIRECTORY_SEPARATOR . $zipName;

    $userDir = MIGRATE_WEBROOT . DIRECTORY_SEPARATOR . 'user';
    if (!is_dir($userDir)) {
        throw new RuntimeException('user/ directory not found; nothing to back up.');
    }

    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException("Cannot open backup zip for write: {$zipPath}");
    }

    $base = MIGRATE_WEBROOT . DIRECTORY_SEPARATOR;
    $fileCount = 0;
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($userDir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($it as $f) {
        $abs = $f->getPathname();
        $rel = ltrim(str_replace($base, '', $abs), DIRECTORY_SEPARATOR);
        if ($f->isDir()) {
            $zip->addEmptyDir($rel);
        } elseif ($f->isFile()) {
            $zip->addFile($abs, $rel);
            $fileCount++;
        }
    }
    $zip->close();

    $size = (int)@filesize($zipPath);
    advance_stage($state, 'snapshot', [
        'path' => 'backup/' . $zipName,
        'size' => $size,
        'files' => $fileCount,
        'created_at' => time(),
    ]);

    return ['path' => 'backup/' . $zipName, 'size' => $size, 'files' => $fileCount];
}

function action_stage(array $state): array
{
    require_authenticated($state);

    $zipRel = $state['staged_zip'] ?? 'tmp/grav-2.0-staged.zip';
    $zipPath = MIGRATE_WEBROOT . DIRECTORY_SEPARATOR . $zipRel;
    if (!is_file($zipPath)) {
        throw new RuntimeException("Staged zip missing: {$zipRel}");
    }

    $stageDir = MIGRATE_WEBROOT . DIRECTORY_SEPARATOR . ($state['stage_dir'] ?? 'grav-2');
    if (is_dir($stageDir)) {
        throw new RuntimeException("Stage directory already exists: {$stageDir}");
    }
    if (!mkdir($stageDir, 0775, true)) {
        throw new RuntimeException("Cannot create stage directory: {$stageDir}");
    }

    $zip = new ZipArchive();
    $opened = $zip->open($zipPath);
    if ($opened !== true) {
        throw new RuntimeException("Cannot open staged zip (code {$opened}): {$zipPath}");
    }

    // Detect if zip contents are wrapped in a single top-level directory.
    $prefix = '';
    if ($zip->numFiles > 0) {
        $first = (string)$zip->getNameIndex(0);
        if ($first !== '' && strpos($first, '/') !== false) {
            $candidate = substr($first, 0, strpos($first, '/') + 1);
            $wrapped = true;
            for ($i = 1, $n = min($zip->numFiles, 32); $i < $n; $i++) {
                $name = (string)$zip->getNameIndex($i);
                if ($name !== '' && strpos($name, $candidate) !== 0) {
                    $wrapped = false;
                    break;
                }
            }
            if ($wrapped) {
                $prefix = $candidate;
            }
        }
    }

    $extracted = 0;
    for ($i = 0, $n = $zip->numFiles; $i < $n; $i++) {
        $name = (string)$zip->getNameIndex($i);
        if ($name === '') {
            continue;
        }
        $rel = $prefix !== '' && strpos($name, $prefix) === 0
            ? substr($name, strlen($prefix))
            : $name;
        if ($rel === '' || $rel === false) {
            continue;
        }
        $dest = $stageDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        if (substr($name, -1) === '/') {
            if (!is_dir($dest) && !@mkdir($dest, 0775, true)) {
                throw new RuntimeException("Cannot create dir {$dest}");
            }
        } else {
            $parent = dirname($dest);
            if (!is_dir($parent) && !@mkdir($parent, 0775, true)) {
                throw new RuntimeException("Cannot create parent {$parent}");
            }
            $stream = $zip->getStream($name);
            if (!$stream) {
                throw new RuntimeException("Cannot stream entry {$name}");
            }
            $out = fopen($dest, 'wb');
            if (!$out) {
                fclose($stream);
                throw new RuntimeException("Cannot open {$dest} for write");
            }
            while (!feof($stream)) {
                $buf = fread($stream, 1 << 16);
                if ($buf === false) {
                    break;
                }
                fwrite($out, $buf);
            }
            fclose($stream);
            fclose($out);
            $extracted++;
        }
    }
    $zip->close();

    advance_stage($state, 'stage', [
        'stage_dir' => $state['stage_dir'] ?? 'grav-2',
        'extracted_files' => $extracted,
        'extracted_at' => time(),
    ]);

    return ['stage_dir' => $state['stage_dir'] ?? 'grav-2', 'extracted_files' => $extracted];
}

/**
 * Copy user-side content (pages, data, accounts, config, themes, media) from
 * the source site into the staged grav-2/ tree. Plugins are intentionally
 * handled later in evaluate/install stages.
 */
function action_import(array $state): array
{
    require_authenticated($state);

    $stageDir = MIGRATE_WEBROOT . DIRECTORY_SEPARATOR . ($state['stage_dir'] ?? 'grav-2');
    if (!is_dir($stageDir)) {
        throw new RuntimeException('Stage directory missing — run the stage step first.');
    }

    $srcUser = MIGRATE_WEBROOT . DIRECTORY_SEPARATOR . 'user';
    $dstUser = $stageDir . DIRECTORY_SEPARATOR . 'user';
    if (!is_dir($srcUser)) {
        throw new RuntimeException('Source user/ directory missing.');
    }

    $subdirs = ['pages', 'data', 'accounts', 'config', 'themes', 'media', 'backups'];
    $summary = [];
    $skipped = [];
    foreach ($subdirs as $sub) {
        $src = $srcUser . DIRECTORY_SEPARATOR . $sub;
        $dst = $dstUser . DIRECTORY_SEPARATOR . $sub;
        if (!is_dir($src)) {
            continue;
        }
        $result = fs_copy_tree($src, $dst, $skipped);
        $summary[$sub] = $result['files'];
    }

    advance_stage($state, 'import', [
        'copied' => $summary,
        'skipped_symlinks' => $skipped,
        'imported_at' => time(),
    ]);

    return ['copied' => $summary, 'skipped_symlinks' => $skipped];
}

/**
 * Walk source user/plugins/*, classify each by compatibility, and store the
 * inventory in state. Does NOT apply anything; decisions happen in install().
 */
function action_evaluate(array $state): array
{
    require_authenticated($state);

    $srcPlugins = MIGRATE_WEBROOT . DIRECTORY_SEPARATOR . 'user' . DIRECTORY_SEPARATOR . 'plugins';
    if (!is_dir($srcPlugins)) {
        advance_stage($state, 'evaluate', ['plugins' => []]);
        return ['plugins' => []];
    }

    $plugins = [];
    foreach (new DirectoryIterator($srcPlugins) as $d) {
        if ($d->isDot() || !$d->isDir()) {
            continue;
        }
        $slug = $d->getFilename();
        $path = $d->getPathname();

        $bp = read_blueprint($path);
        $compat = resolve_compatibility($bp);
        $curated = fetch_curated_compat($slug);
        $class = classify_plugin($compat, $curated);

        $default = match ($class) {
            'works' => 'import',
            'needs_update' => 'reinstall',
            'incompatible' => 'skip',
            default => 'skip',
        };

        $plugins[$slug] = [
            'slug' => $slug,
            'version' => $bp['version'] ?? null,
            'symlink' => is_link($path),
            'compatibility' => $compat,
            'curated' => $curated,
            'classification' => $class,
            'default_action' => $default,
        ];
    }

    ksort($plugins);

    advance_stage($state, 'evaluate', [
        'plugins' => $plugins,
        'evaluated_at' => time(),
    ]);

    return ['plugins' => $plugins];
}

/**
 * Apply per-plugin decisions (import/reinstall/skip) from the UI into the
 * staged tree. Decisions arrive as plugins[slug] = action in POST.
 */
function action_install(array $state): array
{
    require_authenticated($state);

    $decisions = $_POST['plugins'] ?? null;
    if (!is_array($decisions)) {
        throw new RuntimeException('No plugin decisions submitted.');
    }

    $known = $state['evaluate']['plugins'] ?? [];
    if (!$known) {
        throw new RuntimeException('Evaluate stage has no plugin inventory.');
    }

    $stageDir = MIGRATE_WEBROOT . DIRECTORY_SEPARATOR . ($state['stage_dir'] ?? 'grav-2');
    $srcPlugins = MIGRATE_WEBROOT . DIRECTORY_SEPARATOR . 'user' . DIRECTORY_SEPARATOR . 'plugins';
    $dstPlugins = $stageDir . DIRECTORY_SEPARATOR . 'user' . DIRECTORY_SEPARATOR . 'plugins';
    if (!is_dir($dstPlugins) && !mkdir($dstPlugins, 0775, true) && !is_dir($dstPlugins)) {
        throw new RuntimeException("Cannot create {$dstPlugins}");
    }

    $gpmBin = $stageDir . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'gpm';
    $results = [];
    $skipped = [];

    foreach ($known as $slug => $info) {
        $choice = (string)($decisions[$slug] ?? $info['default_action']);
        if (!in_array($choice, ['import', 'reinstall', 'skip'], true)) {
            $choice = 'skip';
        }

        if ($choice === 'skip') {
            $results[$slug] = ['action' => 'skip'];
            continue;
        }

        if ($choice === 'import') {
            if ($info['symlink']) {
                $skipped[] = $slug;
                $results[$slug] = ['action' => 'import', 'skipped' => 'symlink'];
                continue;
            }
            $src = $srcPlugins . DIRECTORY_SEPARATOR . $slug;
            $dst = $dstPlugins . DIRECTORY_SEPARATOR . $slug;
            if (is_dir($dst)) {
                fs_rmtree($dst);
            }
            $dummy = [];
            $stat = fs_copy_tree($src, $dst, $dummy);
            $results[$slug] = ['action' => 'import', 'files' => $stat['files']];
            continue;
        }

        // reinstall via the staged 2.0 gpm
        if (!is_file($gpmBin)) {
            $results[$slug] = ['action' => 'reinstall', 'error' => 'staged bin/gpm not found'];
            continue;
        }
        $proc = run_subprocess([php_binary(), $gpmBin, 'install', $slug, '--yes'], $stageDir);
        $results[$slug] = [
            'action' => 'reinstall',
            'code' => $proc['code'],
            'stderr' => trim((string)$proc['stderr']),
        ];
    }

    advance_stage($state, 'install', [
        'results' => $results,
        'skipped_symlinks' => $skipped,
        'installed_at' => time(),
    ]);

    return ['results' => $results, 'skipped_symlinks' => $skipped];
}

/**
 * Minimal health check on the staged Grav 2.0 site: run `bin/grav list` to
 * confirm the install bootstraps without fatal errors.
 */
function action_test(array $state): array
{
    require_authenticated($state);

    $stageDir = MIGRATE_WEBROOT . DIRECTORY_SEPARATOR . ($state['stage_dir'] ?? 'grav-2');
    $gravBin = $stageDir . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'grav';
    if (!is_file($gravBin)) {
        throw new RuntimeException("Staged grav CLI missing: {$gravBin}");
    }

    $proc = run_subprocess([php_binary(), $gravBin, 'list'], $stageDir);
    $ok = $proc['code'] === 0;

    advance_stage($state, 'test', [
        'ok' => $ok,
        'code' => $proc['code'],
        'stdout_excerpt' => substr((string)$proc['stdout'], 0, 2000),
        'stderr_excerpt' => substr((string)$proc['stderr'], 0, 2000),
        'tested_at' => time(),
    ]);

    return [
        'ok' => $ok,
        'code' => $proc['code'],
        'stdout' => substr((string)$proc['stdout'], 0, 4000),
        'stderr' => substr((string)$proc['stderr'], 0, 4000),
    ];
}

/**
 * Swap: move current webroot contents into an archive directory, then move
 * the staged grav-2/ contents up to webroot. Preserves migrate.php, the
 * .migrating flag, the backup/ directory, and tmp/.
 */
function action_promote(array $state): array
{
    require_authenticated($state);

    $confirm = (string)($_POST['confirm'] ?? '');
    if ($confirm !== 'PROMOTE') {
        throw new RuntimeException('Type PROMOTE to confirm.');
    }

    $stageDir = ($state['stage_dir'] ?? 'grav-2');
    $stagePath = MIGRATE_WEBROOT . DIRECTORY_SEPARATOR . $stageDir;
    if (!is_dir($stagePath)) {
        throw new RuntimeException("Stage directory missing: {$stagePath}");
    }

    $archiveName = 'grav-1x-archive-' . date('YmdHis');
    $archivePath = MIGRATE_WEBROOT . DIRECTORY_SEPARATOR . $archiveName;
    if (!mkdir($archivePath, 0775)) {
        throw new RuntimeException("Cannot create archive dir: {$archivePath}");
    }

    // Entries preserved at webroot and NOT archived.
    $preserve = [
        'migrate.php', '.migrating', $stageDir, $archiveName,
        'backup', 'tmp',
    ];
    // Entries from the 1.x site we should archive. Walk top level only.
    $movedToArchive = [];
    foreach (new DirectoryIterator(MIGRATE_WEBROOT) as $e) {
        if ($e->isDot()) {
            continue;
        }
        $name = $e->getFilename();
        if (in_array($name, $preserve, true)) {
            continue;
        }
        // Safety: don't archive other grav-1x-archive-* from previous runs.
        if (strpos($name, 'grav-1x-archive-') === 0) {
            continue;
        }
        $src = $e->getPathname();
        $dst = $archivePath . DIRECTORY_SEPARATOR . $name;
        if (!@rename($src, $dst)) {
            throw new RuntimeException("Failed to archive {$name}");
        }
        $movedToArchive[] = $name;
    }

    // Move staged contents up to webroot.
    $promoted = [];
    foreach (new DirectoryIterator($stagePath) as $e) {
        if ($e->isDot()) {
            continue;
        }
        $name = $e->getFilename();
        $src = $e->getPathname();
        $dst = MIGRATE_WEBROOT . DIRECTORY_SEPARATOR . $name;

        // If 2.0 ships a backup/ or tmp/, merge rather than clobber.
        if (is_dir($dst) && in_array($name, ['backup', 'tmp'], true)) {
            continue;
        }
        if (file_exists($dst)) {
            // Shouldn't happen for non-preserved names since we archived them,
            // but be defensive: move into archive instead of overwriting.
            @rename($dst, $archivePath . DIRECTORY_SEPARATOR . $name . '.conflict');
        }
        if (!@rename($src, $dst)) {
            throw new RuntimeException("Failed to promote {$name} → webroot");
        }
        $promoted[] = $name;
    }

    // Clean up the now-empty stage dir.
    @rmdir($stagePath);

    // Also move the staged zip into the archive for tidiness.
    $stagedZip = MIGRATE_WEBROOT . DIRECTORY_SEPARATOR . ($state['staged_zip'] ?? 'tmp/grav-2.0-staged.zip');
    if (is_file($stagedZip)) {
        @rename($stagedZip, $archivePath . DIRECTORY_SEPARATOR . basename($stagedZip));
    }

    advance_stage($state, 'promote', [
        'archive' => $archiveName,
        'archived' => $movedToArchive,
        'promoted' => $promoted,
        'promoted_at' => time(),
    ]);

    return [
        'archive' => $archiveName,
        'archived_count' => count($movedToArchive),
        'promoted_count' => count($promoted),
    ];
}

/**
 * Remove migrate.php and .migrating. Archive is left alone — operator deletes
 * when satisfied.
 */
function action_cleanup(array $state): array
{
    require_authenticated($state);

    $confirm = (string)($_POST['confirm'] ?? '');
    if ($confirm !== 'REMOVE') {
        throw new RuntimeException('Type REMOVE to confirm.');
    }

    advance_stage($state, 'cleanup', ['cleaned_at' => time()]);

    // After advance_stage persists, actually nuke our own surfaces.
    $self = __FILE__;
    $flag = MIGRATE_FLAG;

    @unlink($flag);
    // Leave migrate.php deletion to a shutdown-time unlink so we finish the
    // response cleanly.
    register_shutdown_function(static function () use ($self) {
        @unlink($self);
    });

    return ['removed' => ['.migrating', 'migrate.php (pending shutdown)']];
}

// ---------------------------------------------------------------------------
// Request dispatch
// ---------------------------------------------------------------------------

function send_json($payload, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function handle_request(): void
{
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $action = $_GET['action'] ?? $_POST['action'] ?? null;

    try {
        $state = load_state();
    } catch (Throwable $e) {
        if ($method === 'POST' || $action) {
            send_json(['ok' => false, 'error' => $e->getMessage()], 400);
        }
        render_error($e->getMessage());
        return;
    }

    try {
        require_token($state);
    } catch (Throwable $e) {
        if ($method === 'POST' || $action) {
            send_json(['ok' => false, 'error' => $e->getMessage()], 403);
        }
        render_error($e->getMessage());
        return;
    }

    if ($method !== 'POST' && $action !== 'state') {
        render_shell($state);
        return;
    }

    try {
        switch ($action) {
            case 'state':     $res = action_state($state); break;
            case 'auth':      $res = action_auth($state); break;
            case 'preflight': $res = action_preflight($state); break;
            case 'snapshot':  $res = action_snapshot($state); break;
            case 'stage':     $res = action_stage($state); break;
            case 'import':    $res = action_import($state); break;
            case 'evaluate':  $res = action_evaluate($state); break;
            case 'install':   $res = action_install($state); break;
            case 'test':      $res = action_test($state); break;
            case 'promote':   $res = action_promote($state); break;
            case 'cleanup':   $res = action_cleanup($state); break;
            default:
                throw new RuntimeException("Unknown action: " . (string)$action);
        }
    } catch (Throwable $e) {
        send_json(['ok' => false, 'error' => $e->getMessage()], 400);
    }

    // Re-load so the response always reflects persisted state.
    $fresh = load_state();
    send_json(['ok' => true, 'result' => $res, 'state' => redact_state($fresh)]);
}

function render_error(string $message): void
{
    http_response_code(400);
    header('Content-Type: text/html; charset=utf-8');
    $msg = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    ?><!doctype html>
<html><head><meta charset="utf-8"><title>Migration wizard error</title>
<style>body{font:14px system-ui;margin:4em auto;max-width:40em;color:#111}code{background:#eee;padding:.1em .3em;border-radius:3px}</style>
</head><body>
<h1>Migration wizard cannot start</h1>
<p><?php echo $msg; ?></p>
<p>If you expected this wizard to run, check that <code>.migrating</code> exists at the webroot and that you reached this page through the redirect from the <code>migrate-to-2</code> plugin.</p>
</body></html><?php
}

function render_shell(array $state): void
{
    header('Content-Type: text/html; charset=utf-8');
    $stateJson = json_encode(redact_state($state), JSON_UNESCAPED_SLASHES);
    ?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Grav 2.0 Migration Wizard</title>
<script src="https://cdn.tailwindcss.com"></script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<style>
  :root { color-scheme: light dark; }
  body { font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
  .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }
  .card { @apply rounded-xl border border-zinc-200 bg-white/80 shadow-sm p-6; }
</style>
</head>
<body class="bg-zinc-50 text-zinc-900 min-h-screen">

<div class="max-w-4xl mx-auto px-6 py-10" x-data="wizard()" x-init="init()">

  <header class="mb-10">
    <div class="flex items-center gap-3">
      <div class="w-9 h-9 rounded-lg bg-gradient-to-br from-indigo-500 to-fuchsia-500"></div>
      <div>
        <h1 class="text-2xl font-semibold tracking-tight">Grav 2.0 Migration Wizard</h1>
        <p class="text-sm text-zinc-500">Running standalone — your Grav 1.x install is not loaded.</p>
      </div>
    </div>
  </header>

  <!-- Stage rail -->
  <ol class="flex flex-wrap gap-2 mb-8 text-xs">
    <template x-for="s in stages" :key="s">
      <li class="px-2.5 py-1 rounded-full border"
          :class="{
            'bg-emerald-50 border-emerald-200 text-emerald-700': done.includes(s),
            'bg-indigo-50 border-indigo-200 text-indigo-700 font-medium': current === s,
            'bg-white border-zinc-200 text-zinc-500': !done.includes(s) && current !== s
          }"
          x-text="s"></li>
    </template>
  </ol>

  <!-- Global error banner -->
  <div x-show="error" x-transition class="mb-6 rounded-lg border border-red-300 bg-red-50 text-red-800 p-4 text-sm">
    <strong class="block font-semibold mb-1">Something went wrong</strong>
    <span x-text="error" class="mono"></span>
  </div>

  <!-- AUTH -->
  <section x-show="current === 'auth'" class="card">
    <h2 class="text-lg font-semibold mb-1">Authenticate</h2>
    <p class="text-sm text-zinc-500 mb-6">Sign in with a super-admin account from your Grav 1.x site.</p>
    <form @submit.prevent="run('auth', { username: auth.username, password: auth.password })" class="space-y-4">
      <label class="block">
        <span class="text-sm font-medium">Username</span>
        <input class="mt-1 block w-full rounded-md border-zinc-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" x-model="auth.username" autocomplete="username" required>
      </label>
      <label class="block">
        <span class="text-sm font-medium">Password</span>
        <input type="password" class="mt-1 block w-full rounded-md border-zinc-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" x-model="auth.password" autocomplete="current-password" required>
      </label>
      <button type="submit" class="inline-flex items-center gap-2 rounded-md bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 text-sm font-medium disabled:opacity-50" :disabled="busy">
        <span x-show="!busy">Sign in →</span>
        <span x-show="busy">Verifying…</span>
      </button>
    </form>
  </section>

  <!-- PREFLIGHT -->
  <section x-show="current === 'preflight'" class="card">
    <h2 class="text-lg font-semibold mb-1">Pre-flight checks</h2>
    <p class="text-sm text-zinc-500 mb-6">Verify the environment can host Grav 2.0.</p>
    <template x-if="preflight.checks.length === 0">
      <button @click="run('preflight')" class="rounded-md bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 text-sm font-medium" :disabled="busy">Run checks</button>
    </template>
    <ul x-show="preflight.checks.length > 0" class="divide-y divide-zinc-100 border border-zinc-200 rounded-md overflow-hidden">
      <template x-for="c in preflight.checks" :key="c.name">
        <li class="flex items-start gap-3 px-4 py-3">
          <span class="mt-0.5 w-5 h-5 rounded-full flex items-center justify-center text-xs font-bold"
                :class="c.pass ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'"
                x-text="c.pass ? '✓' : '✗'"></span>
          <div class="flex-1">
            <div class="text-sm font-medium" x-text="c.name"></div>
            <div class="text-xs text-zinc-500 mono" x-text="c.detail"></div>
          </div>
        </li>
      </template>
    </ul>
    <div x-show="preflight.checks.length > 0 && !preflight.passed" class="mt-4 text-sm text-red-600">Resolve the failing checks and re-run.</div>
    <div class="mt-6 flex gap-2">
      <button x-show="preflight.checks.length > 0 && !preflight.passed" @click="preflight.checks = []; run('preflight')" class="rounded-md border border-zinc-300 px-4 py-2 text-sm" :disabled="busy">Re-run checks</button>
    </div>
  </section>

  <!-- SNAPSHOT -->
  <section x-show="current === 'snapshot'" class="card">
    <h2 class="text-lg font-semibold mb-1">Snapshot</h2>
    <p class="text-sm text-zinc-500 mb-6">Zip up <span class="mono">user/</span> into <span class="mono">backup/</span> so you can roll back if needed.</p>
    <button @click="run('snapshot')" class="rounded-md bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 text-sm font-medium" :disabled="busy">
      <span x-show="!busy">Create snapshot</span>
      <span x-show="busy">Zipping user/ …</span>
    </button>
  </section>

  <!-- STAGE -->
  <section x-show="current === 'stage'" class="card">
    <h2 class="text-lg font-semibold mb-1">Stage Grav 2.0</h2>
    <p class="text-sm text-zinc-500 mb-6">Extract the Grav 2.0 release into <span class="mono" x-text="'/' + (state.stage_dir || 'grav-2') + '/'"></span>.</p>
    <button @click="run('stage')" class="rounded-md bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 text-sm font-medium" :disabled="busy">
      <span x-show="!busy">Extract</span>
      <span x-show="busy">Extracting…</span>
    </button>
  </section>

  <!-- IMPORT -->
  <section x-show="current === 'import'" class="card">
    <h2 class="text-lg font-semibold mb-1">Import content</h2>
    <p class="text-sm text-zinc-500 mb-6">Copy <span class="mono">pages</span>, <span class="mono">data</span>, <span class="mono">accounts</span>, <span class="mono">config</span>, <span class="mono">themes</span>, and <span class="mono">media</span> from your 1.x site into the staged Grav 2.0.</p>
    <button @click="run('import')" class="rounded-md bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 text-sm font-medium" :disabled="busy">
      <span x-show="!busy">Copy content</span>
      <span x-show="busy">Copying…</span>
    </button>
    <div x-show="state.import" class="mt-6 space-y-2 text-sm">
      <div class="font-medium">Copied:</div>
      <ul class="mono text-xs space-y-1">
        <template x-for="[k,v] in Object.entries(state.import?.copied || {})" :key="k">
          <li><span x-text="k"></span>: <span x-text="v"></span> files</li>
        </template>
      </ul>
      <template x-if="(state.import?.skipped_symlinks || []).length">
        <div class="text-amber-700 text-xs">Symlinks skipped — copy or recreate manually: <span class="mono" x-text="state.import.skipped_symlinks.join(', ')"></span></div>
      </template>
    </div>
  </section>

  <!-- EVALUATE -->
  <section x-show="current === 'evaluate'" class="card">
    <h2 class="text-lg font-semibold mb-1">Evaluate plugins</h2>
    <p class="text-sm text-zinc-500 mb-6">Classify each plugin by its <span class="mono">compatibility.grav</span> flag, dependency inference, and the curated getgrav.org list.</p>
    <template x-if="!state.evaluate">
      <button @click="run('evaluate')" class="rounded-md bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 text-sm font-medium" :disabled="busy">
        <span x-show="!busy">Scan plugins</span>
        <span x-show="busy">Scanning…</span>
      </button>
    </template>
    <template x-if="state.evaluate">
      <div>
        <table class="w-full text-sm">
          <thead>
            <tr class="text-left text-xs uppercase tracking-wide text-zinc-500">
              <th class="py-2">Plugin</th>
              <th>Status</th>
              <th>Grav</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-zinc-100">
            <template x-for="(p, slug) in state.evaluate.plugins" :key="slug">
              <tr>
                <td class="py-2 mono" x-text="slug"></td>
                <td>
                  <span class="inline-block px-2 py-0.5 rounded-full text-xs"
                        :class="{
                          'bg-emerald-100 text-emerald-700': p.classification === 'works',
                          'bg-amber-100 text-amber-800': p.classification === 'needs_update',
                          'bg-red-100 text-red-700': p.classification === 'incompatible',
                          'bg-zinc-200 text-zinc-700': p.classification === 'unknown'
                        }" x-text="p.classification.replace('_',' ')"></span>
                </td>
                <td class="mono text-xs" x-text="(p.compatibility?.grav || []).join(', ') + ' (' + (p.compatibility?.source || '?') + ')'"></td>
                <td>
                  <select x-model="decisions[slug]" class="text-xs rounded border-zinc-300">
                    <option value="import">Import as-is</option>
                    <option value="reinstall">Reinstall from GPM</option>
                    <option value="skip">Skip</option>
                  </select>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
        <div class="mt-6 flex gap-2">
          <button @click="runInstall()" class="rounded-md bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 text-sm font-medium" :disabled="busy">
            <span x-show="!busy">Continue → install plugins</span>
            <span x-show="busy">Installing…</span>
          </button>
          <button @click="state.evaluate = null; run('evaluate')" class="rounded-md border border-zinc-300 px-4 py-2 text-sm" :disabled="busy">Re-scan</button>
        </div>
      </div>
    </template>
  </section>

  <!-- INSTALL -->
  <section x-show="current === 'install'" class="card">
    <h2 class="text-lg font-semibold mb-1">Plugin install results</h2>
    <template x-if="state.install">
      <ul class="divide-y divide-zinc-100 text-sm">
        <template x-for="[slug, r] in Object.entries(state.install?.results || {})" :key="slug">
          <li class="py-2 flex justify-between">
            <span class="mono" x-text="slug"></span>
            <span class="text-xs text-zinc-500" x-text="r.action + (r.files ? ' ('+r.files+' files)' : '') + (r.code !== undefined ? ' exit '+r.code : '') + (r.error ? ' — '+r.error : '') + (r.skipped ? ' — skipped '+r.skipped : '')"></span>
          </li>
        </template>
      </ul>
    </template>
    <template x-if="!state.install">
      <p class="text-sm text-zinc-500">Return to the evaluate step to submit decisions.</p>
    </template>
  </section>

  <!-- TEST -->
  <section x-show="current === 'test'" class="card">
    <h2 class="text-lg font-semibold mb-1">Health check</h2>
    <p class="text-sm text-zinc-500 mb-6">Runs <span class="mono">bin/grav list</span> inside <span class="mono" x-text="'/' + (state.stage_dir || 'grav-2') + '/'"></span> to confirm the staged install bootstraps.</p>
    <button @click="run('test')" class="rounded-md bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 text-sm font-medium" :disabled="busy">
      <span x-show="!busy">Run health check</span>
      <span x-show="busy">Testing…</span>
    </button>
    <template x-if="state.test">
      <div class="mt-6 space-y-2 text-sm">
        <div>
          Result: <span class="font-semibold" :class="state.test.ok ? 'text-emerald-700' : 'text-red-700'" x-text="state.test.ok ? 'passed' : 'failed'"></span>
          (exit <span class="mono" x-text="state.test.code"></span>)
        </div>
        <details>
          <summary class="cursor-pointer text-xs text-zinc-500">stdout</summary>
          <pre class="mt-2 p-2 bg-zinc-100 rounded text-xs mono overflow-x-auto" x-text="state.test.stdout_excerpt"></pre>
        </details>
        <details x-show="state.test.stderr_excerpt">
          <summary class="cursor-pointer text-xs text-zinc-500">stderr</summary>
          <pre class="mt-2 p-2 bg-zinc-100 rounded text-xs mono overflow-x-auto" x-text="state.test.stderr_excerpt"></pre>
        </details>
        <p class="text-xs text-zinc-500">You can also preview the site at <a class="underline" :href="'/' + (state.stage_dir || 'grav-2') + '/'" target="_blank" x-text="'/' + (state.stage_dir || 'grav-2') + '/'"></a>.</p>
      </div>
    </template>
  </section>

  <!-- PROMOTE -->
  <section x-show="current === 'promote'" class="card">
    <h2 class="text-lg font-semibold mb-1">Promote to webroot</h2>
    <p class="text-sm text-zinc-600 mb-4">
      Archives your Grav 1.x site to <span class="mono">grav-1x-archive-YmdHis/</span> and moves the staged 2.0 install up to the webroot.
      <strong class="block mt-2 text-red-700">This cuts over your live site.</strong>
    </p>
    <p class="text-sm text-zinc-500 mb-4">You can skip this step and keep 2.0 running at <span class="mono" x-text="'/' + (state.stage_dir || 'grav-2') + '/'"></span> indefinitely.</p>
    <label class="block mb-4 text-sm">
      Type <span class="mono font-semibold">PROMOTE</span> to confirm:
      <input class="mt-1 block w-48 rounded-md border-zinc-300 mono" x-model="promoteConfirm" placeholder="PROMOTE">
    </label>
    <button @click="run('promote', { confirm: promoteConfirm })" class="rounded-md bg-red-600 hover:bg-red-700 text-white px-4 py-2 text-sm font-medium disabled:opacity-50" :disabled="busy || promoteConfirm !== 'PROMOTE'">
      <span x-show="!busy">Promote now</span>
      <span x-show="busy">Promoting…</span>
    </button>
    <template x-if="state.promote">
      <div class="mt-6 text-sm">
        Archived <span class="mono" x-text="state.promote.archived.length"></span> items to <span class="mono" x-text="state.promote.archive"></span>.
      </div>
    </template>
  </section>

  <!-- CLEANUP -->
  <section x-show="current === 'cleanup'" class="card">
    <h2 class="text-lg font-semibold mb-1">Clean up</h2>
    <p class="text-sm text-zinc-500 mb-4">
      Removes <span class="mono">migrate.php</span> and <span class="mono">.migrating</span> from the webroot.
      Your archive directory is left alone — delete it manually once you're confident the migration is good.
    </p>
    <label class="block mb-4 text-sm">
      Type <span class="mono font-semibold">REMOVE</span> to confirm:
      <input class="mt-1 block w-48 rounded-md border-zinc-300 mono" x-model="cleanupConfirm" placeholder="REMOVE">
    </label>
    <button @click="run('cleanup', { confirm: cleanupConfirm })" class="rounded-md bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 text-sm font-medium disabled:opacity-50" :disabled="busy || cleanupConfirm !== 'REMOVE'">
      <span x-show="!busy">Finish &amp; remove wizard</span>
      <span x-show="busy">Removing…</span>
    </button>
  </section>

  <!-- DONE -->
  <section x-show="current === 'done'" class="card">
    <h2 class="text-lg font-semibold mb-1">Migration complete</h2>
    <p class="text-sm text-zinc-500">All stages finished. You can remove this file when satisfied.</p>
  </section>

  <!-- Debug state (collapsed) -->
  <details class="mt-10 text-xs text-zinc-500">
    <summary class="cursor-pointer">State</summary>
    <pre class="mt-3 p-3 rounded-md bg-zinc-100 overflow-x-auto mono" x-text="JSON.stringify(state, null, 2)"></pre>
  </details>

</div>

<script>
function wizard() {
  return {
    stages: <?php echo json_encode(STAGES); ?>,
    state: <?php echo $stateJson; ?>,
    busy: false,
    error: '',
    auth: { username: '', password: '' },
    preflight: { checks: [], passed: false },
    decisions: {},
    promoteConfirm: '',
    cleanupConfirm: '',

    get current() { return this.state.current_stage || 'auth'; },
    get done()    { return this.state.completed || []; },

    init() {
      const url = new URL(window.location.href);
      if (url.searchParams.has('token')) {
        url.searchParams.delete('token');
        window.history.replaceState({}, '', url.toString());
      }
      this.syncDecisions();
    },

    syncDecisions() {
      const plugins = this.state?.evaluate?.plugins;
      if (!plugins) return;
      for (const [slug, info] of Object.entries(plugins)) {
        if (!(slug in this.decisions)) {
          this.decisions[slug] = info.default_action || 'skip';
        }
      }
    },

    async run(action, body = {}) {
      this.busy = true;
      this.error = '';
      try {
        const form = new FormData();
        form.append('action', action);
        Object.entries(body).forEach(([k, v]) => form.append(k, v));
        const r = await fetch(window.location.pathname, { method: 'POST', body: form, credentials: 'same-origin' });
        const j = await r.json();
        if (!j.ok) throw new Error(j.error || 'request failed');
        if (j.state) this.state = j.state;
        if (action === 'preflight' && j.result) this.preflight = j.result;
        if (action === 'evaluate') this.syncDecisions();
      } catch (e) {
        this.error = e.message;
      } finally {
        this.busy = false;
      }
    },

    async runInstall() {
      this.busy = true;
      this.error = '';
      try {
        const form = new FormData();
        form.append('action', 'install');
        for (const [slug, choice] of Object.entries(this.decisions)) {
          form.append('plugins[' + slug + ']', choice);
        }
        const r = await fetch(window.location.pathname, { method: 'POST', body: form, credentials: 'same-origin' });
        const j = await r.json();
        if (!j.ok) throw new Error(j.error || 'request failed');
        if (j.state) this.state = j.state;
      } catch (e) {
        this.error = e.message;
      } finally {
        this.busy = false;
      }
    }
  };
}
</script>

</body>
</html>
<?php
}

handle_request();
