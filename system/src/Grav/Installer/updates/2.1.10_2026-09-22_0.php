<?php

/**
 * Block running scripts in `images/` and `assets/` in an upgraded site's root `.htaccess`.
 *
 * The shipped deny rules covered `user/`, `system/`, `vendor/` and the cache/log
 * folders, but not `images/` (image derivatives) or `assets/` (combined CSS/JS),
 * so a `.php` file that landed there through any plugin or host bug would run.
 * 2.1.10 adds a rule for them to the shipped `.htaccess`, and since `.htaccess`
 * is never replaced by an upgrade, this postflight carries it to existing sites.
 *
 * The rule goes in directly before the `^(system|vendor)/` file-type rule, where
 * the shipped file has it, using the file's own line endings. Best-effort and
 * non-destructive: nothing is written unless exactly one such anchor rule exists
 * and no `images|assets` rule is present, and a read-only `.htaccess` is left alone.
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
            if (!is_string($contents) || str_contains($contents, '^(images|assets)/')) {
                return;
            }

            $pattern = '/^RewriteRule \^\(system\|vendor\)\/[^\r\n]*\r?\n/m';
            if (preg_match_all($pattern, $contents, $matches, PREG_OFFSET_CAPTURE) !== 1) {
                return;
            }

            [$anchor, $offset] = $matches[0][0];
            $eol = str_ends_with($anchor, "\r\n") ? "\r\n" : "\n";
            $rule = '# Block running scripts in the public cache folders (image derivatives and combined assets)' . $eol
                . 'RewriteRule ^(images|assets)/(.*)\.(php|php2|php3|php4|php5|php7|php8|phar|phtml|pht|phtm|phps|pl|py|cgi|sh|bat)$ error [F,NC]' . $eol;

            // Keep the anchor's own comment line directly above it.
            $lineStart = strrpos(substr($contents, 0, $offset), "\n", -2);
            $insertAt = $offset;
            if ($lineStart !== false) {
                $previous = substr($contents, $lineStart + 1, $offset - $lineStart - 1);
                if (str_starts_with(ltrim($previous), '#')) {
                    $insertAt = $lineStart + 1;
                }
            }

            file_put_contents($file, substr($contents, 0, $insertAt) . $rule . substr($contents, $insertAt));
        },
];
