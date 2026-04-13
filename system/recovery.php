<?php

use Grav\Common\Filesystem\Folder;
use Grav\Common\Recovery\RecoveryManager;

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
$errorMessage = null;
$notice = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'clear-flag') {
        $manager->clear();
        $notice = 'Recovery flag cleared. <a href="' . htmlspecialchars($_SERVER['REQUEST_URI'], ENT_QUOTES, 'UTF-8') . '">Reload the page</a> to continue.';
    } elseif ($action === 'disable-recovery') {
        $configDir = GRAV_ROOT . '/user/config';
        $configFile = $configDir . '/system.yaml';
        Folder::create($configDir);

        $config = [];
        if (is_file($configFile)) {
            $content = file_get_contents($configFile);
            if ($content !== false) {
                $config = \Symfony\Component\Yaml\Yaml::parse($content) ?? [];
            }
        }

        if (!isset($config['updates'])) {
            $config['updates'] = [];
        }
        $config['updates']['recovery_mode'] = false;
        $yaml = \Symfony\Component\Yaml\Yaml::dump($config, 4, 2);
        file_put_contents($configFile, $yaml);

        $manager->clear();
        $notice = 'Recovery mode has been disabled. <a href="' . htmlspecialchars($_SERVER['REQUEST_URI'], ENT_QUOTES, 'UTF-8') . '">Reload the page</a> to continue.';
    }
}

// Determine base URL for assets
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
$baseUrl = rtrim(dirname($scriptName), '/\\');
if ($baseUrl === '.' || $baseUrl === '') {
    $baseUrl = '';
}

header('Content-Type: text/html; charset=utf-8');

