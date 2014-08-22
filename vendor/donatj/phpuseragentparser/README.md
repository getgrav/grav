# PHP User Agent Parser

[![Latest Stable Version](https://poser.pugx.org/donatj/phpuseragentparser/v/stable.png)](https://packagist.org/packages/donatj/phpuseragentparser) [![Total Downloads](https://poser.pugx.org/donatj/phpuseragentparser/downloads.png)](https://packagist.org/packages/donatj/phpuseragentparser) [![Latest Unstable Version](https://poser.pugx.org/donatj/phpuseragentparser/v/unstable.png)](https://packagist.org/packages/donatj/phpuseragentparser) [![License](https://poser.pugx.org/donatj/phpuseragentparser/license.png)](https://packagist.org/packages/donatj/phpuseragentparser)
[![Build Status](https://travis-ci.org/donatj/PhpUserAgent.png?branch=master)](https://travis-ci.org/donatj/PhpUserAgent)
[![HHVM Status](http://hhvm.h4cc.de/badge/donatj/phpuseragentparser.png)](http://hhvm.h4cc.de/package/donatj/phpuseragentparser) [![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/donatj/PhpUserAgent/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/donatj/PhpUserAgent/?branch=master)

## What It Is

A simple, streamlined PHP user-agent parser!

Licensed under the MIT license: http://www.opensource.org/licenses/mit-license.php


## Why Use This

You have your choice in user-agent parsers. This one detects **all modern browsers** in a very light, quick, understandable fashion. 
It is less than 150 lines of code, and consists of just two regular expressions!
It can also correctly identify exotic versions of IE others fail on.

It offers 100% unit test coverage, is installable via Composer, and is very easy to use.

## What It Doesn't Do

### OS Versions

User-agent strings **are not** a reliable source of OS Version!

- Many agents simply don't send the information. 
- Others provide varying levels of accuracy.
- Parsing Windows versions alone almost nearly doubles the size of the code.

I'm much more interested in keeping this thing *tiny* and accurate than adding niché features and would rather focus on things that can be **done well**.

All that said, there is the start of a [branch to do it](https://github.com/donatj/PhpUserAgent/tree/os_version_detection) I created for a client if you want to poke it, I update it from time to time, but frankly if you need to *reliably detect OS Version*, using user-agent isn't the way to do it. I'd go with JavaScript.

## Requirements

  - PHP 5.3.0+

## Installing

PHP User Agent is available through Packagist via Composer.

```json
{
	"require": {
		"donatj/phpuseragentparser": "*"
	}
}
```

## Sample Usage

```php
$ua_info = parse_user_agent();
/*
array(
	'platform' => '[Detected Platform]',
	'browser'  => '[Detected Browser]',
	'version'  => '[Detected Browser Version]',
);
*/
```

## Currently Detected Platforms

- Desktop
	- Windows
	- Linux
	- Macintosh
	- Chrome OS
- Mobile
	- Android
	- iPhone
	- iPad
	- Windows Phone OS
	- Kindle
	- Kindle Fire
	- BlackBerry
	- Playbook
- Console
	- Nintendo 3DS
	- Nintendo Wii
	- Nintendo WiiU
	- PlayStation 3
	- PlayStation 4
	- PlayStation Vita
	- Xbox 360
	- Xbox One

## Currently Detected Browsers

- Android Browser
- BlackBerry Browser
- Camino
- Kindle / Silk
- Firefox / Iceweasel
- Safari
- Internet Explorer
- IEMobile
- Chrome
- Opera
- Midori
- Lynx
- Wget
- Curl



More information is available at [Donat Studios](http://donatstudios.com/PHP-Parser-HTTP_USER_AGENT).
