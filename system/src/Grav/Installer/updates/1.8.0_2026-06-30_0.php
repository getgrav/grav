<?php

/**
 * Security postflight: heal the user/accounts block so profile avatars are served
 * again (getgrav/grav#4185).
 *
 * The 2026-06-09 hardening blocked user/accounts outright. User profile avatars
 * are stored under user/accounts/avatars (the account://avatars stream) and
 * served directly over HTTP, so every avatar started returning a 403: the image
 * vanished from the admin sidebar and account page even though the upload saved.
 *
 * Grav never overwrites a user's root .htaccess on upgrade, so existing installs
 * keep the blanket user/accounts deny. This postflight rewrites the root rule to
 * deny user/accounts except avatar images, and converts the per-folder backup
 * user/accounts/.htaccess from a blanket deny-all into the same image carve-out.
 * Account data (.yaml password hashes), databases, keys and tokens stay blocked,
 * and SVG stays blocked as a stored-XSS vector. Only raster avatar images are
 * opened. Both rewrites are idempotent.
 */

// Avatar image extensions that may be served from user/accounts/avatars. SVG is
// intentionally excluded as a stored-XSS vector.
$avatarExt = 'jpe?g|png|gif|webp|avif|bmp|ico';

// The root .htaccess block that replaces the blanket user/accounts deny.
$rootBlock = <<<HTACCESS
RewriteRule ^(user)/(config|env)/(.*) error [F,NC]
# Block user/accounts too, but allow avatar images (account://avatars) to be
# served directly. Account data (.yaml password hashes) stays blocked; SVG is
# excluded as a stored-XSS vector.
RewriteCond %{REQUEST_URI} !/user/accounts/avatars/[^/]+\.($avatarExt)$ [NC]
RewriteRule ^(user)/accounts/(.*) error [F,NC]
HTACCESS;

// The per-folder backup written when an existing user/accounts/.htaccess is
// still the original blanket deny-all.
$accountsCarveOut = <<<HTACCESS
# Deny direct web access to this folder, except avatar images (account://avatars)
# that Grav serves over HTTP. Account data (.yaml password hashes), databases,
# keys and tokens stay blocked; SVG is excluded as a stored-XSS vector.
# Defense-in-depth backup for the rules in the site root .htaccess.
<IfModule mod_authz_core.c>
    Require all denied
</IfModule>
<IfModule !mod_authz_core.c>
    Order allow,deny
    Deny from all
</IfModule>
<FilesMatch "(?i)\.($avatarExt)$">
    <IfModule mod_authz_core.c>
        Require all granted
    </IfModule>
    <IfModule !mod_authz_core.c>
        Order allow,deny
        Allow from all
    </IfModule>
</FilesMatch>

HTACCESS;

return [
    'preflight' => null,
    'postflight' =>
        function () use ($rootBlock, $accountsCarveOut) {
            // ---- Site root: open user/accounts to avatar images. ----
            $root = GRAV_ROOT . '/.htaccess';
            if (is_file($root) && is_writable($root)) {
                $contents = file_get_contents($root);
                // Already healed (carve-out present) - nothing to do. Idempotent.
                if ($contents !== false && strpos($contents, '/user/accounts/avatars/') === false) {
                    // The prior security updates leave the rule on the [F,NC] form;
                    // tolerate a stock [F] too. Once replaced, neither literal
                    // remains, so this is idempotent.
                    $patched = str_replace(
                        [
                            'RewriteRule ^(user)/(accounts|config|env)/(.*) error [F,NC]',
                            'RewriteRule ^(user)/(accounts|config|env)/(.*) error [F]',
                        ],
                        $rootBlock,
                        $contents
                    );
                    if ($patched !== $contents) {
                        @file_put_contents($root, $patched);
                    }
                }
            }

            // ---- Per-folder backup: convert the blanket deny-all to the carve-out. ----
            $accountsHtaccess = GRAV_ROOT . '/user/accounts/.htaccess';
            if (is_file($accountsHtaccess) && is_writable($accountsHtaccess)) {
                $contents = file_get_contents($accountsHtaccess);
                if ($contents !== false
                    && strpos($contents, 'FilesMatch') === false
                    && strpos($contents, 'Require all denied') !== false) {
                    @file_put_contents($accountsHtaccess, $accountsCarveOut);
                }
            }
        }
];
