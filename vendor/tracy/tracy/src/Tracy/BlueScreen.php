<?php

/**
 * This file is part of the Tracy (http://tracy.nette.org)
 * Copyright (c) 2004 David Grudl (http://davidgrudl.com)
 */

namespace Tracy;

use Tracy;


/**
 * Red BlueScreen.
 *
 * @author     David Grudl
 */
class BlueScreen
{
	/** @var string[] */
	public $info = array();

	/** @var callable[] */
	private $panels = array();

	/** @var string[] paths to be collapsed in stack trace (e.g. core libraries) */
	public $collapsePaths = array(__DIR__);


	/**
	 * Add custom panel.
	 * @param  callable
	 * @return self
	 */
	public function addPanel($panel)
	{
		if (!in_array($panel, $this->panels, TRUE)) {
			$this->panels[] = $panel;
		}
		return $this;
	}


	/**
	 * Renders blue screen.
	 * @param  \Exception
	 * @return void
	 */
	public function render(\Exception $exception)
	{
		$panels = $this->panels;
		$info = array_filter($this->info);
		require __DIR__ . '/templates/bluescreen.phtml';
	}


	/**
	 * Returns syntax highlighted source code.
	 * @param  string
	 * @param  int
	 * @param  int
	 * @return string
	 */
	public static function highlightFile($file, $line, $lines = 15, array $vars = NULL)
	{
		$source = @file_get_contents($file); // intentionally @
		if ($source) {
			$source = static::highlightPhp($source, $line, $lines, $vars);
			if ($editor = Helpers::editorUri($file, $line)) {
				$source = substr_replace($source, ' data-tracy-href="' . htmlspecialchars($editor) . '"', 4, 0);
			}
			return $source;
		}
	}


	/**
	 * Returns syntax highlighted source code.
	 * @param  string
	 * @param  int
	 * @param  int
	 * @return string
	 */
	public static function highlightPhp($source, $line, $lines = 15, array $vars = NULL)
	{
		if (function_exists('ini_set')) {
			ini_set('highlight.comment', '#998; font-style: italic');
			ini_set('highlight.default', '#000');
			ini_set('highlight.html', '#06B');
			ini_set('highlight.keyword', '#D24; font-weight: bold');
			ini_set('highlight.string', '#080');
		}

		$source = str_replace(array("\r\n", "\r"), "\n", $source);
		$source = explode("\n", highlight_string($source, TRUE));
		$out = $source[0]; // <code><span color=highlight.html>
		$source = str_replace('<br />', "\n", $source[1]);
		$out .= static::highlightLine($source, $line, $lines);

		if ($vars) {
			$out = preg_replace_callback('#">\$(\w+)(&nbsp;)?</span>#', function($m) use ($vars) {
				return array_key_exists($m[1], $vars)
					? '" title="' . str_replace('"', '&quot;', trim(strip_tags(Dumper::toHtml($vars[$m[1]])))) . $m[0]
					: $m[0];
			}, $out);
		}

		return "<pre class='php'><div>$out</div></pre>";
	}



	/**
	 * Returns highlighted line in HTML code.
	 * @return string
	 */
	public static function highlightLine($html, $line, $lines = 15)
	{
		$source = explode("\n", "\n" . str_replace("\r\n", "\n", $html));
		$out = '';
		$spans = 1;
		$start = $i = max(1, $line - floor($lines * 2/3));
		while (--$i >= 1) { // find last highlighted block
			if (preg_match('#.*(</?span[^>]*>)#', $source[$i], $m)) {
				if ($m[1] !== '</span>') {
					$spans++;
					$out .= $m[1];
				}
				break;
			}
		}

		$source = array_slice($source, $start, $lines, TRUE);
		end($source);
		$numWidth = strlen((string) key($source));

		foreach ($source as $n => $s) {
			$spans += substr_count($s, '<span') - substr_count($s, '</span');
			$s = str_replace(array("\r", "\n"), array('', ''), $s);
			preg_match_all('#<[^>]+>#', $s, $tags);
			if ($n == $line) {
				$out .= sprintf(
					"<span class='highlight'>%{$numWidth}s:    %s\n</span>%s",
					$n,
					strip_tags($s),
					implode('', $tags[0])
				);
			} else {
				$out .= sprintf("<span class='line'>%{$numWidth}s:</span>    %s\n", $n, $s);
			}
		}
		$out .= str_repeat('</span>', $spans) . '</code>';
		return $out;
	}


	/**
	 * Should a file be collapsed in stack trace?
	 * @param  string
	 * @return bool
	 */
	public function isCollapsed($file)
	{
		foreach ($this->collapsePaths as $path) {
			if (strpos(strtr($file, '\\', '/'), strtr("$path/", '\\', '/')) === 0) {
				return TRUE;
			}
		}
		return FALSE;
	}

}
