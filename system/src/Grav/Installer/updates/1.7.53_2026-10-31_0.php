<?php

/**
 * Carry three deny rules and a tightened dot-file rule from the shipped `.htaccess`
 * to an upgraded site's root `.htaccess`.
 *
 * 1.7.53.8 adds them to the shipped file, as Grav 2 has them:
 *
 * - `tmp` in the folder block, so temporary package downloads are not served.
 * - A rule refusing scripts in `images/` (image derivatives) and `assets/`
 *   (combined CSS/JS), placed before the `^(system|vendor)/` file-type rule.
 * - A rule refusing a request in `images/`, `assets/`, `system/`, `vendor/` or
 *   `user/` that maps to a file with a path after it (`file.php/x`), placed
 *   after the `^(user)/(.*)\.` file-type rule.
 * - The dot-file rule's `.well-known` exception ends at a `/` or the end of the
 *   path, so hidden names that only start with `well-known` stay blocked. The
 *   line is replaced only when it appears once, unchanged.
 *
 * `.htaccess` is never replaced by an upgrade, so this postflight is the only way
 * existing Apache sites get them. Each change is independent and best-effort:
 * it is made only when its anchor rule appears exactly once and the new rule is
 * not there yet, using the file's own line endings. A read-only `.htaccess` is
 * left alone. Plain PHP 7.3 only, because the classes loaded during an upgrade
 * are the old ones.
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
            if (!is_string($contents)) {
                return;
            }
            $updated = $contents;

            // 1. `tmp` in the folder block. The block is found by its alternation, since
            //    older installs carry `[F]` and some sites have added folders of their own.
            $pattern = '/^(RewriteRule \^\()([^)\r\n]+)(\)\/\(\.\*\) error \[F(?:,NC)?\][ \t]*)(?=\r?$)/m';
            if (preg_match_all($pattern, $updated, $matches, PREG_SET_ORDER)) {
                $blocks = array_values(array_filter($matches, static function (array $match) {
                    return !array_diff(['cache', 'bin', 'logs', 'backup'], explode('|', $match[2]));
                }));
                if (count($blocks) === 1) {
                    $folders = explode('|', $blocks[0][2]);
                    if (!in_array('tmp', $folders, true)) {
                        array_splice($folders, array_search('backup', $folders, true) + 1, 0, 'tmp');
                        $updated = str_replace($blocks[0][0], $blocks[0][1] . implode('|', $folders) . $blocks[0][3], $updated);
                    }
                }
            }

            // 2. Scripts in images/ and assets/, before the system/vendor file-type rule
            //    (and above that rule's own comment line).
            if (strpos($updated, 'RewriteRule ^(images|assets)/') === false
                && preg_match_all('/^RewriteRule \^\(system\|vendor\)\/[^\r\n]*(\r?\n)/m', $updated, $matches, PREG_OFFSET_CAPTURE) === 1
            ) {
                $offset = $matches[0][0][1];
                $eol = $matches[1][0][0];
                $rule = '# Block running scripts in the public cache folders (image derivatives and combined assets)' . $eol
                    . 'RewriteRule ^(images|assets)/(.*)\.(php|php2|php3|php4|php5|php7|php8|phar|phtml|pht|phtm|phps|pl|py|cgi|sh|bat)$ error [F,NC]' . $eol;

                $insertAt = $offset;
                $lineStart = $offset >= 2 ? strrpos(substr($updated, 0, $offset - 1), "\n") : false;
                if ($lineStart !== false) {
                    $previous = substr($updated, $lineStart + 1, $offset - $lineStart - 1);
                    if (strpos(ltrim($previous), '#') === 0) {
                        $insertAt = $lineStart + 1;
                    }
                }
                $updated = substr($updated, 0, $insertAt) . $rule . substr($updated, $insertAt);
            }

            // 3. A file with a path after it, after the user file-type rule.
            if (strpos($updated, '^(images|assets|system|vendor|user)/') === false
                && preg_match_all('/^RewriteRule \^\(user\)\/\(\.\*\)\\\\\.[^\r\n]*(\r?\n)/m', $updated, $matches, PREG_OFFSET_CAPTURE) === 1
            ) {
                $insertAt = $matches[0][0][1] + strlen($matches[0][0][0]);
                $eol = $matches[1][0][0];
                $rule = '# A script followed by a path (`file.php/x`) still runs as `file.php`, so refuse any' . $eol
                    . '# request in these folders that maps to a file with a path after it' . $eol
                    . 'RewriteCond %{REQUEST_FILENAME} -f' . $eol
                    . 'RewriteCond %{PATH_INFO} ^/' . $eol
                    . 'RewriteRule ^(images|assets|system|vendor|user)/ error [F,NC]' . $eol;
                $updated = substr($updated, 0, $insertAt) . $rule . substr($updated, $insertAt);
            }

            // 4. Limit the dot-file exception to the `.well-known` folder itself.
            $old = '/^' . preg_quote('RewriteRule (^|/)\.(?!well-known) - [F]', '/') . '(?=\r?\n|$)/m';
            if (preg_match_all($old, $updated) === 1) {
                $updated = preg_replace($old, 'RewriteRule (^|/)\\.(?!well-known(?:/|\$)) - [F]', $updated, 1);
            }

            if ($updated !== $contents) {
                file_put_contents($file, $updated);
            }
        },
];
