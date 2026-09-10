<?php

/**
 * Markdown output for agents: let `.md` page routes through the site root
 * `.htaccess` on existing installs.
 *
 * Grav 2.1 answers `<route>.md` with the page as Markdown, but every
 * `.htaccess` shipped before it carried an unconditional
 * `RewriteRule \.md$ error [F,NC]`, so on an upgraded Apache site every
 * Markdown URL is a 403 before Grav ever runs. `.htaccess` is in the installer
 * `$ignores` list and is never replaced by an upgrade, so this postflight
 * narrows that rule to real files by putting a `RewriteCond
 * %{REQUEST_FILENAME} -f` in front of it, exactly as the shipped file now
 * reads. Source files under `user/pages` stay blocked by their own rule and
 * by the condition; a page route is not a file.
 *
 * Best-effort and non-destructive: the edit is made only when the exact stock
 * line is present and not already conditioned. A hand-edited or read-only
 * `.htaccess` is left alone.
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

            $rule = 'RewriteRule \.md$ error [F,NC]';
            $condition = 'RewriteCond %{REQUEST_FILENAME} -f';

            // Already conditioned (a fresh 2.1 install, or a site that did this by hand).
            if (preg_match('/^' . preg_quote($condition, '/') . '\s*\r?\n' . preg_quote($rule, '/') . '\s*$/m', $contents)) {
                return;
            }

            $pattern = '/^' . preg_quote($rule, '/') . '[ \t]*$/m';
            if (preg_match_all($pattern, $contents) !== 1) {
                return;
            }

            $updated = preg_replace(
                '/^# Block all direct access to \.md files:[ \t]*$/m',
                '# Block all direct access to .md files (a page route ending in .md is not a file, so Grav\'s Markdown output still works):',
                $contents,
                1
            ) ?? $contents;
            $updated = preg_replace($pattern, $condition . "\n" . $rule, $updated, 1);
            if (!is_string($updated) || $updated === $contents) {
                return;
            }

            file_put_contents($file, $updated);
        },
];
