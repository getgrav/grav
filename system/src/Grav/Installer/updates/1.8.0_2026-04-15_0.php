<?php

return [
    'preflight' => null,
    'postflight' =>
        function () {
            foreach (['upgrade.php', 'needs_fixing.txt'] as $stale) {
                $path = GRAV_ROOT . '/' . $stale;
                if (is_file($path)) {
                    @unlink($path);
                }
            }
        }
];
