<?php

/**
 * Security postflight (GHSA-vwg3-w8w3-pc79): make the root .htaccess deny rules
 * case-insensitive, and heal the user/data asset allowlist that earlier security
 * updates left too narrow.
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
 * Asset-allowlist heal: the 2026-06-09 and 2026-06-15 updates introduced the
 * user/data carve-out with a media-only extension list (images/audio/video/pdf),
 * which 403'd compiled assets that legitimately live under user/data - notably
 * theme CSS (e.g. Gantry's css-compiled output), JS and webfonts. The shipped
 * static .htaccess files have since been widened to include
 * woff2|woff|ttf|otf|eot|css|js, but Grav never overwrites a user's .htaccess on
 * upgrade, so existing sites keep the narrow list. This postflight widens that
 * list in place - in both the root RewriteCond and the per-folder FilesMatch -
 * and converts any still-blanket deny-all user/data/.htaccess backup into the
 * full media-aware carve-out. SVG stays blocked (stored-XSS vector). Only
 * user/data is widened; user/accounts, user/config and user/env hold no public
 * assets and stay fully denied.
 *
 * Native case-sensitive Linux (ext4) was not exploitable, but the flags are
 * correct on every platform. Every rewrite below is idempotent.
 */

// Asset extension lists for the user/data carve-out. The "narrow" list is what
// the 2026-06-09 / 2026-06-15 updates shipped; the "full" list matches the
// current static .htaccess files. SVG is intentionally excluded as a stored-XSS
// vector on this user-writable folder.
$narrowMediaExt = 'jpe?g|png|gif|webp|avif|bmp|ico|mp4|webm|ogg|ogv|mov|mp3|wav|m4a|flac|pdf';
$fullMediaExt = $narrowMediaExt . '|woff2|woff|ttf|otf|eot|css|js';

// The media-aware deny-except-assets backup, written when an existing per-folder
// user/data/.htaccess is still the original blanket deny-all.
$dataCarveOut = <<<HTACCESS
# Deny direct web access to this folder, except the public asset uploads
# (e.g. Flex Object images) that Grav has always served from here.
# Data files (.yaml/.json/.md), databases, keys and tokens stay blocked.
# SVG stays blocked as a stored-XSS vector; .css/.js are served per project
# policy despite the same risk on this user-writable folder.
# Defense-in-depth backup for the rules in the site root .htaccess.
<IfModule mod_authz_core.c>
    Require all denied
</IfModule>
<IfModule !mod_authz_core.c>
    Order allow,deny
    Deny from all
</IfModule>
<FilesMatch "(?i)\.($fullMediaExt)$">
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
        function () use ($narrowMediaExt, $fullMediaExt, $dataCarveOut) {
            // ---- Site root: case-insensitive deny rules + widened user/data allowlist. ----
            $root = GRAV_ROOT . '/.htaccess';
            if (is_file($root) && is_writable($root)) {
                $contents = file_get_contents($root);
                if ($contents !== false) {
                    // Make every security deny rule case-insensitive (GHSA-vwg3).
                    $patched = str_replace('error [F]', 'error [F,NC]', $contents);
                    // Widen the user/data asset allowlist so theme CSS/JS/fonts are
                    // served. Anchored on the closing ")" so once the full list is
                    // present the narrow literal is gone - idempotent.
                    $patched = str_replace($narrowMediaExt . ')', $fullMediaExt . ')', $patched);
                    if ($patched !== $contents) {
                        @file_put_contents($root, $patched);
                    }
                }
            }

            // ---- Per-folder backup: widen and/or case-fold the user/data carve-out. ----
            $dataHtaccess = GRAV_ROOT . '/user/data/.htaccess';
            if (is_file($dataHtaccess) && is_writable($dataHtaccess)) {
                $contents = file_get_contents($dataHtaccess);
                if ($contents !== false) {
                    if (strpos($contents, 'FilesMatch') === false
                        && strpos($contents, 'Require all denied') !== false) {
                        // Still the original blanket deny-all: replace it with the
                        // full media-aware carve-out so assets are served.
                        $patched = $dataCarveOut;
                    } else {
                        // Existing carve-out: widen the asset list and make the
                        // FilesMatch case-insensitive. Both are idempotent - once
                        // the full list / (?i) form is present, neither literal
                        // remains to match.
                        $patched = str_replace($narrowMediaExt . ')', $fullMediaExt . ')', $contents);
                        $patched = str_replace('FilesMatch "\.(', 'FilesMatch "(?i)\.(', $patched);
                    }
                    if ($patched !== $contents) {
                        @file_put_contents($dataHtaccess, $patched);
                    }
                }
            }
        }
];
