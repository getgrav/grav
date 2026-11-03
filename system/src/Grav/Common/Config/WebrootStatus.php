<?php

/**
 * @package    Grav\Common\Config
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Config;

/**
 * Health checks for the layout of a Grav install, used by `bin/grav webroot:status`.
 */
final class WebrootStatus
{
    public const PASS = 'pass';
    public const WARN = 'warn';
    public const FAIL = 'fail';

    /** @var string[] Entries that must never be inside the webroot of a split site. */
    public const PRIVATE_ITEMS = ['vendor', 'system', 'bin', '.env', 'cache', 'logs', 'tmp', 'backup', 'composer.json', 'composer.lock'];

    /** @var string */
    private $root;
    /** @var string */
    private $webroot;
    /** @var string */
    private $userPath;
    /** @var string */
    private $system;

    /**
     * @param string      $root     GRAV_ROOT.
     * @param string      $webroot  GRAV_WEBROOT.
     * @param string      $userPath The user folder, relative to the webroot.
     * @param string|null $system   The real system folder, GRAV_ROOT/system by default.
     */
    public function __construct(string $root, string $webroot, string $userPath = 'user', ?string $system = null)
    {
        $this->root = rtrim(str_replace('\\', '/', $root), '/');
        $this->webroot = rtrim(str_replace('\\', '/', $webroot), '/');
        $this->userPath = trim($userPath, '/');
        $this->system = $system ?? $this->root . '/system';
    }

    public function publisher(): WebrootPublisher
    {
        return new WebrootPublisher($this->system, $this->webroot . '/system');
    }

    public function isSplit(): bool
    {
        return $this->root !== $this->webroot;
    }

    /**
     * Run every check.
     *
     * @return array<int,array{id:string,status:string,message:string,details:string[]}>
     */
    public function checks(): array
    {
        $checks = [
            $this->checkPrivateItems(),
            $this->checkWebrootEntries(),
            $this->checkStrayEntries(),
            $this->checkPublished(),
            $this->checkIndex(),
            $this->checkSymlinks(),
        ];

        return array_values(array_filter($checks));
    }

    /**
     * @param array $checks Result of checks().
     * @return bool
     */
    public static function hasFailures(array $checks): bool
    {
        foreach ($checks as $check) {
            if ($check['status'] === self::FAIL) {
                return true;
            }
        }

        return false;
    }

    /**
     * Docroot settings for the common web servers.
     *
     * @param string $webroot
     * @return array<string,string> Server name => config snippet.
     */
    public static function docrootSnippets(string $webroot): array
    {
        return [
            'Apache' => "DocumentRoot \"{$webroot}\"\n<Directory \"{$webroot}\">\n    AllowOverride All\n    Require all granted\n</Directory>",
            'nginx' => "root {$webroot};    # in the server block from webserver-configs/nginx.conf",
            'Caddy' => "root * {$webroot}    # in webserver-configs/Caddyfile",
        ];
    }

    private function checkPrivateItems(): ?array
    {
        if (!$this->isSplit()) {
            return null;
        }

        $found = [];
        foreach (self::PRIVATE_ITEMS as $name) {
            $path = $this->webroot . '/' . $name;
            if (!file_exists($path) && !is_link($path)) {
                continue;
            }
            if ($name === 'system' && is_dir($path) && !is_link($path)) {
                // The published copy of system/ is expected; anything else in there is not.
                $extra = $this->publisher()->plan()['extra'];
                foreach ($extra as $file) {
                    $found[] = 'system/' . $file;
                }
                continue;
            }
            $found[] = $name;
        }

        if ($found) {
            return $this->result('private-in-webroot', self::FAIL, 'Private files are inside the webroot, where the web server can reach them.', $found);
        }

        return $this->result('private-in-webroot', self::PASS, 'No private files inside the webroot.');
    }

    private function checkWebrootEntries(): array
    {
        $fail = $warn = [];
        foreach (InstallLayout::WEBROOT_ENTRIES as $name) {
            if ($name === 'user') {
                $name = $this->userPath;
            }
            if (file_exists($this->webroot . '/' . $name)) {
                continue;
            }
            if ($name === 'index.php' || $name === $this->userPath) {
                $fail[] = $name;
            } else {
                $warn[] = $name;
            }
        }

        if ($fail) {
            return $this->result('webroot-entries', self::FAIL, 'The webroot is missing files the site needs.', array_merge($fail, $warn));
        }
        if ($warn) {
            // .htaccess only matters on Apache; assets/ and images/ are made when first needed.
            return $this->result('webroot-entries', self::WARN, 'The webroot is missing some optional entries.', $warn);
        }

        return $this->result('webroot-entries', self::PASS, 'The webroot has index.php, .htaccess, robots.txt, user/, assets/ and images/.');
    }

