<?php

/**
 * @package    Grav\Framework\Compat
 *
 * Provides lightweight shims for legacy Monolog installations used in Grav 1.7
 * so that newer Grav code (targeting Monolog 3) can run without fatal errors.
 */

declare(strict_types=1);

namespace Grav\Framework\Compat\Monolog;

if (!class_exists(\Monolog\Utils::class, false)) {
    spl_autoload_register(
        static function (string $class): bool {
            if ($class === 'Monolog\\Utils') {
                require __DIR__ . '/Utils.php';

                return true;
            }

            return false;
        },
        true,
        true
    );
}
