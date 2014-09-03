<?php

/**
 * This file is part of the Tracy (http://tracy.nette.org)
 * Copyright (c) 2004 David Grudl (http://davidgrudl.com)
 */

namespace Tracy;

use Tracy;


/**
 * Logger.
 *
 * @author     David Grudl
 */
class Logger
{
	const DEBUG = 'debug',
		INFO = 'info',
		WARNING = 'warning',
		ERROR = 'error',
		EXCEPTION = 'exception',
		CRITICAL = 'critical';

	/** @var int interval for sending email is 2 days */
	public $emailSnooze = 172800;

	/** @var callable handler for sending emails */
	public $mailer = array(__CLASS__, 'defaultMailer');

	/** @var string name of the directory where errors should be logged; FALSE means that logging is disabled */
	public $directory;

	/** @var string|array email or emails to which send error notifications */
	public $email;


	/**
	 * Logs message or exception to file and sends email notification.
	 * @param  string|array
	 * @param  int   one of constant Debugger::INFO, WARNING, ERROR (sends email), EXCEPTION (sends email), CRITICAL (sends email)
	 * @return void
	 */
	public function log($message, $priority = NULL)
	{
		if (!is_dir($this->directory)) {
			throw new \RuntimeException("Directory '$this->directory' is not found or is not directory.");
		}

		if (is_array($message)) {
			$message = implode(' ', $message);
		}
		$message = preg_replace('#\s*\r?\n\s*#', ' ', trim($message));
		$file = $this->directory . '/' . strtolower($priority ?: self::INFO) . '.log';
		if (!@file_put_contents($file, $message . PHP_EOL, FILE_APPEND | LOCK_EX)) {
			throw new \RuntimeException("Unable to write to log file '$file'. Is directory writable?");
		}

		if (in_array($priority, array(self::ERROR, self::EXCEPTION, self::CRITICAL), TRUE) && $this->email && $this->mailer
			&& @filemtime($this->directory . '/email-sent') + $this->emailSnooze < time() // @ - file may not exist
			&& @file_put_contents($this->directory . '/email-sent', 'sent') // @ - file may not be writable
		) {
			call_user_func($this->mailer, $message, implode(', ', (array) $this->email));
		}
	}


	/**
	 * Default mailer.
	 * @param  string
	 * @param  string
	 * @return void
	 * @internal
	 */
	public static function defaultMailer($message, $email)
	{
		$host = preg_replace('#[^\w.-]+#', '', isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : php_uname('n'));
		$parts = str_replace(
			array("\r\n", "\n"),
			array("\n", PHP_EOL),
			array(
				'headers' => implode("\n", array(
					"From: noreply@$host",
					'X-Mailer: Tracy',
					'Content-Type: text/plain; charset=UTF-8',
					'Content-Transfer-Encoding: 8bit',
				)) . "\n",
				'subject' => "PHP: An error occurred on the server $host",
				'body' => "[" . @date('Y-m-d H:i:s') . "] $message", // @ - timezone may not be set
			)
		);

		mail($email, $parts['subject'], $parts['body'], $parts['headers']);
	}

}
