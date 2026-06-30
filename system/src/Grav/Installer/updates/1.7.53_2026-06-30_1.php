<?php

/**
 * Security postflight follow-up: widen the user/accounts avatar carve-out to
 * cover Flex folder-storage user avatars (getgrav/grav#4185).
 *
 * The 2026-06-30_0 update opened user/accounts only for the flatfile avatar
 * layout, user/accounts/avatars/<file> (the account://avatars stream). Flex
 * users on UserFolderStorage store their avatar media under the user object
 * folder instead, user/accounts/<username>/<file>, so those avatars kept
 * returning a 403.
 *
 * Both layouts are exactly two segments under user/accounts (<dir>/<file>), so
 * this widens the root .htaccess RewriteCond from the avatars-only path to any
 * two-segment image path. Account data (.yaml password hashes) is one segment
 * (user/accounts/<username>.yaml) or a non-image file inside the folder, so it
 * stays blocked. SVG stays blocked as a stored-XSS vector.
 *
 * The per-folder user/accounts/.htaccess backup already grants by <FilesMatch>
 * extension regardless of depth, so it needs no change. This is idempotent:
 * once the path is on the [^/]+/[^/]+ form the avatars-only literal is gone.
 */

return [
    'preflight' => null,
    'postflight' =>
        function () {
            $root = GRAV_ROOT . '/.htaccess';
            if (is_file($root) && is_writable($root)) {
                $contents = file_get_contents($root);
                if ($contents !== false) {
                    $patched = str_replace(
                        '/user/accounts/avatars/[^/]+\.',
                        '/user/accounts/[^/]+/[^/]+\.',
                        $contents
                    );
                    if ($patched !== $contents) {
                        @file_put_contents($root, $patched);
                    }
                }
            }
        }
];
