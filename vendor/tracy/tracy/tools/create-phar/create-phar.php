<?php

// creates tracy.phar
if (!class_exists('Phar') || ini_get('phar.readonly')) {
	echo "Enable Phar extension and set directive 'phar.readonly=off'.\n";
	die(1);
}

@unlink('tracy.phar'); // @ - file may not exist

$phar = new Phar('tracy.phar');
$phar->setStub("<?php
require 'phar://' . __FILE__ . '/tracy.php';
__HALT_COMPILER();
");

$phar->startBuffering();
foreach ($iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/../../src', RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
	echo "adding: {$iterator->getSubPathname()}\n";
	$phar[$iterator->getSubPathname()] = php_strip_whitespace($file);
}

$phar->stopBuffering();
$phar->compressFiles(Phar::GZ);

echo "OK\n";
