<?php ob_start(); // needed by FireLogger ?>

<!DOCTYPE html><link rel="stylesheet" href="assets/style.css">

<h1>FireLogger demo</h1>

<p>Requires Firefox, Firebug and <a href="http://firelogger.binaryage.com">FireLogger</a>.</p>

<?php

require __DIR__ . '/../src/tracy.php';

use Tracy\Debugger;


$arr = array(10, 20, array('key1' => 'val1', 'key2' => TRUE));

// will show in FireLogger tab in Firebug
Debugger::fireLog('Hello World');
Debugger::fireLog($arr);


function first($arg1, $arg2)
{
	second(TRUE, FALSE);
}



function second($arg1, $arg2)
{
	third(array(1, 2, 3));
}


function third($arg1)
{
	throw new Exception('The my exception', 123);
}

try {
	first(10, 'any string');
} catch (Exception $e) {
	Debugger::fireLog($e);
}
