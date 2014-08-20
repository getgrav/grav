<?php

/**
 * This file is part of the Tracy (http://tracy.nette.org)
 * Copyright (c) 2004 David Grudl (http://davidgrudl.com)
 */

namespace Tracy;

use Tracy;


/**
 * @author     David Grudl
 */
class OutputDebugger
{
	const BOM = "\xEF\xBB\xBF";

	/** @var array of [file, line, output] */
	private $list = array();

	/** @var string */
	private $lastFile;


	public static function enable()
	{
		$me = new static;
		$me->start();
	}


	public function start()
	{
		foreach (get_included_files() as $file) {
			if (fread(fopen($file, 'r'), 3) === self::BOM) {
				$this->list[] = array($file, 1, self::BOM);
			}
		}
		ob_start(array($this, 'handler'), PHP_VERSION_ID >= 50400 ? 1 : 2);
	}


	public function handler($s, $phase)
	{
		$trace = debug_backtrace(FALSE);
		if (isset($trace[0]['file'], $trace[0]['line'])) {
			if ($this->lastFile === $trace[0]['file']) {
				$this->list[count($this->list) - 1][2] .= $s;
			} else {
				$this->list[] = array($this->lastFile = $trace[0]['file'], $trace[0]['line'], $s);
			}
		}
		if ($phase === PHP_OUTPUT_HANDLER_FINAL) {
			return $this->renderHtml();
		}
	}


	private function renderHtml()
	{
		$res = '<style>code, pre {white-space:nowrap} a {text-decoration:none} pre {color:gray;display:inline} big {color:red}</style><code>';
		foreach ($this->list as $item) {
			list($file, $line, $s) = $item;
			$res .= Helpers::editorLink($item[0], $item[1]) . ' '
				. str_replace(self::BOM, '<big>BOM</big>', Dumper::toHtml($item[2])) . "<br>\n";
		}
		return $res . '</code>';
	}

}
