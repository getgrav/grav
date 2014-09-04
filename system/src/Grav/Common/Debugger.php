<?php
namespace Grav\Common;

use \Tracy\Debugger as TracyDebugger;

/**
 * Class Debugger
 * @package Grav\Common
 */
class Debugger
{
    const PRODUCTION = TracyDebugger::PRODUCTION;
    const DEVELOPMENT = TracyDebugger::DEVELOPMENT;
    const DETECT = TracyDebugger::DETECT;

    public function __construct($mode = self::PRODUCTION)
    {
        // Start the timer and enable debugger in production mode as we do not have system configuration yet.
        // Debugger catches all errors and logs them, for example if the script doesn't have write permissions.
        TracyDebugger::timer();
        TracyDebugger::enable($mode, is_dir(LOG_DIR) ? LOG_DIR : null);
    }

    public function init()
    {
        $grav = Grav::instance();

        /** @var Config $config */
        $config = $grav['config'];

        TracyDebugger::$logDirectory = $config->get('system.debugger.log.enabled') ? LOG_DIR : null;
        TracyDebugger::$maxDepth = $config->get('system.debugger.max_depth');

        // Switch debugger into development mode if configured
        if ($config->get('system.debugger.enabled')) {
            if ($config->get('system.debugger.strict')) {
                TracyDebugger::$strictMode = true;
            }

            $mode = $config->get('system.debugger.mode');

            if (function_exists('ini_set')) {
                ini_set('display_errors', !($mode === 'production'));
            }

            if ($mode === 'detect') {
                TracyDebugger::$productionMode = self::DETECT;
            } elseif ($mode === 'production') {
                TracyDebugger::$productionMode = self::PRODUCTION;
            } else {
                TracyDebugger::$productionMode = self::DEVELOPMENT;
            }

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

    public static function dump($var)
    {
        TracyDebugger::dump($var);
    }
}
