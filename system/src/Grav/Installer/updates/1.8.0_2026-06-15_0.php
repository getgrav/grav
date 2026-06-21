<?php

/**
 * Re-heal of the 2026-06-09 security postflight.
 *
 * That update dropped a blanket deny-all `.htaccess` over `user/data` and added
 * a matching `RewriteRule ^(user)/(accounts|config|data|env)/(.*) error [F]` to
 * the site root. Flex Objects (and other plugins) store uploaded media under
 * `user/data` and serve it directly over HTTP, so the blanket block returned a
 * 403 for every Flex Object image (getgrav/grav#4129).
 *
 * Installs that already applied the 2026-06-09 update have the over-broad rules
 * on disk, so editing that file alone would not reach them. This update rewrites
 * the `user/data` block — in both the root `.htaccess` and the per-folder backup
 * — to deny everything *except* a fixed set of public media extensions, while
 * `accounts`/`config`/`env` stay full deny-all. It is idempotent: an install
 * already on the media-aware form is left untouched.
 */

return [
    'preflight' => null,
    'postflight' =>
        function () {
            // Public media types that may be served directly from user/data.
            // Keep in sync with the root .htaccess rule and the 2026-06-09 update.
            $mediaExt = 'jpe?g|png|gif|webp|avif|bmp|ico|mp4|webm|ogg|ogv|mov|mp3|wav|m4a|flac|pdf';

            // ---- Per-folder backup: upgrade user/data/.htaccess in place. ----
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

            $dataDir = GRAV_ROOT . '/user/data';
            $dataHtaccess = $dataDir . '/.htaccess';
            if (is_dir($dataDir) && is_writable($dataDir) && is_file($dataHtaccess)) {
                $existing = (string) @file_get_contents($dataHtaccess);
                // Only touch the deny-all file this feature wrote: it denies all and
                // has no media carve-out yet. Leave hand-rolled files alone.
                if (strpos($existing, 'FilesMatch') === false
                    && strpos($existing, 'Require all denied') !== false) {
                    @file_put_contents($dataHtaccess, $denyExceptMedia);
                }
            }

            // ---- Site root: swap the blanket folder rule for the media-aware one. ----
            $root = GRAV_ROOT . '/.htaccess';
            if (!is_file($root) || !is_writable($root)) {
                return;
            }

            $contents = file_get_contents($root);
            if ($contents === false) {
                return;
            }

            // Already on the media-aware form (or never patched) - nothing to do.
            if (strpos($contents, 'RewriteRule ^(user)/(accounts|config|data|env)/(.*) error [F]') === false) {
                return;
            }

            $newBlock = "# Block all direct access to these sensitive user folders, whatever the file type\n"
                . "RewriteRule ^(user)/(accounts|config|env)/(.*) error [F]\n"
                . "# Block user/data too, but allow public media uploads (e.g. Flex Object images)\n"
                . "# to be served directly. SVG is intentionally excluded as a stored-XSS vector.\n"
                . "RewriteCond %{REQUEST_URI} !\\.($mediaExt)$ [NC]\n"
                . "RewriteRule ^(user)/data/(.*) error [F]\n";

            // Prefer replacing the comment+rule pair the 2026-06-09 update wrote, so we
            // don't leave its now-stale comment behind; fall back to the rule line alone.
            $oldPair = "# Block all direct access to these sensitive user folders, whatever the file type\n"
                . "RewriteRule ^(user)/(accounts|config|data|env)/(.*) error [F]\n";

            if (strpos($contents, $oldPair) !== false) {
                $patched = str_replace($oldPair, $newBlock, $contents);
            } else {
                $patched = str_replace(
                    "RewriteRule ^(user)/(accounts|config|data|env)/(.*) error [F]\n",
                    $newBlock,
                    $contents
                );
            }

            if ($patched !== $contents) {
                @file_put_contents($root, $patched);
            }
        }
];
