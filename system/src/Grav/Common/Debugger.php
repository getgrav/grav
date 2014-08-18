<?php
namespace Grav\Common;

use \Tracy\Debugger as TracyDebugger;

/**
 * Class Debugger
 * @package Grav\Common
 */
class Debugger
{
    public function __construct()
    {
        // Start the timer and enable debugger in production mode as we do not have system configuration yet.
        // Debugger catches all errors and logs them, for example if the script doesn't have write permissions.
        TracyDebugger::timer();
        TracyDebugger::enable(TracyDebugger::DEVELOPMENT, is_dir(LOG_DIR) ? LOG_DIR : null);
    }

    public function init()
    {
        /** @var Config $config */
        $config = Grav::instance()['Config'];

        TracyDebugger::$logDirectory = $config->get('system.debugger.log.enabled') ? LOG_DIR : null;
        TracyDebugger::$maxDepth = $config->get('system.debugger.max_depth');

        // Switch debugger into development mode if configured
        if ($config->get('system.debugger.enabled')) {
            if ($config->get('system.debugger.strict')) {
                TracyDebugger::$strictMode = true;
            }

            if (function_exists('ini_set')) {
                ini_set('display_errors', true);
            }
            TracyDebugger::$productionMode = TracyDebugger::DEVELOPMENT;
        }
    }

    /**
     * Log a message.
     *
     * @param string $message
     */
    public function log($message)
    {
        if (TracyDebugger::$logDirectory) {
            TracyDebugger::log(sprintf($message, TracyDebugger::timer() * 1000));
        }
    }
}
