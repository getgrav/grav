<?php

/**
 * Security postflight (GHSA-vwg3-w8w3-pc79): make the root .htaccess deny rules
 * case-insensitive.
 *
 * The shipped .htaccess security rules (sensitive folders, blocked file types,
 * .md, root files) matched case-sensitively. On case-insensitive filesystems
 * (Windows, macOS, or Docker volume mounts from those hosts) an attacker could
 * bypass them with a case-varied request - e.g. GET /User/accounts/<user>.yaml
 * served the account file (password hash) instead of returning 403.
 *
 * This adds the [NC] flag to every security deny rule (`error [F]` -> `error
 * [F,NC]`) in an existing root .htaccess. It is idempotent: a rule already on
 * the [F,NC] form contains no `error [F]` literal and is left untouched, and
 * non-security rules (e.g. the `index.php [F]` rewrite) are not affected because
 * they do not use the `error [F]` token. Making a deny rule case-insensitive can
 * only block more, never expose more.
 *
 * The per-folder user/data/.htaccess media carve-out written by the 2026-06-15
 * update uses <FilesMatch>, which is case-sensitive by default, so an uploaded
 * file with an uppercase extension (e.g. PHOTO.JPG) hit the deny-all and 403'd.
 * That backup file is switched to a case-insensitive (?i) match here too, so it
 * stays consistent with the root rule and does not block legitimate uploads.
 *
 * Native case-sensitive Linux (ext4) was not exploitable, but the flags are
 * correct on every platform. Both rewrites are idempotent.
 */

return [
    'preflight' => null,
    'postflight' =>
        function () {
            // ---- Site root: make every security deny rule case-insensitive. ----
            $root = GRAV_ROOT . '/.htaccess';
            if (is_file($root) && is_writable($root)) {
                $contents = file_get_contents($root);
                if ($contents !== false) {
                    $patched = str_replace('error [F]', 'error [F,NC]', $contents);
                    if ($patched !== $contents) {
                        @file_put_contents($root, $patched);
                    }
                }
            }

            // ---- Per-folder backup: make the user/data media carve-out case-insensitive. ----
            $dataHtaccess = GRAV_ROOT . '/user/data/.htaccess';
            if (is_file($dataHtaccess) && is_writable($dataHtaccess)) {
                $contents = file_get_contents($dataHtaccess);
                if ($contents !== false) {
                    // Only the feature-written carve-out has this token; once it is
                    // on the (?i) form the literal is gone, so this is idempotent.
                    $patched = str_replace('FilesMatch "\.(', 'FilesMatch "(?i)\.(', $contents);
                    if ($patched !== $contents) {
                        @file_put_contents($dataHtaccess, $patched);
                    }
                }
            }
        }
];