?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Grav Recovery Mode</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            margin: 0;
            padding: 20px;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            min-height: 100vh;
            color: #e8e8e8;
            line-height: 1.6;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        .header {
            text-align: center;
            padding: 30px 0;
        }
        .header img {
            width: 80px;
            height: auto;
            margin-bottom: 16px;
        }
        .header h1 {
            font-size: 1.8rem;
            margin: 0 0 8px 0;
            color: #fff;
            font-weight: 600;
        }
        .header .subtitle {
            color: #a0a0a0;
            font-size: 1rem;
            margin: 0;
        }
        .alert {
            padding: 16px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }
        .alert-error {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
        }
        .alert-warning {
            background: rgba(245, 158, 11, 0.15);
            border: 1px solid rgba(245, 158, 11, 0.3);
            color: #fcd34d;
        }
        .alert-success {
            background: rgba(34, 197, 94, 0.15);
            border: 1px solid rgba(34, 197, 94, 0.3);
            color: #86efac;
        }
        .alert-success a { color: #4ade80; }
        .alert-icon {
            font-size: 1.5rem;
            flex-shrink: 0;
            line-height: 1;
        }
        .alert-content { flex: 1; }
        .alert-title {
            font-weight: 600;
            margin-bottom: 4px;
        }
        .card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 20px;
        }
        .card h2 {
            font-size: 1.1rem;
            margin: 0 0 16px 0;
            color: #fff;
            font-weight: 600;
        }
        .card h3 {
            font-size: 1rem;
            margin: 0 0 12px 0;
            color: #fff;
            font-weight: 600;
        }
        .error-summary {
            background: rgba(0, 0, 0, 0.2);
            border-radius: 8px;
            padding: 16px;
            font-family: 'SF Mono', Monaco, 'Cascadia Code', monospace;
            font-size: 0.9rem;
            overflow-x: auto;
        }
        .error-summary .error-message {
            color: #f87171;
            word-break: break-word;
        }
        .error-summary .error-location {
            color: #94a3b8;
            margin-top: 8px;
            font-size: 0.85rem;
        }
        .btn-group {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 16px;
        }
        button, .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        .btn-primary {
            background: #3b82f6;
            color: #fff;
        }
        .btn-primary:hover {
            background: #2563eb;
        }
        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: #e8e8e8;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.15);
        }
        .btn-danger {
            background: rgba(239, 68, 68, 0.2);
            color: #fca5a5;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }
        .btn-danger:hover {
            background: rgba(239, 68, 68, 0.3);
        }
        details {
            margin-top: 16px;
        }
        summary {
            cursor: pointer;
            color: #94a3b8;
            font-size: 0.9rem;
            padding: 8px 0;
            user-select: none;
        }
        summary:hover {
            color: #cbd5e1;
        }
        .stack-trace {
            background: rgba(0, 0, 0, 0.3);
            border-radius: 8px;
            padding: 16px;
            margin-top: 12px;
            font-family: 'SF Mono', Monaco, 'Cascadia Code', monospace;
            font-size: 0.8rem;
            overflow-x: auto;
            white-space: pre-wrap;
            word-break: break-all;
            color: #94a3b8;
            max-height: 400px;
            overflow-y: auto;
        }
        .info-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .info-list li {
            padding: 8px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            display: flex;
            gap: 12px;
        }
        .info-list li:last-child {
            border-bottom: none;
        }
        .info-list .label {
            color: #94a3b8;
            min-width: 120px;
            flex-shrink: 0;
        }
        .info-list .value {
            color: #e8e8e8;
            word-break: break-word;
        }
        .help-text {
            color: #64748b;
            font-size: 0.85rem;
            margin-top: 8px;
        }
        code {
            background: rgba(255, 255, 255, 0.1);
            padding: 2px 6px;
            border-radius: 4px;
            font-family: 'SF Mono', Monaco, 'Cascadia Code', monospace;
            font-size: 0.85rem;
        }
        @media (max-width: 600px) {
            body { padding: 12px; }
            .card { padding: 16px; }
            .btn-group { flex-direction: column; }
            button, .btn { width: 100%; justify-content: center; }
            .info-list li { flex-direction: column; gap: 4px; }
            .info-list .label { min-width: auto; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <img src="<?php echo htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8'); ?>/system/assets/grav.png" alt="Grav" onerror="this.style.display='none'">
        <h1>Recovery Mode</h1>
        <p class="subtitle">Grav has encountered an error during a recent update</p>
    </div>

    <?php if ($notice): ?>
        <div class="alert alert-success">
            <span class="alert-icon">&#10003;</span>
            <div class="alert-content"><?php echo $notice; ?></div>
        </div>
    <?php endif; ?>

    <?php if ($errorMessage): ?>
        <div class="alert alert-error">
            <span class="alert-icon">&#9888;</span>
            <div class="alert-content">
                <div class="alert-title">Action Failed</div>
                <?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="alert alert-warning">
        <span class="alert-icon">&#9888;</span>
        <div class="alert-content">
            <div class="alert-title">A Fatal Error Occurred</div>
            Grav detected a fatal error after a recent upgrade and has entered recovery mode to protect your site.
        </div>
    </div>

    <div class="card">
        <h2>Error Details</h2>
        <div class="error-summary">
            <div class="error-message"><?php echo htmlspecialchars($context['message'] ?? 'Unknown error', ENT_QUOTES, 'UTF-8'); ?></div>
            <?php if (!empty($context['file'])): ?>
                <div class="error-location">
                    <?php echo htmlspecialchars($context['file'], ENT_QUOTES, 'UTF-8'); ?><?php if (!empty($context['line'])): ?>:<?php echo htmlspecialchars((string)$context['line'], ENT_QUOTES, 'UTF-8'); ?><?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($context['trace'])): ?>
            <details>
                <summary>View Stack Trace</summary>
                <div class="stack-trace"><?php echo htmlspecialchars($context['trace'], ENT_QUOTES, 'UTF-8'); ?></div>
            </details>
        <?php endif; ?>

        <?php if (!empty($context['plugin'])): ?>
            <details open>
                <summary>Affected Plugin</summary>
                <ul class="info-list" style="margin-top: 12px;">
                    <li>
                        <span class="label">Plugin</span>
                        <span class="value"><strong><?php echo htmlspecialchars($context['plugin'], ENT_QUOTES, 'UTF-8'); ?></strong> (has been automatically disabled)</span>
                    </li>
                </ul>
            </details>
        <?php endif; ?>
    </div>


    <div class="card">
        <h2>What would you like to do?</h2>
        <p style="margin-top: 0; color: #94a3b8;">Choose an action to resolve this issue:</p>

        <div class="btn-group">
            <form method="post" style="display: contents;">
                <input type="hidden" name="action" value="clear-flag">
                <button type="submit" class="btn btn-primary">Clear Recovery &amp; Continue</button>
            </form>
            <form method="post" style="display: contents;">
                <input type="hidden" name="action" value="disable-recovery">
                <button type="submit" class="btn btn-secondary" title="Prevents recovery mode from activating in the future">Disable Recovery Mode</button>
            </form>
        </div>
        <p class="help-text">
            <strong>Clear Recovery &amp; Continue:</strong> Clears the recovery flag and attempts to load your site normally.<br>
            <strong>Disable Recovery Mode:</strong> Sets <code>updates.recovery_mode: false</code> in your configuration so recovery mode won't trigger again.
        </p>
    </div>

</div>
</body>
</html>
