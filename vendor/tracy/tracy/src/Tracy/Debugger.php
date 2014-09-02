<?php

/**
 * This file is part of the Tracy (http://tracy.nette.org)
 * Copyright (c) 2004 David Grudl (http://davidgrudl.com)
 */

namespace Tracy;

use Tracy,
	ErrorException;


/**
 * Debugger: displays and logs errors.
 *
 * Behavior is determined by two factors: mode & output
 * - modes: production / development
 * - output: HTML / AJAX / CLI / other (e.g. XML)
 *
 * @author     David Grudl
 */
class Debugger
{
	/** @var string */
	public static $version = '2.2.2';

	/** @var bool in production mode is suppressed any debugging output */
	public static $productionMode = self::DETECT;

	/** @var int timestamp with microseconds of the start of the request */
	public static $time;

	/** @var string  requested URI or command line */
	public static $source;

	/** @var string URI pattern mask to open editor */
	public static $editor = 'editor://open/?file=%file&line=%line';

	/** @var string command to open browser (use 'start ""' in Windows) */
	public static $browser;

	/********************* Debugger::dump() ****************d*g**/

	/** @var int  how many nested levels of array/object properties display {@link Debugger::dump()} */
	public static $maxDepth = 3;

	/** @var int  how long strings display {@link Debugger::dump()} */
	public static $maxLen = 150;

	/** @var bool display location? {@link Debugger::dump()} */
	public static $showLocation = FALSE;

	/********************* errors and exceptions reporting ****************d*g**/

	/** server modes {@link Debugger::enable()} */
	const DEVELOPMENT = FALSE,
		PRODUCTION = TRUE,
		DETECT = NULL;

	/** @var BlueScreen */
	private static $blueScreen;

	/** @var bool|int determines whether any error will cause immediate death; if integer that it's matched against error severity */
	public static $strictMode = FALSE; // $immediateDeath

	/** @var bool disables the @ (shut-up) operator so that notices and warnings are no longer hidden */
	public static $scream = FALSE;

	/** @var array of callables specifies the functions that are automatically called after fatal error */
	public static $onFatalError = array();

	/** @var bool {@link Debugger::enable()} */
	private static $enabled = FALSE;

	/** @internal */
	public static $errorTypes = array(
		E_ERROR => 'Fatal Error',
		E_USER_ERROR => 'User Error',
		E_RECOVERABLE_ERROR => 'Recoverable Error',
		E_CORE_ERROR => 'Core Error',
		E_COMPILE_ERROR => 'Compile Error',
		E_PARSE => 'Parse Error',
		E_WARNING => 'Warning',
		E_CORE_WARNING => 'Core Warning',
		E_COMPILE_WARNING => 'Compile Warning',
		E_USER_WARNING => 'User Warning',
		E_NOTICE => 'Notice',
		E_USER_NOTICE => 'User Notice',
		E_STRICT => 'Strict standards',
		E_DEPRECATED => 'Deprecated',
		E_USER_DEPRECATED => 'User Deprecated',
	);

	/********************* logging ****************d*g**/

	/** @var Logger */
	private static $logger;

	/** @var FireLogger */
	private static $fireLogger;

	/** @var string name of the directory where errors should be logged */
	public static $logDirectory;

	/** @var int  log bluescreen in production mode for this error severity */
	public static $logSeverity = 0;

	/** @var string|array email(s) to which send error notifications */
	public static $email;

	/** @deprecated */
	public static $mailer = array('Tracy\Logger', 'defaultMailer');

	/** @deprecated */
	public static $emailSnooze = 172800;

	/** {@link Debugger::log()} and {@link Debugger::fireLog()} */
	const DEBUG = 'debug',
		INFO = 'info',
		WARNING = 'warning',
		ERROR = 'error',
		EXCEPTION = 'exception',
		CRITICAL = 'critical';

	/********************* debug bar ****************d*g**/

	/** @var Bar */
	private static $bar;


	/**
	 * Static class - cannot be instantiated.
	 */
	final public function __construct()
	{
		throw new \LogicException;
	}


