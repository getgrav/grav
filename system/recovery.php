<?php

use Grav\Common\Recovery\RecoveryManager;
use Grav\Common\Upgrade\SafeUpgradeService;

if (!\defined('GRAV_ROOT')) {
    \define('GRAV_ROOT', dirname(__DIR__));
}

session_start([
    'name' => 'grav-recovery',
    'cookie_httponly' => true,
    'cookie_secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_samesite' => 'Lax',
]);

$manager = new RecoveryManager();
$context = $manager->getContext() ?? [];
$token = $context['token'] ?? null;
$authenticated = $token && isset($_SESSION['grav_recovery_authenticated']) && hash_equals($_SESSION['grav_recovery_authenticated'], $token);
$errorMessage = null;
$notice = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'authenticate') {
        $provided = trim($_POST['token'] ?? '');
        if ($token && hash_equals($token, $provided)) {
            $_SESSION['grav_recovery_authenticated'] = $token;
            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit;
        }
        $errorMessage = 'Invalid recovery token.';
    } elseif ($authenticated) {
        $service = new SafeUpgradeService();
        try {
            if ($action === 'rollback' && !empty($_POST['manifest'])) {
                $service->rollback(trim($_POST['manifest']));
                $manager->clear();
                $_SESSION['grav_recovery_authenticated'] = null;
                $notice = 'Rollback complete. Please reload Grav.';
            }
            if ($action === 'clear-flag') {
                $manager->clear();
                $_SESSION['grav_recovery_authenticated'] = null;
                $notice = 'Recovery flag cleared.';
            }
        } catch (\Throwable $e) {
            $errorMessage = $e->getMessage();
        }
    } else {
        $errorMessage = 'Authentication required.';
    }
}

$quarantineFile = GRAV_ROOT . '/user/data/upgrades/quarantine.json';
$quarantine = [];
if (is_file($quarantineFile)) {
    $decoded = json_decode(file_get_contents($quarantineFile), true);
    if (is_array($decoded)) {
        $quarantine = $decoded;
    }
}

$manifestDir = GRAV_ROOT . '/user/data/upgrades';
$snapshots = [];
if (is_dir($manifestDir)) {
    $files = glob($manifestDir . '/*.json');
    if ($files) {
        foreach ($files as $file) {
            $decoded = json_decode(file_get_contents($file), true);
            if (!is_array($decoded)) {
                continue;
            }

            $id = $decoded['id'] ?? pathinfo($file, PATHINFO_FILENAME);
            if (!is_string($id) || $id === '' || strncmp($id, 'snapshot-', 9) !== 0) {
                continue;
            }

            $decoded['id'] = $id;
            $decoded['file'] = basename($file);
            $decoded['created_at'] = (int)($decoded['created_at'] ?? filemtime($file) ?: 0);
            $snapshots[] = $decoded;
        }

        if ($snapshots) {
            usort($snapshots, static function (array $a, array $b): int {
                return ($b['created_at'] ?? 0) <=> ($a['created_at'] ?? 0);
            });
        }
    }
}

$latestSnapshot = $snapshots[0] ?? null;

header('Content-Type: text/html; charset=utf-8');

