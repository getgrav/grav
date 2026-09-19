<?php

/**
 * Add `tmp` to the folder block in an upgraded site's root `.htaccess`.
 *
 * 2.1.7 added `tmp` to the shipped `RewriteRule ^(\.git|cache|bin|...)/(.*)`
 * folder block (#4316), but `.htaccess` is in the installer `$ignores` list
 * and is never replaced by an upgrade, and 2.1.7 shipped no postflight for it.
 * Every upgraded Apache site kept serving `tmp/` directly, including temporary
 * package downloads, and the Admin dashboard's storage probe flagged it.
 *
 * The folder block is found by its alternation rather than byte-for-byte:
 * older installs carry `[F]` where newer ones carry `[F,NC]`, and some sites
 * have added folders of their own. `tmp` goes in after `backup`, where the
 * shipped file has it. Best-effort and non-destructive: nothing is written
 * unless exactly one folder-block rule is present and it lacks `tmp`, and a
 * read-only `.htaccess` is left alone.
 */

return [
    'preflight' => null,
    'postflight' =>
        function () {
            $file = GRAV_ROOT . '/.htaccess';
            if (!is_file($file) || !is_readable($file) || !is_writable($file)) {
                return;
            }

            $contents = file_get_contents($file);
            if (!is_string($contents)) {
                return;
            }

            $pattern = '/^(RewriteRule \^\()([^)\r\n]+)(\)\/\(\.\*\) error \[F(?:,NC)?\][ \t]*)(?=\r?$)/m';
            if (!preg_match_all($pattern, $contents, $matches, PREG_SET_ORDER)) {
                return;
            }

            // The folder block is the rule whose alternation names these stock folders.
            $blocks = array_values(array_filter($matches, static function (array $match): bool {
                $folders = explode('|', $match[2]);
                return !array_diff(['cache', 'bin', 'logs', 'backup'], $folders);
            }));
            if (count($blocks) !== 1) {
                return;
            }

            $folders = explode('|', $blocks[0][2]);
            if (in_array('tmp', $folders, true)) {
                return;
            }

            array_splice($folders, array_search('backup', $folders, true) + 1, 0, 'tmp');
            $replacement = $blocks[0][1] . implode('|', $folders) . $blocks[0][3];

            $updated = str_replace($blocks[0][0], $replacement, $contents);
            if ($updated === $contents) {
                return;
            }

            file_put_contents($file, $updated);
        },
];
