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
 */

return [
    'preflight' => null,
    'postflight' =>
        function () {
            $denyHtaccess = <<<HTACCESS
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

            // Drop a backup deny-all .htaccess into each sensitive folder that exists.
            foreach (['accounts', 'config', 'data', 'env'] as $folder) {
                $dir = GRAV_ROOT . '/user/' . $folder;
                if (!is_dir($dir) || !is_writable($dir)) {
                    continue;
                }
                $file = $dir . '/.htaccess';
                if (!is_file($file)) {
                    @file_put_contents($file, $denyHtaccess);
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
            if (strpos($contents, '^(user)/(accounts|config|data|env)/') !== false) {
                return;
            }

            $rule = "# Block all direct access to these sensitive user folders, whatever the file type\n"
                . "RewriteRule ^(user)/(accounts|config|data|env)/(.*) error [F]\n";

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
