<?php

/**
 * Security self-heal: make sure direct web access to the sensitive `user/`
 * folders (accounts, config, data, env) is blocked.
 *
 * Grav never overwrites the site root `.htaccess` on upgrade (it is in the
 * installer `$ignores` list), so older installs keep a root file that only
 * blocks a fixed list of file extensions under `user/`. Files stored under
 * `user/data` with an unlisted extension (certificates, tokens, databases,
 * logs, ...) could therefore be downloaded directly.
 *
 * This postflight patches a stock Grav root `.htaccess` to add the folder
 * block, and drops a backup deny-all `.htaccess` inside each sensitive folder
 * so Apache installs stay protected even when the root file has been
 * customised. It is best-effort: a read-only filesystem or a heavily
 * customised root file is left untouched and reported by the Admin security
 * check instead of aborting the upgrade.
 *
 * `user/data` is treated differently from the other folders: Flex Objects (and
 * other plugins) store uploaded media there and have always served it directly
 * over HTTP, so a blanket deny-all breaks those images (getgrav/grav#4129).
 * The data block therefore denies everything *except* a fixed set of public
 * media extensions. SVG is intentionally excluded as a stored-XSS vector, and
 * data files (.yaml/.json/.md), databases, keys and tokens stay blocked.
 */

return [
    'preflight' => null,
    'postflight' =>
        function () {
            // Public media types that may be served directly from user/data.
            // Keep in sync with the root .htaccess rule and the 2026-06-15 heal.
            $mediaExt = 'jpe?g|png|gif|webp|avif|bmp|ico|mp4|webm|ogg|ogv|mov|mp3|wav|m4a|flac|pdf';

            $denyAll = <<<HTACCESS
            # Deny all direct web access to this folder and everything beneath it.
            # Grav reads these files server-side; they must never be served over HTTP.
            # This is a defense-in-depth backup for the rules in the site root .htaccess.
            <IfModule mod_authz_core.c>
                Require all denied
            </IfModule>
            <IfModule !mod_authz_core.c>
                Order allow,deny
                Deny from all
            </IfModule>

            HTACCESS;

            // Same deny-all, but re-grant a fixed set of public media extensions so
            // Flex Object images and similar uploads keep loading (getgrav/grav#4129).
            $denyExceptMedia = <<<HTACCESS
            # Deny direct web access to this folder, except the public media uploads
            # (e.g. Flex Object images) that Grav has always served from here.
            # Data files (.yaml/.json/.md), databases, keys and tokens stay blocked;
            # SVG is intentionally excluded as a stored-XSS vector.
            <IfModule mod_authz_core.c>
                Require all denied
            </IfModule>
            <IfModule !mod_authz_core.c>
                Order allow,deny
                Deny from all
            </IfModule>
            <FilesMatch "\.($mediaExt)$">
                <IfModule mod_authz_core.c>
                    Require all granted
                </IfModule>
                <IfModule !mod_authz_core.c>
                    Order allow,deny
                    Allow from all
                </IfModule>
            </FilesMatch>

            HTACCESS;

            // Drop a backup .htaccess into each sensitive folder that exists.
            foreach (['accounts', 'config', 'data', 'env'] as $folder) {
                $dir = GRAV_ROOT . '/user/' . $folder;
                if (!is_dir($dir) || !is_writable($dir)) {
                    continue;
                }
                $file = $dir . '/.htaccess';
                if (!is_file($file)) {
                    @file_put_contents($file, $folder === 'data' ? $denyExceptMedia : $denyAll);
                }
            }

            // Patch the site root .htaccess to block the sensitive folders outright.
            $root = GRAV_ROOT . '/.htaccess';
            if (!is_file($root) || !is_writable($root)) {
                return;
            }

            $contents = file_get_contents($root);
            if ($contents === false) {
                return;
            }

            // Already patched (or a non-Grav file that happens to mention it) - nothing to do.
            if (strpos($contents, '^(user)/data/') !== false
                || strpos($contents, '^(user)/(accounts|config|data|env)/') !== false) {
                return;
            }

            $rule = "# Block all direct access to these sensitive user folders, whatever the file type\n"
                . "RewriteRule ^(user)/(accounts|config|env)/(.*) error [F]\n"
                . "# Block user/data too, but allow public media uploads (e.g. Flex Object images)\n"
                . "# to be served directly. SVG is intentionally excluded as a stored-XSS vector.\n"
                . "RewriteCond %{REQUEST_URI} !\\.($mediaExt)$ [NC]\n"
                . "RewriteRule ^(user)/data/(.*) error [F]\n";

            // Insert right after the stock "block these folders" rule so it sits inside
            // the existing <IfModule mod_rewrite.c> ... Security block.
            $patched = preg_replace(
                '/^(RewriteRule \^\(\\\\\.git\|cache\|bin\|logs\|backup\|webserver-configs\|tests\)\/\(\.\*\) error \[F\]\n)/m',
                '$1' . $rule,
                $contents,
                1,
                $count
            );

            if ($count > 0 && $patched !== null && $patched !== $contents) {
                @file_put_contents($root, $patched);
            }
        }
];
