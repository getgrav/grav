<?php

/**
 * Replace the `Require`-based user/ .htaccess files that earlier releases wrote.
 *
 * `Require` is an AuthConfig-class directive. Grav's root .htaccess has only ever
 * needed FileInfo, so the .htaccess files 2.0.19 added under user/ quietly raised
 * Grav's server requirement: on a host granting `AllowOverride FileInfo` Apache
 * logs "Require not allowed here" and returns 500 for every request under that
 * folder (getgrav/grav#4309, #4311). 2.1.4 rebuilt user/.htaccess on mod_rewrite,
 * which is FileInfo-class, but only for NEW installs — user/ is in the installer
 * `$ignores` list, so an upgrade never replaces any of these files, and the
 * self-heal postflight that plants user/.htaccess skips a file already there.
 *
 * 2.1.4's user/.htaccess also restated the root's folder denials without their two
 * exceptions, so avatars under user/accounts and asset uploads under user/data
 * came back 403 (#4311).
 *
 * This migration therefore replaces each file whose content is byte-for-byte one
 * Grav shipped or wrote itself. A file edited by the site is left alone: it is
 * not ours to overwrite, and the admin cannot tell a deliberate change from a
 * stale one. Sites that hand-edited a broken copy keep it, which is the same
 * position they are in today.
 */

use Grav\Installer\InstallException;

