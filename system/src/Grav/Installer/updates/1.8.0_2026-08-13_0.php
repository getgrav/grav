<?php

/**
 * Security self-heal: drop a `user/.htaccess` backstop on existing installs.
 *
 * mod_rewrite rules are not inherited by subdirectories. An .htaccess placed in
 * a theme or plugin folder that turns on RewriteEngine replaces the root
 * ruleset for that folder and everything beneath it, so a package shipping its
 * own .htaccess (to block a Makefile, say) silently exposes its .yaml, .md,
 * .twig and .php files. Adding `RewriteOptions Inherit` only half fixes it: the
 * root rules anchored on a path (`^(user)/...`) still cannot match, because a
 * per-directory rewrite strips the directory prefix before matching
 * (getgrav/grav#4236).
 *
 * Require / FilesMatch access control is merged into subdirectories rather than
 * replaced, so a `user/.htaccess` written that way keeps applying whatever a
 * subfolder does with rewriting. New installs ship the file; `user` and
 * `.htaccess` are both in the installer `$ignores` list, so this postflight is
 * how existing installs get it.
 *
 * Best-effort and non-destructive: a site that already has its own
 * `user/.htaccess`, or a read-only filesystem, is left alone.
 */

return [
    'preflight' => null,
    'postflight' =>
        function () {
            $dir = GRAV_ROOT . '/user';
            if (!is_dir($dir) || !is_writable($dir)) {
                return;
            }

            $file = $dir . '/.htaccess';
            if (is_file($file)) {
                return;
            }

            // Keep in sync with the shipped user/.htaccess and with the
            // `^(user)/(.*)\.(...)` rule in the site root .htaccess.
            $contents = <<<'HTACCESS'
            # Deny direct web access to code and data files anywhere under user/.
            # This is a defense-in-depth backup for the rules in the site root .htaccess.
            #
            # Why it exists: mod_rewrite rules are not inherited by subdirectories. Any
            # .htaccess in a theme or plugin folder that turns on RewriteEngine replaces the
            # root ruleset for that folder and everything beneath it, so a package shipping
            # its own .htaccess (to block a Makefile, say) can silently expose its .yaml,
            # .md, .twig and .php files (getgrav/grav#4236). Access control written with
            # Require / FilesMatch is merged into subdirectories rather than replaced, so
            # these rules keep applying no matter what a subfolder does with rewriting.
            #
            # Keep the extension list in sync with the `^(user)/(.*)\.(...)` rule in the
            # site root .htaccess.
            <FilesMatch "(?i)\.(txt|md|json|yaml|yml|php|php2|php3|php4|php5|phar|phtml|pl|py|cgi|twig|sh|bat)$">
                <IfModule mod_authz_core.c>
                    Require all denied
                </IfModule>
                <IfModule !mod_authz_core.c>
                    Order allow,deny
                    Deny from all
                </IfModule>
            </FilesMatch>

            # Dot-files: .env, git and CI metadata, editor state. Files *inside* a
            # dot-folder cannot be matched here, because <DirectoryMatch> is not allowed in
            # .htaccess; those still rely on the root rewrite rules.
            <FilesMatch "^\.">
                <IfModule mod_authz_core.c>
                    Require all denied
                </IfModule>
                <IfModule !mod_authz_core.c>
                    Order allow,deny
                    Deny from all
                </IfModule>
            </FilesMatch>

            HTACCESS;

            @file_put_contents($file, $contents);
        }
];
