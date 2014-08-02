<?php

/**
 * This file is part of the Tracy (http://tracy.nette.org)
 * Copyright (c) 2004 David Grudl (http://davidgrudl.com)
 */

if (!function_exists('dump')) {
	/**
	 * Tracy\Debugger::dump() shortcut.
	 * @tracySkipLocation
	 */
	function dump($var)
	{
		foreach (func_get_args() as $arg) {
			Tracy\Debugger::dump($arg);
		}
		return $var;
	}
}
