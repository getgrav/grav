Cache
=====

This is a lightweight cache system based on file and directories.

Usage
=====

Step 1: Install it
------------------

Via composer:

```json
{
    "require": {
        "gregwar/cache": "1.0.*"
    }
}
```

Or with a clone of the repository:

```bash
git clone https://github.com/Gregwar/Cache.git
```

Or downloading it:

* [Download .zip](https://github.com/Gregwar/Cache/archive/master.zip)
* [Download .tar.gz](https://github.com/Gregwar/Cache/archive/master.tar.gz)

Step 2: Setup the rights
------------------------

You need your PHP script to have access to the cache directory, you can for instance
create a `cache` directory with mode 777:

```
mkdir cache
chmod 777 cache
```

Step 3: Access the cache
------------------------

To access the cache, you can do like this:

```php
<?php

include('vendor/autoload.php'); // If using composer

use Gregwar\Cache\Cache;

$cache = new Cache;
$cache->setCacheDirectory('cache'); // This is the default

// If the cache exists, this will return it, else, the closure will be called
// to create this image
$data = $cache->getOrCreate('red-square.png', array(), function($filename) {
    $i = imagecreatetruecolor(100, 100);
    imagefill($i, 0, 0, 0xff0000);
    imagepng($i, $filename);
});

header('Content-type: image/png');
echo $data;
```

This will render a red square. If the cache file (which will look like `cache/r/e/d/-/s/red-square.png')
exists, it will be read, else, the closure will be called in order to create the cache file.

API
===

You can use the following methods:

* `setCacheDirectory($directory)`: sets the cache directory (see below).
* `setActualCacheDirectory($directory)`: sets the actual cache directory (see below).
* `exists($filename, $conditions = array())`: check that the $filename file exists in the cache, checking
  the conditions (see below).
* `check($filename, $conditions = array())`: alias for `exists`.
* `getCacheFile($filename, $actual = false, $mkdir = false)`: gets the cache file. If the `$actual` flag
  is true, the actual cache file name will be returned (see below), if the `$mkdir` flag is true, the
  cache file directories tree will be created.
* `set($filename, $contents)`: write contents to `$filename` cache file.
* `write($filename, $contents)`: alias for `set()`
* `get($filename, $conditions = array())`: if the cache file for `$filename` exists, contents will be
  returned, else, `NULL` will be returned.
* `setPrefixSize($prefixSize)`: sets the prefix size for directories, default is 5. For instance, the
  cache file for `helloworld.txt`, will be `'h/e/l/l/o/helloworld.txt`.
* `getOrCreate($filename, $conditions = array(), $function, $file = false)`: this will check if the `$filename`
  cache file exists and verifies `$conditions` (see below). If the cache file is OK, it will return its
  contents. Else, it will call the `$function`, passing it the target file, this function can write the
  file given in parameter or just return data. Then, cache data will be returned. If `$file` flag is set,
  the cache file name will be returned instead of file data.

Note: consider using an hash for the `$filename` cache file, to avoid special characters.

Conditions
==========

You can use conditions to manage file expirations on the cache, there is two way of expiring:

* Using `max-age`, in seconds, to set the maximum age of the file
* Using `younger-than`, by passing another file, this will compare the modification date
  and regenerate the cache if the given file is younger.

For instance, if you want to uppercase a file:

```php
<?php

use Gregwar\Cache\Cache;

$cache = new Cache;

$data = $cache->getOrCreate('uppercase.txt',
    array(
        'younger-than' => 'original.txt'
    ),
    function() {
        echo "Generating file...\n";
        return strtoupper(file_get_contents('original.txt'));
});

echo $data;
```

This will be create the `uppercase.txt` cache file by uppercasing the `original.txt` if the cache file
does not exists or if the `original.txt` file is more recent than the cache file.

For instance:

```
php uppercase.php  # Will generate the cache file
php uppercase.php  # Will not generate the cache file
touch original.txt # Sets the last modification time to now
php uppercase.php  # Will re-generate the cache file
```

Cache directory and actual cache directory
==========================================

In some cases, you'll want to get the cache file name. For instance, if you're caching
images, you'll want to give a string like `cache/s/o/m/e/i/someimage.png` to put it into
an `<img>` tag. This can be done by passing the `$file` argument to the `getOrCreate` to true,
or directly using `getCacheFile` method (see above).

However, the visible `cache` directory of your users is not the same as the absolute path
you want to access. To do that, you can set both the cache directory and the actual cache directory.

The cache directory is the prefix visible by the users (for instance: `cache/s/o/m/e/i/someimage.png`),
and the actual cache directory is the prefix to use to actually access to the image (for instance: 
`/var/www/somesite/cache/s/o/m/e/i/someimage.png`). This way, the file will be accessed using absolute
path and the cache file returned will directly be usable for your user's browsers.

License
=======

This repository is under the MIT license, have a look at the `LICENCE` file.