	/**
	 * Enables displaying or logging errors and exceptions.
	 * @param  mixed         production, development mode, autodetection or IP address(es) whitelist.
	 * @param  string        error log directory; enables logging in production mode, FALSE means that logging is disabled
	 * @param  string        administrator email; enables email sending in production mode
	 * @return void
	 */
	public static function enable($mode = NULL, $logDirectory = NULL, $email = NULL)
	{
		self::$enabled = TRUE;
		self::$time = isset($_SERVER['REQUEST_TIME_FLOAT']) ? $_SERVER['REQUEST_TIME_FLOAT'] : microtime(TRUE);
		if (isset($_SERVER['REQUEST_URI'])) {
			self::$source = (!empty($_SERVER['HTTPS']) && strcasecmp($_SERVER['HTTPS'], 'off') ? 'https://' : 'http://')
				. (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '')
				. $_SERVER['REQUEST_URI'];
		} else {
			self::$source = empty($_SERVER['argv']) ? 'CLI' : 'CLI: ' . implode(' ', $_SERVER['argv']);
		}
		error_reporting(E_ALL | E_STRICT);

		// production/development mode detection
		if (is_bool($mode)) {
			self::$productionMode = $mode;

		} elseif ($mode !== self::DETECT || self::$productionMode === NULL) { // IP addresses or computer names whitelist detection
			$list = is_string($mode) ? preg_split('#[,\s]+#', $mode) : (array) $mode;
			if (!isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
				$list[] = '127.0.0.1';
				$list[] = '::1';
			}
			self::$productionMode = !in_array(isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : php_uname('n'), $list, TRUE);
		}

		// logging configuration
		if ($email !== NULL) {
			self::$email = $email;
		}
		if (is_string($logDirectory)) {
			self::$logDirectory = realpath($logDirectory);
			if (self::$logDirectory === FALSE) {
				self::_exceptionHandler(new \RuntimeException("Log directory is not found or is not directory."));
			}
		} elseif ($logDirectory === FALSE) {
			self::$logDirectory = NULL;
		}
		if (self::$logDirectory) {
			ini_set('error_log', self::$logDirectory . '/php_error.log');
		}

		// php configuration
		if (function_exists('ini_set')) {
			ini_set('display_errors', !self::$productionMode); // or 'stderr'
			ini_set('html_errors', FALSE);
			ini_set('log_errors', FALSE);

		} elseif (ini_get('display_errors') != !self::$productionMode && ini_get('display_errors') !== (self::$productionMode ? 'stderr' : 'stdout')) { // intentionally ==
			self::_exceptionHandler(new \RuntimeException("Unable to set 'display_errors' because function ini_set() is disabled."));
		}

		register_shutdown_function(array(__CLASS__, '_shutdownHandler'));
		set_exception_handler(array(__CLASS__, '_exceptionHandler'));
		set_error_handler(array(__CLASS__, '_errorHandler'));

		foreach (array('Tracy\Bar', 'Tracy\BlueScreen', 'Tracy\DefaultBarPanel', 'Tracy\Dumper',
			'Tracy\FireLogger', 'Tracy\Helpers', 'Tracy\Logger', ) as $class) {
			class_exists($class);
		}
	}


	/**
	 * @return BlueScreen
	 */
	public static function getBlueScreen()
	{
		if (!self::$blueScreen) {
			self::$blueScreen = new BlueScreen;
			self::$blueScreen->info = array(
				'PHP ' . PHP_VERSION,
				isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : NULL,
				'Tracy ' . self::$version,
			);
		}
		return self::$blueScreen;
	}


	/**
	 * @return Bar
	 */
	public static function getBar()
	{
		if (!self::$bar) {
			self::$bar = new Bar;
			self::$bar->addPanel(new DefaultBarPanel('time'));
			self::$bar->addPanel(new DefaultBarPanel('memory'));
			self::$bar->addPanel(new DefaultBarPanel('errors'), __CLASS__ . ':errors'); // filled by _errorHandler()
			self::$bar->addPanel(new DefaultBarPanel('dumps'), __CLASS__ . ':dumps'); // filled by barDump()
			self::$bar->info = array(
				'PHP ' . PHP_VERSION,
				isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : NULL,
				'Tracy ' . self::$version,
			);
		}
		return self::$bar;
	}


	/**
	 * @return void
	 */
	public static function setLogger($logger)
	{
		self::$logger = $logger;
	}


	/**
	 * @return Logger
	 */
	public static function getLogger()
	{
		if (!self::$logger) {
			self::$logger = new Logger;
			self::$logger->directory = & self::$logDirectory;
			self::$logger->email = & self::$email;
			self::$logger->mailer = & self::$mailer;
			self::$logger->emailSnooze = & self::$emailSnooze;
		}
		return self::$logger;
	}


	/**
	 * @return FireLogger
	 */
	public static function getFireLogger()
	{
		if (!self::$fireLogger) {
			self::$fireLogger = new FireLogger;
		}
		return self::$fireLogger;
	}


	/**
	 * Is Debug enabled?
	 * @return bool
	 */
	public static function isEnabled()
	{
		return self::$enabled;
	}


