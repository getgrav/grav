# PHP User Agent

Browser detection in PHP5.
Uses a simple and fast algorithm to recognize major browsers.

## Overview

``` php
$userAgent = new phpUserAgent();

$userAgent->getBrowserName()      // firefox
$userAgent->getBrowserVersion()   // 3.6
$userAgent->getOperatingSystem()  // linux
$userAgent->getEngine()           // gecko
```

### Why you should use it

PHP provides a native function to detect user browser: [get_browser()](http://us2.php.net/manual/en/function.get-browser.php).
get_browser() requires the "browscap.ini" file which is 300KB+.
Loading and processing this file impact script performance.
And sometimes, the production server just doesn't provide browscap.ini.

Although get_browser() surely provides excellent detection results, in most
cases a much simpler method can be just as effective.
php-user-agent has the advantage of being compact and easy to extend.
It is performant as well, since it doesn't do any iteration or recursion.

## Usage

``` php
// include classes or rely on Composer autoloader
require_once '/path/to/php-user-agent/phpUserAgent.php';
require_once '/path/to/php-user-agent/phpUserAgentStringParser.php';

// Create a user agent
$userAgent = new phpUserAgent();

// Interrogate the user agent
$userAgent->getBrowserName()      // firefox
$userAgent->getBrowserVersion()   // 3.6
$userAgent->getOperatingSystem()  // linux
$userAgent->getEngine()           // gecko
```

## Advanced

### Custom user agent string

When you create a phpUserAgent object, the current user agent string is used.
You can specify another user agent string:

``` php
// use another user agent string
$userAgent = new phpUserAgent('msnbot/2.0b (+http://search.msn.com/msnbot.htm)');
$userAgent->getBrowserName() // msnbot

// use current user agent string
$userAgent = new phpUserAgent($_SERVER['HTTP_USER_AGENT');
// this is equivalent to:
$userAgent = new phpUserAgent();
```

### Custom parser class

By default, phpUserAgentStringParser is used to analyse the user agent string.
You can replace the parser instance and customize it to match your needs:

``` php
// create a custom user agent string parser
class myUserAgentStringParser extends phpUserAgentStringParser
{
  // override methods
}

// inject the custom parser when creating a user agent:
$userAgent = new phpUserAgent(null, new myUserAgentStringParser());
```

## Run tests

You can run the unit tests on your server:

``` bash
$ php prove.php
```

## Contribute

If you found a browser of operating system this library fails to recognize,
feel free to submit an issue. Please provide the user agent string.
And well, if you also want to provide the patch, it's even better.
