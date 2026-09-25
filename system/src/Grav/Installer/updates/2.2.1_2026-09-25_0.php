<?php

/**
 * Take mod_rewrite out of the .htaccess files under user/ and use mod_alias only.
 *
 * Since 2.1.5 these files have been rewrite rulesets carrying
 * `RewriteOptions InheritDownBefore`. Turning RewriteEngine on in user/ replaces
 * the site root's rewrite rules for the whole user/ tree, so user/.htaccess had to
 * restate them and route missing files back to index.php itself, and every host
 * quirk in that path takes out every stylesheet, script and image under user/ at
 * once. Older Apache rejects the option outright (getgrav/grav-plugin-admin2#179),
 * and at least one shared host that accepts it still breaks on it
 * (getgrav/grav#4309).
 *
 * The files now hold only `RedirectMatch 403` rules. With no RewriteEngine in
 * user/, the root rules cover it as they did before 2.0.19. mod_alias is
 * FileInfo-class like mod_rewrite, sits outside the rewrite pipeline and merges
 * into subdirectories, so a package's own .htaccess still cannot switch the
 * protection off (getgrav/grav#4236), with no version check and no
 * `InheritDownBefore`.
 *
 * Same rule as 2.1.5, 2.1.6 and 2.2.0: replace a file only when its content is
 * byte-for-byte a copy Grav wrote, and leave a file the site edited alone. A site
 * that removed the option by hand to get back online keeps that working edit.
 */

use Grav\Installer\InstallException;

return [
    'preflight' => null,
    'postflight' =>
        function () {
            try {
                // sha256 of every copy Grav has written that carries
                // InheritDownBefore, normalised (CRLF folded, trailing whitespace
                // trimmed, one final newline). The 2.2.0 migration, which runs
                // first, turns the unconditional copies into the guarded ones.
                $known = [
                    'user/.htaccess' => [
                        // 2.2.0
                        '5b6997ad36839717c7f029dabe68268c699b15c53f4e57c6f5d45433c7ca6235',
                        // 2.1.5
                        '6e4a76c8f587a4782bcf04480be4a147159a1951cfba3650e50bc39fa38a884c',
                        // 2.1.6 - 2.1.12, and what the 2026-08-13 postflight wrote before 2.2.1
                        '2beacb0ba2a06dc3a74ecafe2bd07d7643e8094253e4b86f9c3ceecfdf19f308',
                    ],
                    'user/accounts/.htaccess' => [
                        // 2.2.0
                        '348fc8f43dd565d19f4bab38cee9d34ce9bd258cfb3c89849aa61261f123719d',
                        // 2.1.5 - 2.1.12
                        '1bda758f53018bb25104d6f6da74cac328c844b64250312107b2dc7922b3f554',
                    ],
                    'user/config/.htaccess' => [
                        // 2.2.0
                        '97f127d864fed19e9306454310d90133b307a28de1d9d185e7d4753b9b5822b5',
                        // 2.1.5 - 2.1.12
                        '65186f850858b59dd20fa3174876375bedc3ecaf95ab31ea1dea17dee6396c1c',
                    ],
                    'user/env/.htaccess' => [
                        // what the 2.1.6 and 2.2.0 migrations write, the same as user/config
                        '97f127d864fed19e9306454310d90133b307a28de1d9d185e7d4753b9b5822b5',
                        '65186f850858b59dd20fa3174876375bedc3ecaf95ab31ea1dea17dee6396c1c',
                    ],
                    'user/data/.htaccess' => [
                        // 2.2.0
                        '9f5384e67f212d6c44662571111fbb78ad0d0fc945b4f9c9eef2770f41d225b7',
                        // 2.1.5 - 2.1.12
                        '013bc8ef7ee9e6dd0f04af30a96b756a56c3c0ce23392f5020978b5b8e2a4334',
                    ],
                ];

                // Byte-for-byte the shipped files; user/env gets the user/config
                // copy. Keep these in sync with the files under user/.
                $config = <<<'HTACCESS'
                        # Deny all direct web access to this folder and everything beneath it.
                        # Grav reads these files server-side; they must never be served over HTTP.
                        # This is a defense-in-depth backup for the rules in the site root .htaccess.
                        #
                        # mod_alias, not `Require` or mod_rewrite. `Require` is AuthConfig-class and
                        # returns 500 for this whole folder on a host that grants only `AllowOverride
                        # FileInfo` (getgrav/grav#4309, #4311). mod_alias is FileInfo-class, leaves the
                        # root's rewrite rules in force here, and merges into subfolders, so a subfolder
                        # with its own RewriteEngine cannot switch it off.
                        <IfModule mod_alias.c>
                            RedirectMatch 403 .*
                        </IfModule>

                        HTACCESS;

                $replacements = [
                    'user/.htaccess' => <<<'HTACCESS'
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

                        HTACCESS,
                    'user/accounts/.htaccess' => <<<'HTACCESS'
                        # Deny direct web access to this folder, except avatar images (account://avatars)
                        # that Grav serves over HTTP. Account data (.yaml password hashes), databases,
                        # keys and tokens stay blocked; SVG is excluded as a stored-XSS vector.
                        # Defense-in-depth backup for the rules in the site root .htaccess.
                        #
                        # mod_alias, not `Require` or mod_rewrite. `Require` is AuthConfig-class and
                        # returns 500 for this whole folder on a host that grants only `AllowOverride
                        # FileInfo` (getgrav/grav#4309, #4311). mod_alias is FileInfo-class, leaves the
                        # root's rewrite rules in force here, and merges into subfolders, so a subfolder
                        # with its own RewriteEngine cannot switch it off.
                        #
                        # Matches the whole URL path, so the exception is written exactly as the root's.
                        # Keep the two in sync.
                        <IfModule mod_alias.c>
                            RedirectMatch 403 (?i)^(?!.*/user/accounts/[^/]+/[^/]+\.(jpe?g|png|gif|webp|avif|bmp|ico)$)
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
                        # mod_alias, not `Require` or mod_rewrite. `Require` is AuthConfig-class and
                        # returns 500 for this whole folder on a host that grants only `AllowOverride
                        # FileInfo` (getgrav/grav#4309, #4311). mod_alias is FileInfo-class, leaves the
                        # root's rewrite rules in force here, and merges into subfolders, so a subfolder
                        # with its own RewriteEngine cannot switch it off.
                        #
                        # Keep the extension list in sync with the root's user/data rule.
                        <IfModule mod_alias.c>
                            RedirectMatch 403 (?i)^(?!.*\.(jpe?g|png|gif|webp|avif|bmp|ico|mp4|webm|ogg|ogv|mov|mp3|wav|m4a|flac|pdf|woff2|woff|ttf|otf|eot|css|js)$)
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
                    error_log('Grav upgrade: moved ' . implode(', ', $healed) . ' from mod_rewrite to mod_alias.');
                }
            } catch (\Exception $e) {
                throw new InstallException('Could not update the .htaccess files under user/', $e);
            }
        }
];
