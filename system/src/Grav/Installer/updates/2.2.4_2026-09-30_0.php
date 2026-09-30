<?php

/**
 * Let a multisite's assets through an upgraded site's root `.htaccess`.
 *
 * Earlier releases refused everything under `user/env/`, which also blocked the
 * themes, plugins and media of a multisite that keeps each site in
 * `user/env/<host>/`. 2.2.4 protects `config/`, `accounts/` and `data/` inside
 * each env folder the same way it protects them in `user/`, and lets the rest
 * through. Since `.htaccess` is never replaced by an upgrade, this postflight
 * carries the new rules to existing sites.
 *
 * All four lines are swapped together or not at all: loosening the `env` rule
 * without the matching `accounts` and `data` rules would open those folders.
 * Nothing is written unless each old line is present exactly once, unchanged, and
 * a read-only `.htaccess` is left alone. Plain PHP only, because the classes
 * loaded during an upgrade are the old ones.
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
            if (!is_string($contents)) {
                return;
            }

            $swaps = [
                'RewriteRule ^(user)/(config|env)/(.*) error [F,NC]'
                    => 'RewriteRule ^(user)/(env/[^/]+/)?config/(.*) error [F,NC]',
                'RewriteCond %{REQUEST_URI} !/user/accounts/[^/]+/[^/]+\.(jpe?g|png|gif|webp|avif|bmp|ico)$ [NC]'
                    => 'RewriteCond %{REQUEST_URI} !/user/(env/[^/]+/)?accounts/[^/]+/[^/]+\.(jpe?g|png|gif|webp|avif|bmp|ico)$ [NC]',
                'RewriteRule ^(user)/accounts/(.*) error [F,NC]'
                    => 'RewriteRule ^(user)/(env/[^/]+/)?accounts/(.*) error [F,NC]',
                'RewriteRule ^(user)/data/(.*) error [F,NC]'
                    => 'RewriteRule ^(user)/(env/[^/]+/)?data/(.*) error [F,NC]',
            ];

            foreach ($swaps as $old => $new) {
                $pattern = '/^' . preg_quote($old, '/') . '(?=\r?$)/m';
                if (preg_match_all($pattern, $contents) !== 1) {
                    return;
                }
            }

            foreach ($swaps as $old => $new) {
                $pattern = '/^' . preg_quote($old, '/') . '(?=\r?$)/m';
                $contents = preg_replace($pattern, str_replace(['\\', '$'], ['\\\\', '\\$'], $new), $contents, 1);
            }

            file_put_contents($file, $contents);
        },
];