?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Grav Recovery Mode</title>
    <style>
        body { font-family: system-ui, -apple-system, BlinkMacSystemFont, sans-serif; margin: 0; padding: 40px; background: #111; color: #eee; }
        .panel { max-width: 720px; margin: 0 auto; background: #1d1d1f; padding: 24px 32px; border-radius: 12px; box-shadow: 0 10px 45px rgba(0,0,0,0.4); }
        h1 { font-size: 2.5rem; margin-top: 0; color: #fff; display:flex;align-items:center; }
        h1 > img {margin-right:1rem;}
        code { background: rgba(255,255,255,0.08); padding: 2px 4px; border-radius: 4px; }
        form { margin-top: 16px; }
        input[type="text"] { width: 100%; padding: 10px; border: 1px solid #333; border-radius: 6px; background: #151517; color: #fff; }
        button { margin-top: 12px; padding: 10px 16px; border: 0; border-radius: 6px; cursor: pointer; background: #3c8bff; color: #fff; font-weight: 600; }
        button.secondary { background: #444; }
        .message { padding: 10px 14px; border-radius: 6px; margin-top: 12px; }
        .error { background: rgba(220, 53, 69, 0.15); color: #ffb3b8; }
        .notice { background: rgba(25, 135, 84, 0.2); color: #bdf8d4; }
        ul { padding-left: 20px; }
        li { margin-bottom: 8px; }
        .card { border: 1px solid #2a2a2d; border-radius: 8px; padding: 14px 16px; margin-top: 16px; background: #161618; }
        small { color: #888; }
    </style>
</head>
<body>
<div class="panel">
    <h1><img src="system/assets/grav.png">Grav Recovery Mode</h1>
    <?php if ($notice): ?>
        <div class="message notice"><?php echo htmlspecialchars($notice, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if ($errorMessage): ?>
        <div class="message error"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <?php if (!$authenticated): ?>
        <p>This site is running in recovery mode because Grav detected a fatal error.</p>
        <p>Locate the recovery token in <code>user/data/recovery.flag</code> and enter it below.</p>
        <form method="post">
            <input type="hidden" name="action" value="authenticate">
            <label for="token">Recovery token</label>
            <input id="token" name="token" type="text" autocomplete="one-time-code" required>
            <button type="submit">Unlock Recovery</button>
        </form>
    <?php else: ?>
        <div class="card">
            <h2>Failure Details</h2>
            <ul>
                <li><strong>Message:</strong> <?php echo htmlspecialchars($context['message'] ?? 'Unknown', ENT_QUOTES, 'UTF-8'); ?></li>
                <li><strong>File:</strong> <?php echo htmlspecialchars($context['file'] ?? 'n/a', ENT_QUOTES, 'UTF-8'); ?></li>
                <li><strong>Line:</strong> <?php echo htmlspecialchars((string)($context['line'] ?? 'n/a'), ENT_QUOTES, 'UTF-8'); ?></li>
                <?php if (!empty($context['plugin'])): ?>
                    <li><strong>Quarantined plugin:</strong> <?php echo htmlspecialchars($context['plugin'], ENT_QUOTES, 'UTF-8'); ?></li>
                <?php endif; ?>
            </ul>
        </div>

        <?php if ($quarantine): ?>
            <div class="card">
                <h3>Quarantined Plugins</h3>
                <ul>
                    <?php foreach ($quarantine as $entry): ?>
                        <li>
                            <strong><?php echo htmlspecialchars($entry['slug'], ENT_QUOTES, 'UTF-8'); ?></strong>
                            <small>(disabled at <?php echo date('c', $entry['disabled_at']); ?>)</small><br>
                            <?php echo htmlspecialchars($entry['message'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="card">
            <h3>Rollback</h3>
            <?php if ($latestSnapshot): ?>
                <form method="post">
                    <input type="hidden" name="action" value="rollback">
                    <input type="hidden" name="manifest" value="<?php echo htmlspecialchars($latestSnapshot['id'], ENT_QUOTES, 'UTF-8'); ?>">
                    <p>
                        Latest snapshot:
                        <code><?php echo htmlspecialchars($latestSnapshot['id'], ENT_QUOTES, 'UTF-8'); ?></code>
                        <?php if (!empty($latestSnapshot['label'])): ?>
                            <br><small><?php echo htmlspecialchars($latestSnapshot['label'], ENT_QUOTES, 'UTF-8'); ?></small>
                        <?php endif; ?>
                        — Grav <?php echo htmlspecialchars($latestSnapshot['target_version'] ?? 'unknown', ENT_QUOTES, 'UTF-8'); ?>
                        <?php if (!empty($latestSnapshot['created_at'])): ?>
                            <br><small>Created <?php echo htmlspecialchars(date('c', (int)$latestSnapshot['created_at']), ENT_QUOTES, 'UTF-8'); ?></small>
                        <?php endif; ?>
                    </p>
                    <button type="submit" class="secondary">Rollback to Latest Snapshot</button>
                </form>
            <?php else: ?>
                <p>No upgrade snapshots were found.</p>
            <?php endif; ?>
        </div>

        <form method="post">
            <input type="hidden" name="action" value="clear-flag">
            <button type="submit" class="secondary">Exit Recovery Mode</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
