## Parsedown

Better [Markdown](http://en.wikipedia.org/wiki/Markdown) parser for PHP.

* [Demo](http://parsedown.org/demo)
* [Test Suite](http://parsedown.org/tests/)

### Features

* [Fast](http://parsedown.org/speed)
* [Consistent](http://parsedown.org/consistency)
* [GitHub Flavored](https://help.github.com/articles/github-flavored-markdown)
* [Tested](https://travis-ci.org/erusev/parsedown) in PHP 5.2, 5.3, 5.4, 5.5, 5.6 and [hhvm](http://www.hhvm.com/)
* Extensible
* [Markdown Extra extension](https://github.com/erusev/parsedown-extra) <sup>new</sup>

### Installation

Include `Parsedown.php` or install [the composer package](https://packagist.org/packages/erusev/parsedown).

### Example

``` php
$Parsedown = new Parsedown();

echo $Parsedown->text('Hello _Parsedown_!'); # prints: <p>Hello <em>Parsedown</em>!</p>
```

More examples in [the wiki](https://github.com/erusev/parsedown/wiki/Usage).

### Questions

**How does Parsedown work?**<br/>
Parsedown recognises that the Markdown syntax is optimised for humans so it tries to read like one. It goes through text line by line. It looks at how lines start to identify blocks. It looks for special characters to identify inline elements.

**Why doesn’t Parsedown use namespaces?**<br/>
Using namespaces would mean dropping support for PHP 5.2. Since Parsedown is a single class with an uncommon name, making this trade wouldn't make much sense.

**Who uses Parsedown?**<br/>
[phpDocumentor](http://www.phpdoc.org/), [Bolt CMS](http://bolt.cm/), [RaspberryPi.org](http://www.raspberrypi.org/) and [more](https://www.versioneye.com/php/erusev:parsedown/references).
