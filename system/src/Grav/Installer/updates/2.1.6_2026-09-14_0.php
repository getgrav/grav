<?php

/**
 * Close the two gaps 2.1.5 left in the .htaccess files under user/.
 *
 * 2.1.5 rebuilt those files on mod_rewrite, because `Require` is AuthConfig-class
 * and returns 500 for a whole folder on a host granting only `AllowOverride
 * FileInfo` (getgrav/grav#4309, #4311). Two things it did not cover:
 *
 * `user/env/.htaccess` was not in the set it healed. Grav has never shipped a
 * `user/env` folder, so that file exists only where the 2026-06-09 postflight
 * created it on a site that had one, which is why it was missed: it is in no
 * release archive and no checkout. Nothing is served out of user/env, so no site
 * broke, but it answers 500 where a 403 belongs.
 *
 * `user/.htaccess` gains a mod_alias backstop. Rules inherited with
 * `RewriteOptions InheritDownBefore` run first, which is what stops a package's
 * own .htaccess from replacing them (getgrav/grav#4236) - but a folder that
 * declares `RewriteOptions Inherit` and ends its own rules with an unconditional
 * [L] flips that order and stops before they run. The `Require` version 2.0.19
 * shipped resisted that, so the move to mod_rewrite narrowed the protection.
 * mod_alias is FileInfo-class too, sits outside the rewrite pipeline, and merges
 * into subdirectories, so no [L] reaches past it.
 *
 * Same rule as 2.1.5: replace a file only when its content is byte-for-byte what
 * Grav shipped or wrote. A file the site edited is left alone.
 */

use Grav\Installer\InstallException;

return [
    'preflight' => null,
    'postflight' =>
        function () {
            try {
                // sha256 of every copy Grav has shipped or written for each file,
                // normalised (CRLF folded, trailing whitespace trimmed, one final
                // newline). Add the current hash here whenever one of these files
                // changes, so the next release can heal this one in turn.
                $known = [
                    'user/.htaccess' => [
                        // 2.0.19 - 2.1.3, and what the 2026-08-13 postflight wrote
                        'ecdc0b76805752c8be8db497c0db4d3b47dedb580186ccfcf98a2f2d7bce3c6b',
                        // 2.1.4: mod_rewrite, but missing the root's two exceptions
                        '63a5dbc3792c494f3feb97b2451006a37f025a8549f9cf403124fde842e60cb4',
                        // 2.1.5: exceptions restored, no mod_alias backstop yet
                        '6e4a76c8f587a4782bcf04480be4a147159a1951cfba3650e50bc39fa38a884c',
                    ],
                    'user/env/.htaccess' => [
                        // the deny-all the 2026-06-09 postflight writes
                        'd8b0c6828fc2e83bf0ce0a9d8ef61e0ca11ffadd6a282cd649046ea634281326',
                    ],
                    'user/data/.htaccess' => [
                        // The 2026-06-26 postflight widened the 06-09/06-15 carve-out in
                        // place with str_replace, so these two copies exist only on disk -
                        // in no release archive and no checkout. A hash sweep over git
                        // history cannot see them, which is why 2.1.5 missed both and left
                        // these sites returning 500 for the whole folder on a host that
                        // grants only AllowOverride FileInfo (getgrav/grav#4309, #4311).
                        'c098e2771a37bb2f54b5d2cdb83ef8210ce95f764d266315ee5207ba16ac110d',
                        // the same carve-out before 2026-06-26 widened it
                        'cb2da60edc62983310e9829d3d93b11dbb23b493c09d481dc4e9d1472192bca1',
                    ],
                ];

                // Byte-for-byte the shipped files. user/env is the exception: Grav
                // ships no such folder, so that copy lives here only. Keep both in
                // sync with user/.htaccess, user/config/.htaccess and the
                // `^(user)/...` rules in the site root .htaccess.
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

                        # Backstop for the rules above. A subfolder that turns RewriteEngine on with
                        # `RewriteOptions Inherit` and then ends its own rules with an unconditional [L]
                        # never reaches them, because the inherited rules run last and [L] stops first.
                        # mod_alias is FileInfo-class like mod_rewrite, but it sits outside the rewrite
                        # pipeline and its rules merge into subdirectories instead of replacing what is
                        # there, so no [L] can reach past this. It only covers the file types, which is
                        # the case that matters: third-party packages, the ones that ship an .htaccess
                        # of their own (getgrav/grav#4236), install into user/plugins and user/themes.
                        #
                        # Deliberately unanchored. These rules only run for requests that resolve into
                        # this folder, which is what scopes them, and matching the tail rather than a
                        # leading /user/ keeps them correct for a Grav installed in a subdirectory.
                        # Keep the extension list in sync with the rule above.
                        <IfModule mod_alias.c>
                            RedirectMatch 403 (?i)\.(txt|md|json|yaml|yml|php|php2|php3|php4|php5|phar|phtml|pl|py|cgi|twig|sh|bat)$
                            RedirectMatch 403 (?i)(^|/)\.
                        </IfModule>

                        HTACCESS,
                    'user/env/.htaccess' => <<<'HTACCESS'
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
                        // Missing: most sites have no user/env folder at all, and
                        // user/.htaccess is planted by the 2026-08-13 postflight.
                        continue;
                    }

                    $current = @file_get_contents($file);
                    if ($current === false) {
                        continue;
                    }

                    $hash = hash('sha256', rtrim(str_replace("\r\n", "\n", $current)) . "\n");
                    if (!in_array($hash, $known[$relative], true)) {
                        continue; // edited by the site - leave it alone
                    }

                    if (@file_put_contents($file, $contents) !== false) {
                        $healed[] = $relative;
                    }
                }

                if ($healed) {
                    error_log('Grav upgrade: updated ' . implode(', ', $healed) . '.');
                }
            } catch (\Exception $e) {
                throw new InstallException('Could not update the .htaccess files under user/', $e);
            }
        }
];
