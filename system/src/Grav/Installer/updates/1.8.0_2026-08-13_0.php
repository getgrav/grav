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
 * The file written here uses mod_alias, whose rules merge into subdirectories
 * instead of being replaced, so a package's own .htaccess cannot switch them off.
 * It holds no rewrite rules, so the root's keep covering user/. Everything in it
 * is FileInfo-class: `Require` is AuthConfig-class and returns 500 for the whole
 * user/ tree on a host granting only `AllowOverride FileInfo` (getgrav/grav#4309).
 * Until 2.2.1 this wrote a mod_rewrite version; the 2.2.1 migration replaces it.
 * New installs ship the file; `user` and `.htaccess` are both in the installer
 * `$ignores` list, so this postflight is how existing installs get it.
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

            // Byte-for-byte the shipped user/.htaccess. Keep the two in sync,
            // along with the `^(user)/...` rules in the site root .htaccess.
            $contents = <<<'HTACCESS'
            # Defense-in-depth backup for the `^(user)/...` rules in the site root .htaccess.
            #
            # mod_alias only, never mod_rewrite. An .htaccess that turns RewriteEngine on
            # replaces the root's rewrite rules for its folder and everything beneath it, so
            # a rewrite ruleset here would have to restate the root's and route missing files
            # back to index.php itself, and every host quirk in that path would break the
            # whole user/ tree (getgrav/grav#4309, getgrav/grav-plugin-admin2#179). With no
            # RewriteEngine here, the root rules keep covering user/ exactly as they always
            # have. mod_alias sits outside the rewrite pipeline and its rules merge into
            # subdirectories instead of being replaced, so a plugin or theme that ships its
            # own RewriteEngine cannot switch these off (getgrav/grav#4236). It is
            # FileInfo-class like mod_rewrite, so it needs nothing more from AllowOverride
            # than the root .htaccess already does.
            #
            # Deliberately unanchored. These rules only run for requests that resolve into
            # this folder, which is what scopes them, and matching the tail rather than a
            # leading /user/ keeps them correct for a Grav installed in a subdirectory.
            # Keep the extension list in sync with the `^(user)/(.*)\.(...)` rule in the
            # site root .htaccess.
            <IfModule mod_alias.c>
                RedirectMatch 403 (?i)\.(txt|md|json|yaml|yml|php|php2|php3|php4|php5|phar|phtml|pl|py|cgi|twig|sh|bat)$
                RedirectMatch 403 (?i)(^|/)\.
            </IfModule>

            HTACCESS;

            @file_put_contents($file, $contents);
        }
];
