<?php

/**
 * Finish #4311: the one `Require`-based .htaccess 2.1.5 could not see.
 *
 * 2.1.5 rebuilt the .htaccess files under user/ on mod_rewrite, because `Require`
 * is AuthConfig-class and returns 500 for a whole folder on a host granting only
 * `AllowOverride FileInfo` (getgrav/grav#4309, #4311). It healed the four files
 * Grav ships — user/.htaccess and the ones in accounts, config and data.
 *
 * `user/env/.htaccess` is not one of them. Grav has never shipped a `user/env`
 * folder, so that file only exists where the 2026-06-09 postflight created it on
 * a site that had one, which is why it was missed: it is in no release archive
 * and no checkout. Sites carrying it still answer 500 for `user/env` on those
 * hosts, and a site upgrading the whole chain in one pass has it written by that
 * older postflight moments before this one runs.
 *
 * Same rule as 2.1.5: replace the file only when its content is byte-for-byte
 * what Grav wrote. A file the site edited is left alone.
 */

use Grav\Installer\InstallException;

return [
    'preflight' => null,
    'postflight' =>
        function () {
            try {
                $file = GRAV_ROOT . '/user/env/.htaccess';
                if (!is_file($file) || !is_writable($file)) {
                    return; // most sites have no user/env folder at all
                }

                $current = @file_get_contents($file);
                if ($current === false) {
                    return;
                }

                // The deny-all copy the 2026-06-09 postflight writes, normalised
                // the way 2.1.5 normalises (CRLF folded, trailing whitespace
                // trimmed, one final newline).
                $known = 'd8b0c6828fc2e83bf0ce0a9d8ef61e0ca11ffadd6a282cd649046ea634281326';
                if (hash('sha256', rtrim(str_replace("\r\n", "\n", $current)) . "\n") !== $known) {
                    return; // edited by the site — leave it alone
                }

                // Matches user/config/.htaccess, which denies this folder the same
                // way. Keep the two in sync, and with the `^(user)/(config|env)/`
                // rule in the site root .htaccess.
                $contents = <<<'HTACCESS'
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

                    HTACCESS;

                if (@file_put_contents($file, $contents) !== false) {
                    error_log(
                        'Grav upgrade: rewrote user/env/.htaccess on mod_rewrite. The previous '
                        . 'copy used `Require`, which returns 500 for that folder on a host '
                        . 'granting only `AllowOverride FileInfo`.'
                    );
                }
            } catch (\Exception $e) {
                throw new InstallException('Could not update user/env/.htaccess', $e);
            }
        }
];
