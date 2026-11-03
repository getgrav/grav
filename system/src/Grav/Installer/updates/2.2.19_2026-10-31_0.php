<?php

/**
 * Keep the `.well-known` exception in an upgraded site's root `.htaccess` to the
 * `.well-known` folder itself.
 *
 * The dot-file rule excepted any name starting with `well-known`, so a hidden
 * file or folder such as `.well-knownX` was served. 2.2.19 ends the exception at
 * a `/` or the end of the path, as the other shipped server configs do, and since
 * `.htaccess` is never replaced by an upgrade, this postflight carries it to
 * existing sites.
 *
 * Best-effort and non-destructive: the line is only replaced when it appears
 * exactly once, unchanged, keeping the file's own line endings, and a read-only
 * `.htaccess` is left alone. Plain PHP only, because the classes loaded during an
 * upgrade are the old ones.
 */

return [
    'preflight' => null,
    'postflight' =>
        function (?string $file = null) {
            $file ??= GRAV_WEBROOT . '/.htaccess';
            if (!is_file($file) || !is_readable($file) || !is_writable($file)) {
                return;
            }

            $contents = file_get_contents($file);
            if (!is_string($contents)) {
                return;
            }

            $old = 'RewriteRule (^|/)\.(?!well-known) - [F]';
            $new = 'RewriteRule (^|/)\.(?!well-known(?:/|$)) - [F]';
            $pattern = '/^' . preg_quote($old, '/') . '(?=\r?\n|$)/m';
            if (preg_match_all($pattern, $contents) !== 1) {
                return;
            }

            file_put_contents($file, preg_replace($pattern, str_replace('$', '\$', $new), $contents, 1));
        },
];
