<?php

/**
 * Stop the .htaccess files under user/ answering 500 on Apache older than 2.4.8.
 *
 * `RewriteOptions InheritDownBefore` arrived in Apache 2.4.8. An older server, such
 * as the 2.4.6 that CentOS/RHEL 7 and many Plesk hosts still run, does not know the
 * option and rejects the whole .htaccess file, so every request under that folder
 * fails, stylesheets and images included, and the admin with them
 * (getgrav/grav-plugin-admin2#179). 2.1.4 introduced the option, and the 2.1.5 and
 * 2.1.6 migrations and the 2026-08-13 postflight wrote it into existing sites.
 *
 * The shipped files now set it inside `<IfVersion >= 2.4.8>` (itself inside
 * `<IfModule mod_version.c>`, since `<IfVersion>` is unknown without mod_version).
 * user/ is in the installer `$ignores` list, so this carries the change to sites
 * already upgraded. Same rule as 2.1.5 and 2.1.6: replace a file only when its
 * content is byte-for-byte a copy Grav wrote, and leave a file the site edited
 * alone. A site that commented the option out by hand to get back online keeps
 * that edit, which is equivalent on an old server.
 */

use Grav\Installer\InstallException;

return [
    'preflight' => null,
    'postflight' =>
        function () {
            try {
                // sha256 of every copy that sets InheritDownBefore unconditionally,
                // normalised (CRLF folded, trailing whitespace trimmed, one final
                // newline). Older copies are replaced by the 2.1.5 and 2.1.6
                // migrations, which run before this one, with one of these.
                $known = [
                    'user/.htaccess' => [
                        // 2.1.5
                        '6e4a76c8f587a4782bcf04480be4a147159a1951cfba3650e50bc39fa38a884c',
                        // 2.1.6 - 2.1.12, and what the 2026-08-13 postflight writes
                        '2beacb0ba2a06dc3a74ecafe2bd07d7643e8094253e4b86f9c3ceecfdf19f308',
                    ],
                    'user/accounts/.htaccess' => [
                        // 2.1.5 - 2.1.12
                        '1bda758f53018bb25104d6f6da74cac328c844b64250312107b2dc7922b3f554',
                    ],
                    'user/config/.htaccess' => [
                        // 2.1.5 - 2.1.12
                        '65186f850858b59dd20fa3174876375bedc3ecaf95ab31ea1dea17dee6396c1c',
                    ],
                    'user/env/.htaccess' => [
                        // what the 2.1.6 migration writes, the same as user/config
                        '65186f850858b59dd20fa3174876375bedc3ecaf95ab31ea1dea17dee6396c1c',
                    ],
                    'user/data/.htaccess' => [
                        // 2.1.5 - 2.1.12
                        '013bc8ef7ee9e6dd0f04af30a96b756a56c3c0ce23392f5020978b5b8e2a4334',
                    ],
                ];

                // Byte-for-byte the shipped files; user/env gets the user/config
                // copy, as in 2.1.6. Keep these in sync with the files under user/.
                $config = <<<'HTACCESS'
                        # Deny all direct web access to this folder and everything beneath it.
                        # Grav reads these files server-side; they must never be served over HTTP.
                        # This is a defense-in-depth backup for the rules in the site root .htaccess.
                        #
                        # mod_rewrite, not `Require`: `Require` is AuthConfig-class and returns 500 for
                        # this whole folder on a host that grants only `AllowOverride FileInfo`, which
                        # is all the root .htaccess has ever needed (getgrav/grav#4309, #4311).
                        <IfModule mod_rewrite.c>
                            RewriteEngine On
                            # InheritDownBefore needs Apache 2.4.8 or newer. An older server rejects the whole
                            # file and answers 500 for everything under this folder (getgrav/grav-plugin-admin2#179),
                            # so it is only set where the server can take it. Older servers still apply the rules
                            # below, just not ahead of a subfolder's own RewriteEngine.
                            <IfModule mod_version.c>
                                <IfVersion >= 2.4.8>
                                    RewriteOptions InheritDownBefore
                                </IfVersion>
                            </IfModule>

                            RewriteRule .* - [F]
                        </IfModule>

                        HTACCESS;

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
                            # InheritDownBefore needs Apache 2.4.8 or newer. An older server rejects the whole
                            # file and answers 500 for everything under this folder (getgrav/grav-plugin-admin2#179),
                            # so it is only set where the server can take it. Older servers still apply the rules
                            # below, just not ahead of a subfolder's own RewriteEngine.
                            <IfModule mod_version.c>
                                <IfVersion >= 2.4.8>
                                    RewriteOptions InheritDownBefore
                                </IfVersion>
                            </IfModule>

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
                            # InheritDownBefore needs Apache 2.4.8 or newer. An older server rejects the whole
                            # file and answers 500 for everything under this folder (getgrav/grav-plugin-admin2#179),
                            # so it is only set where the server can take it. Older servers still apply the rules
                            # below, just not ahead of a subfolder's own RewriteEngine.
                            <IfModule mod_version.c>
                                <IfVersion >= 2.4.8>
                                    RewriteOptions InheritDownBefore
                                </IfVersion>
                            </IfModule>

                            # REQUEST_URI is the whole original path; the rule pattern only sees the
                            # path below this folder, so the exception is written as a condition.
                            RewriteCond %{REQUEST_URI} !/user/accounts/[^/]+/[^/]+\.(jpe?g|png|gif|webp|avif|bmp|ico)$ [NC]
                            RewriteRule .* - [F]
                        </IfModule>

                        HTACCESS,
                    'user/config/.htaccess' => $config,
                    'user/env/.htaccess' => $config,
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
                            # InheritDownBefore needs Apache 2.4.8 or newer. An older server rejects the whole
                            # file and answers 500 for everything under this folder (getgrav/grav-plugin-admin2#179),
                            # so it is only set where the server can take it. Older servers still apply the rules
                            # below, just not ahead of a subfolder's own RewriteEngine.
                            <IfModule mod_version.c>
                                <IfVersion >= 2.4.8>
                                    RewriteOptions InheritDownBefore
                                </IfVersion>
                            </IfModule>

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
                    error_log('Grav upgrade: updated ' . implode(', ', $healed) . ' for Apache older than 2.4.8.');
                }
            } catch (\Exception $e) {
                throw new InstallException('Could not update the .htaccess files under user/', $e);
            }
        }
];
