<?php

/**
 * Turn off Apache's type-map handler in an upgraded site's root `.htaccess`.
 *
 * Debian and Ubuntu ship `AddHandler type-map var`, which applies wherever `.var`
 * appears among a file's extensions and lets the file set its own Content-Type.
 * 1.7.53.12 refuses `.var` in uploaded names and removes the handler in `.htaccess`,
 * so files already stored are served as plain files, and since `.htaccess` is
 * never replaced by an upgrade, this postflight carries the block to existing
 * sites.
 *
 * The block goes at the end of the file, where the shipped file has it, using the
 * file's own line endings. `RemoveHandler` needs the same AllowOverride class
 * (FileInfo) as `RewriteEngine`, so the block is only added to a file that already
 * turns the rewrite engine on. Best-effort and non-destructive: nothing is written
 * when the file already removes the handler, and a read-only `.htaccess` is left
 * alone. Plain PHP 7.3 only, because the classes loaded during an upgrade are the
 * old ones.
 */

return [
    'preflight' => null,
    'postflight' =>
        function ($file = null) {
            $file = $file ?: GRAV_ROOT . '/.htaccess';
            if (!is_file($file) || !is_readable($file) || !is_writable($file)) {
                return;
            }

            $contents = file_get_contents($file);
            if (!is_string($contents)
                || preg_match('/^\s*RemoveHandler\s[^\r\n]*\.var\b/mi', $contents)
                || !preg_match('/^\s*RewriteEngine\s+On\b/mi', $contents)
            ) {
                return;
            }

            $eol = strpos($contents, "\r\n") !== false ? "\r\n" : "\n";
            if ($contents !== '' && substr($contents, -1) !== "\n") {
                $contents .= $eol;
            }

            $block = $eol
                . '# Begin - Apache type maps' . $eol
                . '# Debian and Ubuntu ship `AddHandler type-map var`, which applies wherever `.var`' . $eol
                . '# appears among a file\'s extensions and lets the file set its own Content-Type.' . $eol
                . '# Grav serves no type maps, so the handler is removed. Like the rewrite rules' . $eol
                . '# above, this needs nothing more from AllowOverride than FileInfo.' . $eol
                . '<IfModule mod_mime.c>' . $eol
                . '    RemoveHandler .var' . $eol
                . '</IfModule>' . $eol
                . '# End - Apache type maps' . $eol;

            file_put_contents($file, $contents . $block);
        },
];
