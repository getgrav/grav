<?php

/**
 * This file is part of the Tracy (http://tracy.nette.org)
 * Copyright (c) 2004 David Grudl (http://davidgrudl.com)
 */

namespace Tracy;

use Tracy;


/**
 * Dumps a variable.
 *
 * @author     David Grudl
 */
class Dumper
{
	const DEPTH = 'depth', // how many nested levels of array/object properties display (defaults to 4)
		TRUNCATE = 'truncate', // how truncate long strings? (defaults to 150)
		COLLAPSE = 'collapse', // always collapse? (defaults to false)
		COLLAPSE_COUNT = 'collapsecount', // how big array/object are collapsed? (defaults to 7)
		LOCATION = 'location'; // show location string? (defaults to false)

	/** @var array */
	public static $terminalColors = array(
		'bool' => '1;33',
		'null' => '1;33',
		'number' => '1;32',
		'string' => '1;36',
		'array' => '1;31',
		'key' => '1;37',
		'object' => '1;31',
		'visibility' => '1;30',
		'resource' => '1;37',
		'indent' => '1;30',
	);

	/** @var array */
	public static $resources = array(
		'stream' => 'stream_get_meta_data',
		'stream-context' => 'stream_context_get_options',
		'curl' => 'curl_getinfo',
	);


	/**
	 * Dumps variable to the output.
	 * @return mixed  variable
	 */
	public static function dump($var, array $options = NULL)
	{
		if (PHP_SAPI !== 'cli' && !preg_match('#^Content-Type: (?!text/html)#im', implode("\n", headers_list()))) {
			echo self::toHtml($var, $options);
		} elseif (self::detectColors()) {
			echo self::toTerminal($var, $options);
		} else {
			echo self::toText($var, $options);
		}
		return $var;
	}


	/**
	 * Dumps variable to HTML.
	 * @return string
	 */
	public static function toHtml($var, array $options = NULL)
	{
		$options = (array) $options + array(
			self::DEPTH => 4,
			self::TRUNCATE => 150,
			self::COLLAPSE => FALSE,
			self::COLLAPSE_COUNT => 7,
			self::LOCATION => FALSE,
		);
		list($file, $line, $code) = $options[self::LOCATION] ? self::findLocation() : NULL;
		return '<pre class="tracy-dump"'
			. ($file ? Helpers::createHtml(' title="%in file % on line %" data-tracy-href="%"', "$code\n", $file, $line, Helpers::editorUri($file, $line)) . '>' : '>')
			. self::dumpVar($var, $options)
			. ($file ? '<small>in ' . Helpers::editorLink($file, $line) . '</small>' : '')
			. "</pre>\n";
	}


	/**
	 * Dumps variable to plain text.
	 * @return string
	 */
	public static function toText($var, array $options = NULL)
	{
		return htmlspecialchars_decode(strip_tags(self::toHtml($var, $options)), ENT_QUOTES);
	}


	/**
	 * Dumps variable to x-terminal.
	 * @return string
	 */
	public static function toTerminal($var, array $options = NULL)
	{
		return htmlspecialchars_decode(strip_tags(preg_replace_callback('#<span class="tracy-dump-(\w+)">|</span>#', function($m) {
			return "\033[" . (isset($m[1], Dumper::$terminalColors[$m[1]]) ? Dumper::$terminalColors[$m[1]] : '0') . 'm';
		}, self::toHtml($var, $options))), ENT_QUOTES);
	}


	/**
	 * Internal toHtml() dump implementation.
	 * @param  mixed  variable to dump
	 * @param  array  options
	 * @param  int    current recursion level
	 * @return string
	 */
	private static function dumpVar(& $var, array $options, $level = 0)
	{
		if (method_exists(__CLASS__, $m = 'dump' . gettype($var))) {
			return self::$m($var, $options, $level);
		} else {
			return "<span>unknown type</span>\n";
		}
	}


	private static function dumpNull()
	{
		return "<span class=\"tracy-dump-null\">NULL</span>\n";
	}


	private static function dumpBoolean(& $var)
	{
		return '<span class="tracy-dump-bool">' . ($var ? 'TRUE' : 'FALSE') . "</span>\n";
	}


