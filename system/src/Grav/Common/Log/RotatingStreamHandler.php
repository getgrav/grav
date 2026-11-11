<?php

/**
 * @package    Grav\Common\Log
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Log;

use Monolog\Handler\StreamHandler;
use Monolog\LogRecord;
use Throwable;

/**
 * A StreamHandler that rotates its file (see LogRotator) when it has grown past
 * `system.log.rotate.max_size`. The size is checked once, on the first write, so a request
 * that logs nothing pays nothing and one that logs pays a single stat.
 */
class RotatingStreamHandler extends StreamHandler
{
    private bool $checked = false;

    protected function write(LogRecord $record): void
    {
        if (!$this->checked) {
            $this->checked = true;
            $this->rotateIfDue();
        }

        parent::write($record);
    }

    private function rotateIfDue(): void
    {
        $path = $this->getUrl();
        // Only plain files; a php://stderr or similar stream has nothing to rotate.
        if ($path === null || str_contains($path, '://')) {
            return;
        }

        try {
            $settings = LogRotator::settings();
            if ($settings === null || !$settings['enabled']) {
                return;
            }

            if (LogRotator::needsRotation($path, $settings['max_size'])) {
                LogRotator::rotate($path, $settings['max_files'], $settings['compress'], $settings['max_size']);
            }
        } catch (Throwable) {
            // Logging must never break because rotation failed; try again next request.
        }
    }
}