	/**
	 * Logs message or exception to file (if not disabled) and sends email notification (if enabled).
	 * @param  string|Exception
	 * @param  int  one of constant Debugger::INFO, WARNING, ERROR (sends email), EXCEPTION (sends email), CRITICAL (sends email)
	 * @return string logged error filename
	 */
	public static function log($message, $priority = self::INFO)
	{
		if (!self::$logDirectory) {
			return;
		}

		$exceptionFilename = NULL;
		if ($message instanceof \Exception) {
			$exception = $message;
			while ($exception) {
				$tmp[] = ($exception instanceof ErrorException
					? 'Fatal error: ' . $exception->getMessage()
					: get_class($exception) . ': ' . $exception->getMessage())
					. ' in ' . $exception->getFile() . ':' . $exception->getLine();
				$exception = $exception->getPrevious();
			}
			$exception = $message;
			$message = implode($tmp, "\ncaused by ");

			$hash = md5(preg_replace('~(Resource id #)\d+~', '$1', $exception));
			$exceptionFilename = 'exception-' . @date('Y-m-d-H-i-s') . "-$hash.html";
			foreach (new \DirectoryIterator(self::$logDirectory) as $entry) {
				if (strpos($entry, $hash)) {
					$exceptionFilename = $entry;
					$saved = TRUE;
					break;
				}
			}
		} elseif (!is_string($message)) {
			$message = Dumper::toText($message);
		}

		if ($exceptionFilename) {
			$exceptionFilename = self::$logDirectory . '/' . $exceptionFilename;
			if (empty($saved) && $logHandle = @fopen($exceptionFilename, 'w')) {
				ob_start(); // double buffer prevents sending HTTP headers in some PHP
				ob_start(function($buffer) use ($logHandle) { fwrite($logHandle, $buffer); }, 4096);
				self::getBlueScreen()->render($exception);
				ob_end_flush();
				ob_end_clean();
				fclose($logHandle);
			}
		}

		self::getLogger()->log(array(
			@date('[Y-m-d H-i-s]'),
			trim($message),
			self::$source ? ' @  ' . self::$source : NULL,
			$exceptionFilename ? ' @@  ' . basename($exceptionFilename) : NULL
		), $priority);

		return $exceptionFilename ? strtr($exceptionFilename, '\\/', DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR) : NULL;
	}


