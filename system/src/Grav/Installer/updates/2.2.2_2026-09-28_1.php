<?php

/**
 * Refuse a script followed by a path in an upgraded site's root `.htaccess`.
 *
 * The file-type rules for `images/`, `assets/`, `system/`, `vendor/` and `user/`
 * match the end of the URL, but Apache runs `/images/file.php/x` as `file.php`
 * with PATH_INFO `/x`, so none of them fired. 2.2.2 adds a rule that refuses any
 * request in those folders that maps to a file with a path after it, and since
 * `.htaccess` is never replaced by an upgrade, this postflight carries it to
 * existing sites.
 *
 * The rule goes in directly after the `^(user)/(.*)\.` file-type rule, where the
 * shipped file has it, using the file's own line endings. Best-effort and
 * non-destructive: nothing is written unless exactly one such anchor rule exists
 * and the rule is not already present, and a read-only `.htaccess` is left alone.
 * Plain PHP only, because the classes loaded during an upgrade are the old ones.
 */

return [
    'preflight' => null,
    'postflight' =>
        function (?string $file = null) {
            $file ??= GRAV_ROOT . '/.htaccess';
            if (!is_file($file) || !is_readable($file) || !is_writable($file)) {
                return;
            }

            $contents = file_get_contents($file);
            if (!is_string($contents) || str_contains($contents, '^(images|assets|system|vendor|user)/')) {
                return;
            }

            $pattern = '/^RewriteRule \^\(user\)\/\(\.\*\)\\\\\.[^\r\n]*(\r?\n)/m';
            if (preg_match_all($pattern, $contents, $matches, PREG_OFFSET_CAPTURE) !== 1) {
                return;
            }

            [$anchor, $offset] = $matches[0][0];
            $eol = $matches[1][0][0];
            $rule = '# A script followed by a path (`file.php/x`) still runs as `file.php`, so refuse any' . $eol
                . '# request in these folders that maps to a file with a path after it' . $eol
                . 'RewriteCond %{REQUEST_FILENAME} -f' . $eol
                . 'RewriteCond %{PATH_INFO} ^/' . $eol
                . 'RewriteRule ^(images|assets|system|vendor|user)/ error [F,NC]' . $eol;

            $insertAt = $offset + strlen($anchor);
            file_put_contents($file, substr($contents, 0, $insertAt) . $rule . substr($contents, $insertAt));
        },
];
