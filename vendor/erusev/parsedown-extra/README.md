## Parsedown Extra

An extension of [Parsedown](http://parsedown.org) that adds support for [Markdown Extra](http://en.wikipedia.org/wiki/Markdown_Extra).

[[ demo ]](http://parsedown.org/demo?extra=1)

### Installation

Include both `Parsedown.php` and `ParsedownExtra.php` or install [the composer package](https://packagist.org/packages/erusev/parsedown-extra).

### Example

``` php
$Instance = new ParsedownExtra();

echo $Instance->text('Hello _Parsedown Extra_!'); # prints: <p>Hello <em>Parsedown Extra</em>!</p>
```
