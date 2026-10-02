<?php

/**
 * Let a multisite's assets through an upgraded site's `.htaccess` files.
 *
 * Earlier releases refused everything under `user/env/`, which also blocked the
 * themes, plugins and media of a multisite that keeps each site in
 * `user/env/<host>/`. 2.2.4 protects `config/`, `accounts/` and `data/` inside
 * each env folder the same way it protects them in `user/`, and lets the rest
 * through. Since `.htaccess` is never replaced by an upgrade, this postflight
 * carries the new rules to existing sites, in two places:
 *
 * - The root `.htaccess`. All four lines are swapped together or not at all:
 *   loosening the `env` rule without the matching `accounts` and `data` rules
 *   would open those folders. Nothing is written unless each old line is present
 *   exactly once and unchanged.
 * - `user/env/.htaccess`, the deny-all backup the 1.8.0 postflight dropped into
 *   an existing `user/env` (and 2.1.6 to 2.2.1 kept up to date). Grav has never
 *   shipped that folder, so this only replaces a copy Grav wrote, byte for byte,
 *   and leaves a file the site edited alone.
 *
 * Read-only files are left alone. Plain PHP only, because the classes loaded
 * during an upgrade are the old ones.
 */

return [
    'preflight' => null,
    'postflight' =>
        function (?string $file = null, ?string $envFile = null) {
            $file ??= GRAV_ROOT . '/.htaccess';
            $envFile ??= GRAV_ROOT . '/user/env/.htaccess';

            $contents = is_file($file) && is_readable($file) && is_writable($file) ? file_get_contents($file) : false;
            if (is_string($contents)) {
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

                $all = true;
                foreach ($swaps as $old => $new) {
                    if (preg_match_all('/^' . preg_quote($old, '/') . '(?=\r?$)/m', $contents) !== 1) {
                        $all = false;
                        break;
                    }
                }

                if ($all) {
                    foreach ($swaps as $old => $new) {
                        $pattern = '/^' . preg_quote($old, '/') . '(?=\r?$)/m';
                        $contents = preg_replace($pattern, str_replace(['\\', '$'], ['\\\\', '\\$'], $new), $contents, 1);
                    }
                    file_put_contents($file, $contents);
                }
            }

            $current = is_file($envFile) && is_writable($envFile) ? @file_get_contents($envFile) : false;
            if (is_string($current)) {
                // sha256 of every deny-all copy Grav has written, normalised (CRLF
                // folded, trailing whitespace trimmed, one final newline): the
                // 2.2.1 mod_alias copy, the 2.2.0 one and the 2.1.5 - 2.1.12 one.
                $known = [
                    '1a3e8eab2f92be2425ad976bf98f7f0385ceb5398f73ae7ce67e3fb24981ee60',
                    '97f127d864fed19e9306454310d90133b307a28de1d9d185e7d4753b9b5822b5',
                    '65186f850858b59dd20fa3174876375bedc3ecaf95ab31ea1dea17dee6396c1c',
                ];
                $hash = hash('sha256', rtrim(str_replace("\r\n", "\n", $current)) . "\n");
                if (in_array($hash, $known, true)) {
                    @file_put_contents($envFile, <<<'HTACCESS'
                # Deny direct web access to the sensitive folders inside each environment:
                # config/, accounts/ (except avatar images) and data/ (except public asset
                # uploads), matching the rules for the same folders in user/. Everything else
                # under an environment, such as a multisite's themes, plugins and page media,
                # is served normally.
                # Defense-in-depth backup for the rules in the site root .htaccess.
                #
                # mod_alias, not `Require` or mod_rewrite. `Require` is AuthConfig-class and
                # returns 500 for this whole folder on a host that grants only `AllowOverride
                # FileInfo` (getgrav/grav#4309, #4311). mod_alias is FileInfo-class, leaves the
                # root's rewrite rules in force here, and merges into subfolders, so a subfolder
                # with its own RewriteEngine cannot switch it off.
                #
                # Matches the whole URL path, so the exceptions are written exactly as the
                # root's. Keep the three in sync.
                <IfModule mod_alias.c>
                    RedirectMatch 403 (?i)/user/env/[^/]+/config/
                    RedirectMatch 403 (?i)^(?!.*/user/env/[^/]+/accounts/[^/]+/[^/]+\.(jpe?g|png|gif|webp|avif|bmp|ico)$).*/user/env/[^/]+/accounts/
                    RedirectMatch 403 (?i)^(?!.*\.(jpe?g|png|gif|webp|avif|bmp|ico|mp4|webm|ogg|ogv|mov|mp3|wav|m4a|flac|pdf|woff2|woff|ttf|otf|eot|css|js)$).*/user/env/[^/]+/data/
                </IfModule>

                HTACCESS);
                }
            }
        },
];
