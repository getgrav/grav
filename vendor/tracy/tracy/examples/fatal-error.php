<!DOCTYPE html><link rel="stylesheet" href="assets/style.css">

<h1>Tracy Fatal Error demo</h1>

<?php

require __DIR__ . '/../src/tracy.php';

use Tracy\Debugger;


Debugger::enable();



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
	missing_funcion();
}


first(10, 'any string');
