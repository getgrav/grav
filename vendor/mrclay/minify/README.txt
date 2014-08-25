WELCOME TO MINIFY!

Minify is an HTTP content server. It compresses sources of content 
(usually files), combines the result and serves it with appropriate 
HTTP headers. These headers can allow clients to perform conditional 
GETs (serving content only when clients do not have a valid cache) 
and tell clients to cache the file for a period of time. 
More info: http://code.google.com/p/minify/


WORDPRESS USER?

These WP plugins integrate Minify into WordPress's style and script hooks to
get you set up faster.
  http://wordpress.org/extend/plugins/bwp-minify/
  http://wordpress.org/extend/plugins/w3-total-cache/


INSTALLATION

Place the /min/ directory as a child of your DOCUMENT_ROOT
directory: i.e. you will have: /home/example/www/min

You can see verify that it is working by visiting these two URLs:
  http://example.org/min/?f=min/quick-test.js
  http://example.org/min/?f=min/quick-test.css

If your server supports mod_rewrite, this URL should also work:
  http://example.org/min/f=min/quick-test.js

CONFIGURATION & USAGE

See the MIN.txt file and http://code.google.com/p/minify/wiki/UserGuide

Minify also comes with a URI Builder application that can help you write URLs
for use with Minify or configure groups of files. See here for details:
  http://code.google.com/p/minify/wiki/BuilderApp

The cookbook also provides some more advanced options for minification:
  http://code.google.com/p/minify/wiki/CookBook

UPGRADING

See UPGRADING.txt for instructions.


UNIT TESTING:

1. Place the /min_unit_tests/ directory as a child of your DOCUMENT_ROOT 
directory: i.e. you will have: /home/example/www/min_unit_tests

2. To run unit tests, access: http://example.org/min_unit_tests/test_all.php

(If you wish, the other test_*.php files can be run to test individual
components with more verbose output.)

3. Remove /min_unit_tests/ from your DOCUMENT_ROOT when you are done.


FILE ENCODINGS

Minify *should* work fine with files encoded in UTF-8 or other 8-bit 
encodings like ISO 8859/Windows-1252. By default Minify appends
";charset=utf-8" to the Content-Type headers it sends. 

Leading UTF-8 BOMs are stripped from all sources to prevent 
duplication in output files, and files are converted to Unix newlines.

