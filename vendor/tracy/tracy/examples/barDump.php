<!DOCTYPE html><link rel="stylesheet" href="assets/style.css">

<style> html { background: url(assets/arrow.png) no-repeat bottom right; height: 100%; } </style>

<h1>Tracy Debug Bar demo</h1>

<p>You can dump variables to bar in rightmost bottom egde.</p>

<?php

require __DIR__ . '/../src/tracy.php';

use Tracy\Debugger;

Debugger::enable();

$arr = array(10, 20.2, TRUE, NULL, 'hello', (object) NULL, array());


Debugger::barDump(get_defined_vars());

Debugger::barDump($arr, 'The Array');

Debugger::barDump('<a href="#">test</a>', 'String');
