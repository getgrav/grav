<!DOCTYPE html><link rel="stylesheet" href="assets/style.css">

<h1>Tracy Dumper demo</h1>

<?php

require __DIR__ . '/../src/tracy.php';

use Tracy\Debugger;


Debugger::enable(Debugger::DEVELOPMENT);

class Test
{
	public $x = array(10, NULL);

	private $y = 'hello';

	protected $z = 30;
}

$arr = array(10, 20.2, TRUE, NULL, 'hello', (object) NULL, array(), fopen(__FILE__, 'r'));

$obj = new Test;


dump('<a href="#">test</a>');

dump($arr);

dump($obj);


echo "<h2>With location</h2>\n";

Debugger::$showLocation = TRUE;

dump($arr);