	private static function dumpInteger(& $var)
	{
		return "<span class=\"tracy-dump-number\">$var</span>\n";
	}


	private static function dumpDouble(& $var)
	{
		$var = is_finite($var)
			? ($tmp = json_encode($var)) . (strpos($tmp, '.') === FALSE ? '.0' : '')
			: var_export($var, TRUE);
		return "<span class=\"tracy-dump-number\">$var</span>\n";
	}


	private static function dumpString(& $var, $options)
	{
		return '<span class="tracy-dump-string">'
			. self::encodeString($options[self::TRUNCATE] && strlen($var) > $options[self::TRUNCATE] ? substr($var, 0, $options[self::TRUNCATE]) . ' ... ' : $var)
			. '</span>' . (strlen($var) > 1 ? ' (' . strlen($var) . ')' : '') . "\n";
	}


	private static function dumpArray(& $var, $options, $level)
	{
		static $marker;
		if ($marker === NULL) {
			$marker = uniqid("\x00", TRUE);
		}

		$out = '<span class="tracy-dump-array">array</span> (';

		if (empty($var)) {
			return $out . ")\n";

		} elseif (isset($var[$marker])) {
			return $out . (count($var) - 1) . ") [ <i>RECURSION</i> ]\n";

		} elseif (!$options[self::DEPTH] || $level < $options[self::DEPTH]) {
			$collapsed = $level ? count($var) >= $options[self::COLLAPSE_COUNT] : $options[self::COLLAPSE];
			$out = '<span class="tracy-toggle' . ($collapsed ? ' tracy-collapsed' : '') . '">' . $out . count($var) . ")</span>\n<div" . ($collapsed ? ' class="tracy-collapsed"' : '') . '>';
			$var[$marker] = TRUE;
			foreach ($var as $k => & $v) {
				if ($k !== $marker) {
					$out .= '<span class="tracy-dump-indent">   ' . str_repeat('|  ', $level) . '</span>'
						. '<span class="tracy-dump-key">' . (preg_match('#^\w+\z#', $k) ? $k : self::encodeString($k)) . '</span> => '
						. self::dumpVar($v, $options, $level + 1);
				}
			}
			unset($var[$marker]);
			return $out . '</div>';

		} else {
			return $out . count($var) . ") [ ... ]\n";
		}
	}


	private static function dumpObject(& $var, $options, $level)
	{
		if ($var instanceof \Closure) {
			$rc = new \ReflectionFunction($var);
			$fields = array();
			foreach ($rc->getParameters() as $param) {
				$fields[] = '$' . $param->getName();
			}
			$fields = array(
				'file' => $rc->getFileName(), 'line' => $rc->getStartLine(),
				'variables' => $rc->getStaticVariables(), 'parameters' => implode(', ', $fields)
			);
		} elseif ($var instanceof \SplFileInfo) {
			$fields = array('path' => $var->getPathname());
		} elseif ($var instanceof \SplObjectStorage) {
			$fields = array();
			foreach (clone $var as $obj) {
				$fields[] = array('object' => $obj, 'data' => $var[$obj]);
			}
		} else {
			$fields = (array) $var;
		}

		static $list = array();
		$rc = $var instanceof \Closure ? new \ReflectionFunction($var) : new \ReflectionClass($var);
		$out = '<span class="tracy-dump-object"'
			. ($options[self::LOCATION] && ($editor = Helpers::editorUri($rc->getFileName(), $rc->getStartLine())) ? ' data-tracy-href="' . htmlspecialchars($editor) . '"' : '')
			. '>' . get_class($var) . '</span> <span class="tracy-dump-hash">#' . substr(md5(spl_object_hash($var)), 0, 4) . '</span>';

		if (empty($fields)) {
			return $out . "\n";

		} elseif (in_array($var, $list, TRUE)) {
			return $out . " { <i>RECURSION</i> }\n";

		} elseif (!$options[self::DEPTH] || $level < $options[self::DEPTH] || $var instanceof \Closure) {
			$collapsed = $level ? count($fields) >= $options[self::COLLAPSE_COUNT] : $options[self::COLLAPSE];
			$out = '<span class="tracy-toggle' . ($collapsed ? ' tracy-collapsed' : '') . '">' . $out . "</span>\n<div" . ($collapsed ? ' class="tracy-collapsed"' : '') . '>';
			$list[] = $var;
			foreach ($fields as $k => & $v) {
				$vis = '';
				if ($k[0] === "\x00") {
					$vis = ' <span class="tracy-dump-visibility">' . ($k[1] === '*' ? 'protected' : 'private') . '</span>';
					$k = substr($k, strrpos($k, "\x00") + 1);
				}
				$out .= '<span class="tracy-dump-indent">   ' . str_repeat('|  ', $level) . '</span>'
					. '<span class="tracy-dump-key">' . (preg_match('#^\w+\z#', $k) ? $k : self::encodeString($k)) . "</span>$vis => "
					. self::dumpVar($v, $options, $level + 1);
			}
			array_pop($list);
			return $out . '</div>';

		} else {
			return $out . " { ... }\n";
		}
	}


