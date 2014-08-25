<?php

/**
 * This file is part of the Tracy (http://tracy.nette.org)
 * Copyright (c) 2004 David Grudl (http://davidgrudl.com)
 */

namespace Tracy;

use Tracy;


/**
 * FireLogger console logger.
 *
 * @see http://firelogger.binaryage.com
 * @author     David Grudl
 */
class FireLogger
{
	const DEBUG = 'debug',
		INFO = 'info',
		WARNING = 'warning',
		ERROR = 'error',
		CRITICAL = 'critical';

	private static $payload = array('logs' => array());


	/**
	 * Sends message to FireLogger console.
	 * @param  mixed
	 * @return bool    was successful?
	 */
	public static function log($message, $priority = self::DEBUG)
	{
		if (!isset($_SERVER['HTTP_X_FIRELOGGER']) || headers_sent()) {
			return FALSE;
		}

		$item = array(
			'name' => 'PHP',
			'level' => $priority,
			'order' => count(self::$payload['logs']),
			'time' => str_pad(number_format((microtime(TRUE) - Debugger::$time) * 1000, 1, '.', ' '), 8, '0', STR_PAD_LEFT) . ' ms',
			'template' => '',
			'message' => '',
			'style' => 'background:#767ab6',
		);

		$args = func_get_args();
		if (isset($args[0]) && is_string($args[0])) {
			$item['template'] = array_shift($args);
		}

		if (isset($args[0]) && $args[0] instanceof \Exception) {
			$e = array_shift($args);
			$trace = $e->getTrace();
			if (isset($trace[0]['class']) && $trace[0]['class'] === 'Tracy\Debugger'
				&& ($trace[0]['function'] === '_shutdownHandler' || $trace[0]['function'] === '_errorHandler')
			) {
				unset($trace[0]);
			}

			$file = str_replace(dirname(dirname(dirname($e->getFile()))), "\xE2\x80\xA6", $e->getFile());
			$item['template'] = ($e instanceof \ErrorException ? '' : get_class($e) . ': ')
				. $e->getMessage() . ($e->getCode() ? ' #' . $e->getCode() : '') . ' in ' . $file . ':' . $e->getLine();
			$item['pathname'] = $e->getFile();
			$item['lineno'] = $e->getLine();

		} else {
			$trace = debug_backtrace();
			if (isset($trace[1]['class']) && $trace[1]['class'] === 'Tracy\Debugger'
				&& ($trace[1]['function'] === 'fireLog')
			) {
				unset($trace[0]);
			}

			foreach ($trace as $frame) {
				if (isset($frame['file']) && is_file($frame['file'])) {
					$item['pathname'] = $frame['file'];
					$item['lineno'] = $frame['line'];
					break;
				}
			}
		}

		$item['exc_info'] = array('', '', array());
		$item['exc_frames'] = array();

		foreach ($trace as $frame) {
			$frame += array('file' => NULL, 'line' => NULL, 'class' => NULL, 'type' => NULL, 'function' => NULL, 'object' => NULL, 'args' => NULL);
			$item['exc_info'][2][] = array($frame['file'], $frame['line'], "$frame[class]$frame[type]$frame[function]", $frame['object']);
			$item['exc_frames'][] = $frame['args'];
		}

		if (isset($args[0]) && in_array($args[0], array(self::DEBUG, self::INFO, self::WARNING, self::ERROR, self::CRITICAL), TRUE)) {
			$item['level'] = array_shift($args);
		}

		$item['args'] = $args;

		self::$payload['logs'][] = self::jsonDump($item, -1);
		foreach (str_split(base64_encode(@json_encode(self::$payload)), 4990) as $k => $v) { // intentionally @
			header("FireLogger-de11e-$k:$v");
		}
		return TRUE;
	}


	/**
	 * Dump implementation for JSON.
	 * @param  mixed  variable to dump
	 * @param  int    current recursion level
	 * @return string
	 */
	private static function jsonDump(& $var, $level = 0)
	{
		if (is_bool($var) || is_null($var) || is_int($var) || is_float($var)) {
			return $var;

		} elseif (is_string($var)) {
			if (Debugger::$maxLen && strlen($var) > Debugger::$maxLen) {
				$var = substr($var, 0, Debugger::$maxLen) . " \xE2\x80\xA6 ";
			}
			return Helpers::fixEncoding($var);

		} elseif (is_array($var)) {
			static $marker;
			if ($marker === NULL) {
				$marker = uniqid("\x00", TRUE);
			}
			if (isset($var[$marker])) {
				return "\xE2\x80\xA6RECURSION\xE2\x80\xA6";

			} elseif ($level < Debugger::$maxDepth || !Debugger::$maxDepth) {
				$var[$marker] = TRUE;
				$res = array();
				foreach ($var as $k => & $v) {
					if ($k !== $marker) {
						$res[self::jsonDump($k)] = self::jsonDump($v, $level + 1);
					}
				}
				unset($var[$marker]);
				return $res;

			} else {
				return " \xE2\x80\xA6 ";
			}

		} elseif (is_object($var)) {
			$arr = (array) $var;
			static $list = array();
			if (in_array($var, $list, TRUE)) {
				return "\xE2\x80\xA6RECURSION\xE2\x80\xA6";

			} elseif ($level < Debugger::$maxDepth || !Debugger::$maxDepth) {
				$list[] = $var;
				$res = array("\x00" => '(object) ' . get_class($var));
				foreach ($arr as $k => & $v) {
					if ($k[0] === "\x00") {
						$k = substr($k, strrpos($k, "\x00") + 1);
					}
					$res[self::jsonDump($k)] = self::jsonDump($v, $level + 1);
				}
				array_pop($list);
				return $res;

			} else {
				return " \xE2\x80\xA6 ";
			}

		} elseif (is_resource($var)) {
			return 'resource ' . get_resource_type($var);

		} else {
			return 'unknown type';
		}
	}

}
