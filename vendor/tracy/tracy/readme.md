[Tracy](http://tracy.nette.org) - PHP debugger
==============================================

[![Downloads this Month](https://img.shields.io/packagist/dm/tracy/tracy.svg)](https://packagist.org/packages/tracy/tracy)
[![Build Status](https://travis-ci.org/nette/tracy.svg?branch=v2.2)](https://travis-ci.org/nette/tracy)

Tracy library is a useful PHP everyday programmer's helper. It helps you to:

- quickly detect and correct errors
- log errors
- dump variables
- measure the time


PHP is a perfect language for making hardly detectable errors because it gives a great flexibility to programmers. Tracy\Debugger is more valuable because of that. It is a ultimate tool among the diagnostic ones.
If you are meeting Tracy the first time, believe me, your life starts to be divided one before the Tracy and the one with her.
Welcome to the good part!

The best way how to install Tracy is to [download a latest package](https://github.com/nette/tracy/releases) or use a Composer:

```
php composer.phar require tracy/tracy
```

It is easy to activate Tracy. Preferably immediately after library loading (using `require 'src/tracy.php'` or via Composer) add code:

```php
use Tracy\Debugger;

Debugger::enable();
```

The first thing you notice on the web site is a Debugger Bar.


Debugger Bar
------------

The Debugger Bar is a floating panel. It is displayed in the bottom right corner of a page. You can move it by the mouse. It will remember its position after the page reloading.

![Debugger-Bar](http://nette.github.io/tracy/images/tracy-bar.png)

You can add other useful panels into the Debugger Bar.


Visualization of errors and exceptions
--------------------------------------

Surely, you know how PHP reports errors: there is something like this in the page source code:

```pre
<b>Parse error</b>:  syntax error, unexpected T_STRING in <b>SignPresenter.php</b> on line <b>6</b>
```

or uncaught exception:

```pre
<b>Fatal error</b>:  Uncaught exception 'Nette\Security\AuthenticationException' with message 'The password is incorrect.' in W:\examples\CD-collection\app\model\Authenticator.php:38
Stack trace:
#0 W:\Nette\Security\User.php(87): Authenticator-&gt;authenticate(Array)
#1 W:\examples\CD-collection\app\presenters\SignPresenter.php(36): Nette\Security\User-&gt;login('demo', 'xx')
#2 [internal function]: SignPresenter-&gt;signInFormSucceeded(Object(Nette\Application\UI\Form))
#3 W:\Nette\Utils\Callback.php(38): call_user_func_array(Array, Array)
#4 [internal function]: Nette\Utils\{closure}(Object(Nette\Application\UI\Form))
#5 W:\Nette\Utils\Callback.php(50): call_user_func_array(Object(Closure), Array)
#6 W:\Nette\Forms\Form.php(412): Nette\Utils\Callback::invoke(Object(Closure), Object(Nette\Application\UI\Form))
#7 W:\Nette\Application\UI\Form.php(129): Nette\Forms\Form-&gt;fireEvents()
#8 W:\Nette\Application\UI\Presenter.php(320): Nette\Application\UI in <b>W:\examples\CD-collection\app\model\Authenticator.php</b> on line <b>38</b><br />
```

It is not so easy to navigate throw this output. If you enable the Tracy, errors or exceptions are displayed completely in a different form:

[![Uncaught exception rendered by Tracy](http://nette.github.io/tracy/images/tracy-exception.png)](http://nette.github.io/tracy/tracy-exception.html)

The error message literally screams. You can see a part of the source code with the highlighted line where the error occurred. A message clearly explains an error. The entire site is [interactive, try it](http://nette.github.io/tracy/tracy-exception.html).

And you know what? A fatal errors are captured and displayed in the same way. No need to install any extension (click for live example):

[![Fatal error rendered by Tracy](http://nette.github.io/tracy/images/tracy-error.png)](http://nette.github.io/tracy/tracy-error.html)

Errors like a typo in a variable name or an attempt to open a nonexistent file generate reports of E_NOTICE or E_WARNING level. These can be easily overlooked and/or can be completly hidden in a web page graphic layout. Let Tracy to manage them:

![Notice rendered by Tracy](http://nette.github.io/tracy/images/tracy-notice2.png)

Or they may be displayed like errors:

```php
Debugger::$strictMode = TRUE;
```

[![Notice rendered by Tracy](http://nette.github.io/tracy/images/tracy-notice.png)](http://nette.github.io/tracy/tracy-notice.html)


Production mode and error logging
---------------------------------

As you can see, Tracy is quite eloquent. It is appreciated in a development environment, but on a production server it would cause disaster. There cannot be listed any debugging information. Therefore Tracy has an environment autodetection and logging functionality. Instead of showing self, Tracy stores information into log file and shows a server error with user-comprehensible message to visitor:

![Server Error 500](http://nette.github.io/tracy/images/tracy-error2.png)

An output mode suppresses all debugging information which are sent out via `Debugger::dump()` or `Debugger::fireLog()`, and of course all error messages generated by PHP. So, if you forget `Debugger::dump($obj)` in the source code, you do not have to worry about it on a production server. Nothing will be listed.

The output mode is switched by the first parameter of `Debugger::enable()`. You can specify either a constant `Debugger::PRODUCTION` or `Debugger::DEVELOPMENT`.

If it is not specified, the default value `Debugger::DETECT` is used. In this case the system detects a server by IP address. The production mode is choosed if an application is accessed via public IP address. A local IP address leads to development mode. It is not necessary to set the mode in most cases. The mode is correctly recognized when you are launching the application on your local server or in production.

In the production mode, Tracy automatically capture all errors and exceptions into a text log. Unless you specify otherwise, it will be a set of log/error.log. This error logging is extremely useful. Imagine, that all users of your application are actually betatesters. They are doing cutting-edge work for free when hunting bugs and you would be silly if you threw away their valuable reports to a recycle bin unnoticed.

If you need a own messages logging or you caught an exception, use the method `log()`:

```php
Debugger::log('Unexpected error'); // text message

try {
	criticalOperation();
} catch (Exception $e) {
	Debugger::log($e); // log exception
	// or
	Debugger::log($e, Debugger::ERROR) // sends also an email notification
}
```

A directory for errors logging can be set by the second parameter of enable() method:

```php
Debugger::enable(Debugger::DETECT, __DIR__ . '/mylog');
```

For a real professional is the error log a key source of information and he wants to be noticed about any new error immediately. Tracy helps him. She is capable to send an email on every new error record. The variable $email identify where to send the e-mails:

```php
Debugger::$email = 'admin@example.com';
```

To protect your e-mail box from flood, Tracy sends **the only one message** and creates a file `email-sent`. When developer receives an e-mail notification, he checks the log, corrects application and deletes the monitoring file. It activates the e-mails sending back.


Variables dumping
-----------------

Each debugging developer is a good friend with the function `var_dump` which lists a content of a variable in detail. Unfortunately, the output is without HTML formatting and decants dump into a single line of HTML code. Not to mention an context escaping. It is necessary replace the `var_dump` by a handier function. That is just `Debugger::dump()`.

```php
$arr = array(10, 20.2, TRUE, NULL, 'hello');

Debugger::dump($arr);
// including namespace Tracy\Debugger::dump($arr);
```

generates the output:

![dump](http://nette.github.io/tracy/images/tracy-dump.png)

You can also change the nesting depth by `Debugger::$maxDepth` and displayed strings length by `Debugger::$maxLen`. Naturally, lower values accelerates Tracy rendering.

```php
Debugger::$maxDepth = 2; // default: 3
Debugger::$maxLen = 50; // default: 150
```

Timing
------

An another useful tool is a debugger stopwatch with a precision of microseconds:

```php
Debugger::timer();

// sweet dreams my cherrie
sleep(2);

$elapsed = Debugger::timer();
// $elapsed = 2
```

Multiple measurements can be achieved by an optional parameter.

```php
Debugger::timer('page-generating');
// some code

Debugger::timer('rss-generating');
// some code

$rssElapsed = Debugger::timer('rss-generating');
$pageElapsed = Debugger::timer('page-generating');
```

```php
Debugger::timer(); // runs the timer

... // some difficult operation

echo Debugger::timer(); // elapsed time in seconds
```


Firebug and FireLogger
----------------------

You cannot send debugging information to the browser window always. This applies to Ajax requests, or generating XML files to output. In such cases, you can send the messages by a separate channel into Firebug. Error, Notice and Warning levels are sent to Firebug window automatically. It is also possible to log suppressed exceptions in running application when attention to them is important.

How to do it?

- download and run Firefox browser
- download extension [Firebug](http://www.getfirebug.com)
- download extension  [FireLogger](http://firelogger.binaryage.com)
- restart browser, turn on Firebug (key F12) and enable network (Net) and Logger panels

... Open our utility panel and click on the Console. Ha!, error message has been moved there.

Because the Tracy\Debugger communicates with Firebug via an HTTP headers, you must call the logging function before the PHP script sends anything to the output. It is also possible to enable the output buffering and delay the output.

```php
use Tracy\Debugger;

Debugger::fireLog('Hello World'); // render string into Firebug console

Debugger::fireLog($_SERVER); // or even arrays and objects

Debugger::fireLog(new Exception('Test Exception')); // or exceptions
```

The result looks like this:

![FireLogger](http://nette.github.io/tracy/images/firelogger.png)