    private function checkStrayEntries(): ?array
    {
        if (!$this->isSplit()) {
            return null;
        }

        $found = [];
        foreach (['index.php', $this->userPath] as $name) {
            if (file_exists($this->root . '/' . $name) || is_link($this->root . '/' . $name)) {
                $found[] = $name;
            }
        }

        if (in_array($this->userPath, $found, true)) {
            return $this->result('stray-in-root', self::FAIL, 'The private folder has its own user/ folder next to the webroot one. Only the webroot copy is used.', $found);
        }
        if ($found) {
            return $this->result('stray-in-root', self::WARN, 'The private folder has its own index.php. If the web server serves the private folder, everything in it is exposed.', $found);
        }

        return $this->result('stray-in-root', self::PASS, 'No index.php or user/ left in the private folder.');
    }

    private function checkPublished(): array
    {
        if (!$this->isSplit()) {
            return $this->result('published', self::PASS, 'Published system files are not needed in the single-folder layout.');
        }

        $publisher = $this->publisher();
        $plan = $publisher->plan();
        if ($publisher->isInSync($plan)) {
            return $this->result('published', self::PASS, sprintf('%d published system files are in sync.', $plan['total']));
        }

        if (\count($plan['copy']) === $plan['total'] && !$publisher->manifest()) {
            return $this->result('published', self::FAIL, sprintf('Nothing is published yet: %d system files linked by URL are missing from the webroot. Run bin/grav webroot:sync.', $plan['total']));
        }

        $details = [];
        foreach (['copy' => 'missing', 'update' => 'changed', 'delete' => 'no longer in system/'] as $key => $label) {
            foreach ($plan[$key] as $file) {
                $details[] = "{$label}: system/{$file}";
            }
        }
        if (!$details) {
            $details[] = 'the manifest is out of date';
        }

        return $this->result('published', self::FAIL, 'Published system files are out of sync. Run bin/grav webroot:sync.', $details);
    }

    private function checkIndex(): array
    {
        $file = $this->webroot . '/index.php';
        $code = is_file($file) ? (string) file_get_contents($file) : '';
        if ($code === '') {
            return $this->result('index-php', self::FAIL, 'index.php is missing from the webroot.');
        }

        // The stock index.php looks for vendor/ and system/ one folder up when they aren't next to it.
        if (str_contains($code, 'GRAV_ROOT') && str_contains($code, 'dirname(__DIR__)')) {
            return $this->result('index-php', self::PASS, 'index.php is a stock copy that detects the layout.');
        }

        if ($this->isSplit()) {
            return $this->result('index-php', self::FAIL, 'index.php does not look for the private folder, so the site can\'t start. Copy the stock index.php from this Grav version into the webroot.');
        }

        return $this->result('index-php', self::WARN, 'index.php is an older or edited copy. Replace it with the stock one before running webroot:split.');
    }

    private function checkSymlinks(): array
    {
        $user = $this->webroot . '/' . $this->userPath;
        if (!is_dir($user)) {
            return $this->result('symlinks', self::PASS, 'No user folder to check.');
        }

        $links = WebrootPlanner::outsideLinks($user);
        $broken = [];
        foreach ($links as $link => $info) {
            if ($info['broken']) {
                $broken[] = substr($link, \strlen($this->webroot) + 1) . ' -> ' . $info['target'];
            }
        }

        if ($broken) {
            return $this->result('symlinks', self::FAIL, 'Relative symlinks in user/ point outside it and are broken.', $broken);
        }
        if ($links) {
            return $this->result('symlinks', self::PASS, sprintf('%d relative symlink(s) in user/ point outside it, and all of them work.', \count($links)));
        }

        return $this->result('symlinks', self::PASS, 'No relative symlinks in user/ point outside it.');
    }

    /**
     * @param string[] $details
     * @return array{id:string,status:string,message:string,details:string[]}
     */
    private function result(string $id, string $status, string $message, array $details = []): array
    {
        return ['id' => $id, 'status' => $status, 'message' => $message, 'details' => $details];
    }
}