return [
    'preflight' => null,
    'postflight' =>
        function () {
            try {
                // sha256 of every copy Grav has shipped or written for each file,
                // normalised (CRLF folded, trailing whitespace trimmed, one final
                // newline) so a checkout or editor that changed line endings still
                // matches. Add the current hash here whenever one of these files
                // changes, so the next release can heal this one in turn.
                $known = [
                    'user/.htaccess' => [
                        // 2.0.19 - 2.1.3, and what the 2026-08-13 postflight wrote
                        'ecdc0b76805752c8be8db497c0db4d3b47dedb580186ccfcf98a2f2d7bce3c6b',
                        // 2.1.4: mod_rewrite, but missing the root's two exceptions
                        '63a5dbc3792c494f3feb97b2451006a37f025a8549f9cf403124fde842e60cb4',
                    ],
                    'user/accounts/.htaccess' => [
                        'd8b0c6828fc2e83bf0ce0a9d8ef61e0ca11ffadd6a282cd649046ea634281326',
                        'ca441f7a20a8497c3330e42e8cad1dbc802163d75eb4086bb7d48a606393de4d',
                    ],
                    'user/config/.htaccess' => [
                        'd8b0c6828fc2e83bf0ce0a9d8ef61e0ca11ffadd6a282cd649046ea634281326',
                    ],
                    'user/data/.htaccess' => [
                        'd8b0c6828fc2e83bf0ce0a9d8ef61e0ca11ffadd6a282cd649046ea634281326',
                        '736ddf9ca6de43c65d8c5632b4520d8af140ac493c5bb07c4f749980e41fb31d',
                    ],
                ];

                // Byte-for-byte the shipped files. Keep all four in sync with them
                // and with the `^(user)/...` rules in the site root .htaccess.
                $replacements = [
                    'user/.htaccess' => <<<'HTACCESS'
                        # Defense-in-depth backup for the `^(user)/...` rules in the site root .htaccess.
                        # Keep the folder and extension lists in sync with them.
                        #
                        # InheritDownBefore pushes these rules down into every subdirectory and runs them
                        # first, so a plugin or theme shipping its own RewriteEngine cannot switch them
                        # off (getgrav/grav#4236). Everything here is deliberately FileInfo-class:
                        # `Require` is AuthConfig-class and returns 500 for the whole user/ tree on hosts
                        # that grant only `AllowOverride FileInfo` (getgrav/grav#4309).
                        <IfModule mod_rewrite.c>
                            RewriteEngine On
                            RewriteOptions InheritDownBefore

                            RewriteRule ^(config|env)/ - [F,NC]

                            # The same two exceptions the site root makes: avatar images under
                            # user/accounts (`avatars/<file>` for flatfile accounts, `<username>/<file>`
                            # for Flex folder storage) and public asset uploads under user/data are
                            # served; everything else in both folders is denied. The conditions read
                            # REQUEST_URI, which is the whole original path, so they are written exactly
                            # as they are in the root — a per-directory rule only sees the path below
                            # this folder. Keep all three copies in sync.
                            RewriteCond %{REQUEST_URI} !/user/accounts/[^/]+/[^/]+\.(jpe?g|png|gif|webp|avif|bmp|ico)$ [NC]
                            RewriteRule ^accounts/ - [F,NC]
                            RewriteCond %{REQUEST_URI} !\.(jpe?g|png|gif|webp|avif|bmp|ico|mp4|webm|ogg|ogv|mov|mp3|wav|m4a|flac|pdf|woff2|woff|ttf|otf|eot|css|js)$ [NC]
                            RewriteRule ^data/ - [F,NC]
                            RewriteRule (?i)\.(txt|md|json|yaml|yml|php|php2|php3|php4|php5|phar|phtml|pl|py|cgi|twig|sh|bat)$ - [F]

                            # `(^|/)`, not `^`: paths are relative to whichever folder the rules run in.
                            RewriteRule (?i)(^|/)\. - [F]

                            # The site root's front-controller rule no longer reaches us. `../index.php`,
                            # not `/index.php`, or a subdirectory install lands on the wrong one.
                            RewriteCond %{REQUEST_FILENAME} !-f
                            RewriteCond %{REQUEST_FILENAME} !-d
                            RewriteRule . ../index.php [L]
                        </IfModule>

                        HTACCESS,
                    'user/accounts/.htaccess' => <<<'HTACCESS'
                        # Deny direct web access to this folder, except avatar images (account://avatars)
                        # that Grav serves over HTTP. Account data (.yaml password hashes), databases,
                        # keys and tokens stay blocked; SVG is excluded as a stored-XSS vector.
                        # Defense-in-depth backup for the rules in the site root .htaccess.
                        #
                        # mod_rewrite, not `Require`: `Require` is AuthConfig-class and returns 500 for
                        # this whole folder on a host that grants only `AllowOverride FileInfo`, which
                        # is all the root .htaccess has ever needed (getgrav/grav#4309, #4311).
                        <IfModule mod_rewrite.c>
                            RewriteEngine On
                            RewriteOptions InheritDownBefore

                            # REQUEST_URI is the whole original path; the rule pattern only sees the
                            # path below this folder, so the exception is written as a condition.
                            RewriteCond %{REQUEST_URI} !/user/accounts/[^/]+/[^/]+\.(jpe?g|png|gif|webp|avif|bmp|ico)$ [NC]
                            RewriteRule .* - [F]
                        </IfModule>

                        HTACCESS,
                    'user/config/.htaccess' => <<<'HTACCESS'
                        # Deny all direct web access to this folder and everything beneath it.
                        # Grav reads these files server-side; they must never be served over HTTP.
                        # This is a defense-in-depth backup for the rules in the site root .htaccess.
                        #
                        # mod_rewrite, not `Require`: `Require` is AuthConfig-class and returns 500 for
                        # this whole folder on a host that grants only `AllowOverride FileInfo`, which
                        # is all the root .htaccess has ever needed (getgrav/grav#4309, #4311).
                        <IfModule mod_rewrite.c>
                            RewriteEngine On
                            RewriteOptions InheritDownBefore

                            RewriteRule .* - [F]
                        </IfModule>

                        HTACCESS,
                    'user/data/.htaccess' => <<<'HTACCESS'
                        # Deny direct web access to this folder, except the public asset uploads
                        # (e.g. Flex Object images) that Grav has always served from here.
                        # Data files (.yaml/.json/.md), databases, keys and tokens stay blocked.
                        # SVG stays blocked as a stored-XSS vector; .css/.js are served per project
                        # policy despite the same risk on this user-writable folder.
                        # Defense-in-depth backup for the rules in the site root .htaccess.
                        #
                        # mod_rewrite, not `Require`: `Require` is AuthConfig-class and returns 500 for
                        # this whole folder on a host that grants only `AllowOverride FileInfo`, which
                        # is all the root .htaccess has ever needed (getgrav/grav#4309, #4311).
                        <IfModule mod_rewrite.c>
                            RewriteEngine On
                            RewriteOptions InheritDownBefore

                            # REQUEST_URI is the whole original path; the rule pattern only sees the
                            # path below this folder, so the exception is written as a condition.
                            RewriteCond %{REQUEST_URI} !\.(jpe?g|png|gif|webp|avif|bmp|ico|mp4|webm|ogg|ogv|mov|mp3|wav|m4a|flac|pdf|woff2|woff|ttf|otf|eot|css|js)$ [NC]
                            RewriteRule .* - [F]
                        </IfModule>

                        HTACCESS,
                ];

                $healed = [];
                foreach ($replacements as $relative => $contents) {
                    $file = GRAV_ROOT . '/' . $relative;
                    if (!is_file($file) || !is_writable($file)) {
                        // Missing: the folder may not exist on this site, and
                        // user/.htaccess is planted by the 2026-08-13 postflight.
                        continue;
                    }

                    $current = @file_get_contents($file);
                    if ($current === false) {
                        continue;
                    }

                    $hash = hash('sha256', rtrim(str_replace("\r\n", "\n", $current)) . "\n");
                    if (!in_array($hash, $known[$relative], true)) {
                        continue; // edited by the site — leave it alone
                    }

                    if (@file_put_contents($file, $contents) !== false) {
                        $healed[] = $relative;
                    }
                }

                if ($healed) {
                    error_log(
                        'Grav upgrade: rewrote ' . implode(', ', $healed) . ' on mod_rewrite. '
                        . 'The previous copies used `Require`, which returns 500 for those '
                        . 'folders on a host granting only `AllowOverride FileInfo`, and '
                        . 'denied avatar and data-folder media the site root allows.'
                    );
                }
            } catch (\Exception $e) {
                throw new InstallException('Could not update the .htaccess files under user/', $e);
            }
        }
];