	/**
	 * Shutdown handler to catch fatal errors and execute of the planned activities.
	 * @return void
	 * @internal
	 */
	public static function _shutdownHandler()
	{
		if (!self::$enabled) {
			return;
		}

		$error = error_get_last();
		if (in_array($error['type'], array(E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE), TRUE)) {
			self::_exceptionHandler(Helpers::fixStack(new ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line'])), FALSE);

		} elseif (!connection_aborted() && !self::$productionMode && self::isHtmlMode()) {
			self::getBar()->render();
		}
	}


	/**
	 * Handler to catch uncaught exception.
	 * @param  \Exception
	 * @return void
	 * @internal
	 */
	public static function _exceptionHandler(\Exception $exception, $exit = TRUE)
	{
		if (!self::$enabled) {
			return;
		}
		self::$enabled = FALSE; // prevent double rendering

		if (!headers_sent()) {
			$protocol = isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.1';
			$code = isset($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE ') !== FALSE ? 503 : 500;
			header("$protocol $code", TRUE, $code);
			if (self::isHtmlMode()) {
				header('Content-Type: text/html; charset=UTF-8');
			}
		}

		$logMsg = 'Unable to log error. Check if directory is writable and path is absolute.';
		if (self::$productionMode) {
			try {
				self::log($exception, self::EXCEPTION);
			} catch (\Exception $e) {
			}

			$error = isset($e) ? $logMsg : NULL;
			if (self::isHtmlMode()) {
				require __DIR__ . '/templates/error.phtml';
			} else {
				echo "ERROR: application encountered an error and can not continue.\n$error\n";
			}

		} elseif (!connection_aborted() && self::isHtmlMode()) {
			self::getBlueScreen()->render($exception);
			self::getBar()->render();

		} elseif (connection_aborted() || !self::fireLog($exception)) {
			try {
				$file = self::log($exception, self::EXCEPTION);
				if ($file && !headers_sent()) {
					header("X-Tracy-Error-Log: $file");
				}
				echo "$exception\n" . ($file ? "(stored in $file)\n" : '');
				if ($file && self::$browser) {
					exec(self::$browser . ' ' . escapeshellarg($file));
				}
			} catch (\Exception $e) {
				echo "$exception\n$logMsg {$e->getMessage()}\n";
			}
		}

		try {
			foreach (self::$onFatalError as $handler) {
				call_user_func($handler, $exception);
			}
		} catch (\Exception $e) {
			try {
				self::log($e, self::EXCEPTION);
			} catch (\Exception $e) {}
		}

		if ($exit) {
			exit(254);
		}
	}


	/**
	 * Handler to catch warnings and notices.
	 * @param  int    level of the error raised
	 * @param  string error message
	 * @param  string file that the error was raised in
	 * @param  int    line number the error was raised at
	 * @param  array  an array of variables that existed in the scope the error was triggered in
	 * @return bool   FALSE to call normal error handler, NULL otherwise
	 * @throws ErrorException
	 * @internal
	 */
	public static function _errorHandler($severity, $message, $file, $line, $context)
	{
		if (self::$scream) {
			error_reporting(E_ALL | E_STRICT);
		}

		if ($severity === E_RECOVERABLE_ERROR || $severity === E_USER_ERROR) {
			if (Helpers::findTrace(debug_backtrace(PHP_VERSION_ID >= 50306 ? DEBUG_BACKTRACE_IGNORE_ARGS : FALSE), '*::__toString')) {
				$previous = isset($context['e']) && $context['e'] instanceof \Exception ? $context['e'] : NULL;
				$e = new ErrorException($message, 0, $severity, $file, $line, $previous);
				$e->context = $context;
				self::_exceptionHandler($e);
			}

			$e = new ErrorException($message, 0, $severity, $file, $line);
			$e->context = $context;
			throw $e;

		} elseif (($severity & error_reporting()) !== $severity) {
			return FALSE; // calls normal error handler to fill-in error_get_last()

		} elseif (($severity & self::$logSeverity) === $severity) {
			$e = new ErrorException($message, 0, $severity, $file, $line);
			$e->context = $context;
			self::log($e, self::ERROR);
			return NULL;

		} elseif (!self::$productionMode && (is_bool(self::$strictMode) ? self::$strictMode : ((self::$strictMode & $severity) === $severity))) {
			$e = new ErrorException($message, 0, $severity, $file, $line);
			$e->context = $context;
			self::_exceptionHandler($e);
		}

		$message = 'PHP ' . (isset(self::$errorTypes[$severity]) ? self::$errorTypes[$severity] : 'Unknown error') . ": $message";
		$count = & self::getBar()->getPanel(__CLASS__ . ':errors')->data["$file|$line|$message"];

		if ($count++) { // repeated error
			return NULL;

		} elseif (self::$productionMode) {
			self::log("$message in $file:$line", self::ERROR);
			return NULL;

		} else {
			self::fireLog(new ErrorException($message, 0, $severity, $file, $line));
			return self::isHtmlMode() ? NULL : FALSE; // FALSE calls normal error handler
		}
	}


	/********************* useful tools ****************d*g**/


	/**
	 * Dumps information about a variable in readable format.
	 * @tracySkipLocation
	 * @param  mixed  variable to dump
	 * @param  bool   return output instead of printing it? (bypasses $productionMode)
	 * @return mixed  variable itself or dump
	 */
	public static function dump($var, $return = FALSE)
	{
		if ($return) {
			ob_start();
			Dumper::dump($var, array(
				Dumper::DEPTH => self::$maxDepth,
				Dumper::TRUNCATE => self::$maxLen,
			));
			return ob_get_clean();

		} elseif (!self::$productionMode) {
			Dumper::dump($var, array(
				Dumper::DEPTH => self::$maxDepth,
				Dumper::TRUNCATE => self::$maxLen,
				Dumper::LOCATION => self::$showLocation,
			));
		}

		return $var;
	}


	/**
	 * Starts/stops stopwatch.
	 * @param  string  name
	 * @return float   elapsed seconds
	 */
	public static function timer($name = NULL)
	{
		static $time = array();
		$now = microtime(TRUE);
		$delta = isset($time[$name]) ? $now - $time[$name] : 0;
		$time[$name] = $now;
		return $delta;
	}


	/**
	 * Dumps information about a variable in Tracy Debug Bar.
	 * @tracySkipLocation
	 * @param  mixed  variable to dump
	 * @param  string optional title
	 * @param  array  dumper options
	 * @return mixed  variable itself
	 */
	public static function barDump($var, $title = NULL, array $options = NULL)
	{
		if (!self::$productionMode) {
			self::getBar()->getPanel(__CLASS__ . ':dumps')->data[] = array('title' => $title, 'dump' => Dumper::toHtml($var, (array) $options + array(
				Dumper::DEPTH => self::$maxDepth,
				Dumper::TRUNCATE => self::$maxLen,
				Dumper::LOCATION => self::$showLocation,
			)));
		}
		return $var;
	}


	/**
	 * Sends message to FireLogger console.
	 * @param  mixed   message to log
	 * @return bool    was successful?
	 */
	public static function fireLog($message)
	{
		if (!self::$productionMode) {
			return self::getFireLogger()->log($message);
		}
	}


	private static function isHtmlMode()
	{
		return empty($_SERVER['HTTP_X_REQUESTED_WITH'])
			&& PHP_SAPI !== 'cli'
			&& !preg_match('#^Content-Type: (?!text/html)#im', implode("\n", headers_list()));
	}

}