	private static function dumpResource(& $var, $options, $level)
	{
		$type = get_resource_type($var);
		$out = '<span class="tracy-dump-resource">' . htmlSpecialChars($type) . ' resource</span>';
		if (isset(self::$resources[$type])) {
			$out = "<span class=\"tracy-toggle tracy-collapsed\">$out</span>\n<div class=\"tracy-collapsed\">";
			foreach (call_user_func(self::$resources[$type], $var) as $k => $v) {
				$out .= '<span class="tracy-dump-indent">   ' . str_repeat('|  ', $level) . '</span>'
					. '<span class="tracy-dump-key">' . htmlSpecialChars($k) . "</span> => " . self::dumpVar($v, $options, $level + 1);
			}
			return $out . '</div>';
		}
		return "$out\n";
	}


	private static function encodeString($s)
	{
		static $table;
		if ($table === NULL) {
			foreach (array_merge(range("\x00", "\x1F"), range("\x7F", "\xFF")) as $ch) {
				$table[$ch] = '\x' . str_pad(dechex(ord($ch)), 2, '0', STR_PAD_LEFT);
			}
			$table["\\"] = '\\\\';
			$table["\r"] = '\r';
			$table["\n"] = '\n';
			$table["\t"] = '\t';
		}

		if (preg_match('#[^\x09\x0A\x0D\x20-\x7E\xA0-\x{10FFFF}]#u', $s) || preg_last_error()) {
			$s = strtr($s, $table);
		}
		return '"' . htmlSpecialChars($s, ENT_NOQUOTES) . '"';
	}


	/**
	 * Finds the location where dump was called.
	 * @return array [file, line, code]
	 */
	private static function findLocation()
	{
		foreach (debug_backtrace(PHP_VERSION_ID >= 50306 ? DEBUG_BACKTRACE_IGNORE_ARGS : FALSE) as $item) {
			if (isset($item['class']) && $item['class'] === __CLASS__) {
				$location = $item;
				continue;
			} elseif (isset($item['function'])) {
				try {
					$reflection = isset($item['class'])
						? new \ReflectionMethod($item['class'], $item['function'])
						: new \ReflectionFunction($item['function']);
					if (preg_match('#\s@tracySkipLocation\s#', $reflection->getDocComment())) {
						$location = $item;
						continue;
					}
				} catch (\ReflectionException $e) {}
			}
			break;
		}

		if (isset($location['file'], $location['line']) && is_file($location['file'])) {
			$lines = file($location['file']);
			$line = $lines[$location['line'] - 1];
			return array(
				$location['file'],
				$location['line'],
				trim(preg_match('#\w*dump(er::\w+)?\(.*\)#i', $line, $m) ? $m[0] : $line)
			);
		}
	}


	/**
	 * @return bool
	 */
	private static function detectColors()
	{
		return self::$terminalColors &&
			(getenv('ConEmuANSI') === 'ON'
			|| getenv('ANSICON') !== FALSE
			|| (defined('STDOUT') && function_exists('posix_isatty') && posix_isatty(STDOUT)));
	}

}
