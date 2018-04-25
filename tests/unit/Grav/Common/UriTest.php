<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Common\Uri;
use Grav\Common\Utils;

/**
 * Class UriTest
 */
class UriTest extends \Codeception\TestCase\Test
{
    /** @var Grav $grav */
    protected $grav;

    /** @var Uri $uri */
    protected $uri;

    protected $tests = [
        '/path' => [
            'scheme' => '',
            'user' => null,
            'password' => null,
            'host' => null,
            'port' => null,
            'path' => '/path',
            'query' => '',
            'fragment' => null,

            'route' => '/path',
            'paths' => ['path'],
            'params' => null,
            'url' => '/path',
            'environment' => 'unknown',
            'basename' => 'path',
            'base' => '',
            'currentPage' => 1,
            'rootUrl' => '',
            'extension' => null,
            'addNonce' => '/path/nonce:{{nonce}}',
        ],
        '//localhost/' => [
            'scheme' => '//',
            'user' => null,
            'password' => null,
            'host' => 'localhost',
            'port' => null,
            'path' => '/',
            'query' => '',
            'fragment' => null,

            'route' => '/',
            'paths' => [],
            'params' => null,
            'url' => '/',
            'environment' => 'localhost',
            'basename' => '',
            'base' => '//localhost',
            'currentPage' => 1,
            'rootUrl' => '//localhost',
            'extension' => null,
            'addNonce' => '//localhost/nonce:{{nonce}}',
        ],
        'http://localhost/' => [
            'scheme' => 'http://',
            'user' => null,
            'password' => null,
            'host' => 'localhost',
            'port' => 80,
            'path' => '/',
            'query' => '',
            'fragment' => null,

            'route' => '/',
            'paths' => [],
            'params' => null,
            'url' => '/',
            'environment' => 'localhost',
            'basename' => '',
            'base' => 'http://localhost',
            'currentPage' => 1,
            'rootUrl' => 'http://localhost',
            'extension' => null,
            'addNonce' => 'http://localhost/nonce:{{nonce}}',
        ],
        'http://127.0.0.1/' => [
            'scheme' => 'http://',
            'user' => null,
            'password' => null,
            'host' => '127.0.0.1',
            'port' => 80,
            'path' => '/',
            'query' => '',
            'fragment' => null,

            'route' => '/',
            'paths' => [],
            'params' => null,
            'url' => '/',
            'environment' => 'localhost',
            'basename' => '',
            'base' => 'http://127.0.0.1',
            'currentPage' => 1,
            'rootUrl' => 'http://127.0.0.1',
            'extension' => null,
            'addNonce' => 'http://127.0.0.1/nonce:{{nonce}}',
        ],
        'https://localhost/' => [
            'scheme' => 'https://',
            'user' => null,
            'password' => null,
            'host' => 'localhost',
            'port' => 443,
            'path' => '/',
            'query' => '',
            'fragment' => null,

            'route' => '/',
            'paths' => [],
            'params' => null,
            'url' => '/',
            'environment' => 'localhost',
            'basename' => '',
            'base' => 'https://localhost',
            'currentPage' => 1,
            'rootUrl' => 'https://localhost',
            'extension' => null,
            'addNonce' => 'https://localhost/nonce:{{nonce}}',
        ],
        'http://localhost:8080/grav/it/ueper' => [
            'scheme' => 'http://',
            'user' => null,
            'password' => null,
            'host' => 'localhost',
            'port' => 8080,
            'path' => '/grav/it/ueper',
            'query' => '',
            'fragment' => null,

            'route' => '/grav/it/ueper',
            'paths' => ['grav', 'it', 'ueper'],
            'params' => null,
            'url' => '/grav/it/ueper',
            'environment' => 'localhost',
            'basename' => 'ueper',
            'base' => 'http://localhost:8080',
            'currentPage' => 1,
            'rootUrl' => 'http://localhost:8080',
            'extension' => null,
            'addNonce' => 'http://localhost:8080/grav/it/ueper/nonce:{{nonce}}',
        ],
        'http://localhost:8080/grav/it/ueper:xxx' => [
            'scheme' => 'http://',
            'user' => null,
            'password' => null,
            'host' => 'localhost',
            'port' => 8080,
            'path' => '/grav/it',
            'query' => '',
            'fragment' => null,

            'route' => '/grav/it',
            'paths' => ['grav', 'it'],
            'params' => '/ueper:xxx',
            'url' => '/grav/it',
            'environment' => 'localhost',
            'basename' => 'it',
            'base' => 'http://localhost:8080',
            'currentPage' => 1,
            'rootUrl' => 'http://localhost:8080',
            'extension' => null,
            'addNonce' => 'http://localhost:8080/grav/it/ueper:xxx/nonce:{{nonce}}',
        ],
        'http://localhost:8080/grav/it/ueper:xxx/page:/test:yyy' => [
            'scheme' => 'http://',
            'user' => null,
            'password' => null,
            'host' => 'localhost',
            'port' => 8080,
            'path' => '/grav/it',
            'query' => '',
            'fragment' => null,

            'route' => '/grav/it',
            'paths' => ['grav', 'it'],
            'params' => '/ueper:xxx/page:/test:yyy',
            'url' => '/grav/it',
            'environment' => 'localhost',
            'basename' => 'it',
            'base' => 'http://localhost:8080',
            'currentPage' => '',
            'rootUrl' => 'http://localhost:8080',
            'extension' => null,
            'addNonce' => 'http://localhost:8080/grav/it/ueper:xxx/page:/test:yyy/nonce:{{nonce}}',
        ],
        'http://localhost:8080/grav/it/ueper?test=x' => [
            'scheme' => 'http://',
            'user' => null,
            'password' => null,
            'host' => 'localhost',
            'port' => 8080,
            'path' => '/grav/it/ueper',
            'query' => 'test=x',
            'fragment' => null,

            'route' => '/grav/it/ueper',
            'paths' => ['grav', 'it', 'ueper'],
            'params' => null,
            'url' => '/grav/it/ueper',
            'environment' => 'localhost',
            'basename' => 'ueper',
            'base' => 'http://localhost:8080',
            'currentPage' => 1,
            'rootUrl' => 'http://localhost:8080',
            'extension' => null,
            'addNonce' => 'http://localhost:8080/grav/it/ueper/nonce:{{nonce}}?test=x',
        ],
        'http://localhost:80/grav/it/ueper?test=x' => [
            'scheme' => 'http://',
            'user' => null,
            'password' => null,
            'host' => 'localhost',
            'port' => 80,
            'path' => '/grav/it/ueper',
            'query' => 'test=x',
            'fragment' => null,

            'route' => '/grav/it/ueper',
            'paths' => ['grav', 'it', 'ueper'],
            'params' => null,
            'url' => '/grav/it/ueper',
            'environment' => 'localhost',
            'basename' => 'ueper',
            'base' => 'http://localhost:80',
            'currentPage' => 1,
            'rootUrl' => 'http://localhost:80',
            'extension' => null,
            'addNonce' => 'http://localhost:80/grav/it/ueper/nonce:{{nonce}}?test=x',
        ],
        'http://localhost/grav/it/ueper?test=x' => [
            'scheme' => 'http://',
            'user' => null,
            'password' => null,
            'host' => 'localhost',
            'port' => 80,
            'path' => '/grav/it/ueper',
            'query' => 'test=x',
            'fragment' => null,

            'route' => '/grav/it/ueper',
            'paths' => ['grav', 'it', 'ueper'],
            'params' => null,
            'url' => '/grav/it/ueper',
            'environment' => 'localhost',
            'basename' => 'ueper',
            'base' => 'http://localhost',
            'currentPage' => 1,
            'rootUrl' => 'http://localhost',
            'extension' => null,
            'addNonce' => 'http://localhost/grav/it/ueper/nonce:{{nonce}}?test=x',
        ],
        'http://grav/grav/it/ueper' => [
            'scheme' => 'http://',
            'user' => null,
            'password' => null,
            'host' => 'grav',
            'port' => 80,
            'path' => '/grav/it/ueper',
            'query' => '',
            'fragment' => null,

            'route' => '/grav/it/ueper',
            'paths' => ['grav', 'it', 'ueper'],
            'params' => null,
            'url' => '/grav/it/ueper',
            'environment' => 'grav',
            'basename' => 'ueper',
            'base' => 'http://grav',
            'currentPage' => 1,
            'rootUrl' => 'http://grav',
            'extension' => null,
            'addNonce' => 'http://grav/grav/it/ueper/nonce:{{nonce}}',
        ],
        'https://username:password@api.getgrav.com:4040/v1/post/128/page:x/?all=1' => [
            'scheme' => 'https://',
            'user' => 'username',
            'password' => 'password',
            'host' => 'api.getgrav.com',
            'port' => 4040,
            'path' => '/v1/post/128/', // FIXME <-
            'query' => 'all=1',
            'fragment' => null,

            'route' => '/v1/post/128',
            'paths' => ['v1', 'post', '128'],
            'params' => '/page:x',
            'url' => '/v1/post/128',
            'environment' => 'api.getgrav.com',
            'basename' => '128',
            'base' => 'https://api.getgrav.com:4040',
            'currentPage' => 'x',
            'rootUrl' => 'https://api.getgrav.com:4040',
            'extension' => null,
            'addNonce' => 'https://username:password@api.getgrav.com:4040/v1/post/128/page:x/nonce:{{nonce}}?all=1',
            '__toString' => 'https://username:password@api.getgrav.com:4040/v1/post/128/page:x?all=1'
        ],
        'https://google.com:443/' => [
            'scheme' => 'https://',
            'user' => null,
            'password' => null,
            'host' => 'google.com',
            'port' => 443,
            'path' => '/',
            'query' => '',
            'fragment' => null,

            'route' => '/',
            'paths' => [],
            'params' => null,
            'url' => '/',
            'environment' => 'google.com',
            'basename' => '',
            'base' => 'https://google.com:443',
            'currentPage' => 1,
            'rootUrl' => 'https://google.com:443',
            'extension' => null,
            'addNonce' => 'https://google.com:443/nonce:{{nonce}}',
        ],
        // Path tests.
        'http://localhost:8080/a/b/c/d' => [
            'scheme' => 'http://',
            'user' => null,
            'password' => null,
            'host' => 'localhost',
            'port' => 8080,
            'path' => '/a/b/c/d',
            'query' => '',
            'fragment' => null,

            'route' => '/a/b/c/d',
            'paths' => ['a', 'b', 'c', 'd'],
            'params' => null,
            'url' => '/a/b/c/d',
            'environment' => 'localhost',
            'basename' => 'd',
            'base' => 'http://localhost:8080',
            'currentPage' => 1,
            'rootUrl' => 'http://localhost:8080',
            'extension' => null,
            'addNonce' => 'http://localhost:8080/a/b/c/d/nonce:{{nonce}}',
        ],
        'http://localhost:8080/a/b/c/d/e/f/a/b/c/d/e/f/a/b/c/d/e/f' => [
            'scheme' => 'http://',
            'user' => null,
            'password' => null,
            'host' => 'localhost',
            'port' => 8080,
            'path' => '/a/b/c/d/e/f/a/b/c/d/e/f/a/b/c/d/e/f',
            'query' => '',
            'fragment' => null,

            'route' => '/a/b/c/d/e/f/a/b/c/d/e/f/a/b/c/d/e/f',
            'paths' => ['a', 'b', 'c', 'd', 'e', 'f', 'a', 'b', 'c', 'd', 'e', 'f', 'a', 'b', 'c', 'd', 'e', 'f'],
            'params' => null,
            'url' => '/a/b/c/d/e/f/a/b/c/d/e/f/a/b/c/d/e/f',
            'environment' => 'localhost',
            'basename' => 'f',
            'base' => 'http://localhost:8080',
            'currentPage' => 1,
            'rootUrl' => 'http://localhost:8080',
            'extension' => null,
            'addNonce' => 'http://localhost:8080/a/b/c/d/e/f/a/b/c/d/e/f/a/b/c/d/e/f/nonce:{{nonce}}',
        ],
        'http://localhost/this is the path/my page' => [
            'scheme' => 'http://',
            'user' => null,
            'password' => null,
            'host' => 'localhost',
            'port' => 80,
            'path' => '/this%20is%20the%20path/my%20page',
            'query' => '',
            'fragment' => null,

            'route' => '/this%20is%20the%20path/my%20page',
            'paths' => ['this%20is%20the%20path', 'my%20page'],
            'params' => null,
            'url' => '/this%20is%20the%20path/my%20page',
            'environment' => 'localhost',
            'basename' => 'my%20page',
            'base' => 'http://localhost',
            'currentPage' => 1,
            'rootUrl' => 'http://localhost',
            'extension' => null,
            'addNonce' => 'http://localhost/this%20is%20the%20path/my%20page/nonce:{{nonce}}',
            '__toString' => 'http://localhost/this%20is%20the%20path/my%20page'
        ],
        'http://localhost/pölöpölö/päläpälä' => [
            'scheme' => 'http://',
            'user' => null,
            'password' => null,
            'host' => 'localhost',
            'port' => 80,
            'path' => '/p%C3%B6l%C3%B6p%C3%B6l%C3%B6/p%C3%A4l%C3%A4p%C3%A4l%C3%A4',
            'query' => '',
            'fragment' => null,

            'route' => '/p%C3%B6l%C3%B6p%C3%B6l%C3%B6/p%C3%A4l%C3%A4p%C3%A4l%C3%A4',
            'paths' => ['p%C3%B6l%C3%B6p%C3%B6l%C3%B6', 'p%C3%A4l%C3%A4p%C3%A4l%C3%A4'],
            'params' => null,
            'url' => '/p%C3%B6l%C3%B6p%C3%B6l%C3%B6/p%C3%A4l%C3%A4p%C3%A4l%C3%A4',
            'environment' => 'localhost',
            'basename' => 'p%C3%A4l%C3%A4p%C3%A4l%C3%A4',
            'base' => 'http://localhost',
            'currentPage' => 1,
            'rootUrl' => 'http://localhost',
            'extension' => null,
            'addNonce' => 'http://localhost/p%C3%B6l%C3%B6p%C3%B6l%C3%B6/p%C3%A4l%C3%A4p%C3%A4l%C3%A4/nonce:{{nonce}}',
            '__toString' => 'http://localhost/p%C3%B6l%C3%B6p%C3%B6l%C3%B6/p%C3%A4l%C3%A4p%C3%A4l%C3%A4'
        ],
        // Query params tests.
        'http://localhost:8080/grav/it/ueper?test=x&test2=y' => [
            'scheme' => 'http://',
            'user' => null,
            'password' => null,
            'host' => 'localhost',
            'port' => 8080,
            'path' => '/grav/it/ueper',
            'query' => 'test=x&test2=y',
            'fragment' => null,

            'route' => '/grav/it/ueper',
            'paths' => ['grav', 'it', 'ueper'],
            'params' => null,
            'url' => '/grav/it/ueper',
            'environment' => 'localhost',
            'basename' => 'ueper',
            'base' => 'http://localhost:8080',
            'currentPage' => 1,
            'rootUrl' => 'http://localhost:8080',
            'extension' => null,
            'addNonce' => 'http://localhost:8080/grav/it/ueper/nonce:{{nonce}}?test=x&test2=y',
        ],
        'http://localhost:8080/grav/it/ueper?test=x&test2=y&test3=x&test4=y' => [
            'scheme' => 'http://',
            'user' => null,
            'password' => null,
            'host' => 'localhost',
            'port' => 8080,
            'path' => '/grav/it/ueper',
            'query' => 'test=x&test2=y&test3=x&test4=y',
            'fragment' => null,

            'route' => '/grav/it/ueper',
            'paths' => ['grav', 'it', 'ueper'],
            'params' => null,
            'url' => '/grav/it/ueper',
            'environment' => 'localhost',
            'basename' => 'ueper',
            'base' => 'http://localhost:8080',
            'currentPage' => 1,
            'rootUrl' => 'http://localhost:8080',
            'extension' => null,
            'addNonce' => 'http://localhost:8080/grav/it/ueper/nonce:{{nonce}}?test=x&test2=y&test3=x&test4=y',
        ],
        'http://localhost:8080/grav/it/ueper?test=x&test2=y&test3=x&test4=y/test' => [
            'scheme' => 'http://',
            'user' => null,
            'password' => null,
            'host' => 'localhost',
            'port' => 8080,
            'path' => '/grav/it/ueper',
            'query' => 'test=x&test2=y&test3=x&test4=y%2Ftest',
            'fragment' => null,

            'route' => '/grav/it/ueper',
            'paths' => ['grav', 'it', 'ueper'],
            'params' => null,
            'url' => '/grav/it/ueper',
            'environment' => 'localhost',
            'basename' => 'ueper',
            'base' => 'http://localhost:8080',
            'currentPage' => 1,
            'rootUrl' => 'http://localhost:8080',
            'extension' => null,
            'addNonce' => 'http://localhost:8080/grav/it/ueper/nonce:{{nonce}}?test=x&test2=y&test3=x&test4=y/test',
        ],
        // Port tests.
        'http://localhost/a-page' => [
            'scheme' => 'http://',
            'user' => null,
            'password' => null,
            'host' => 'localhost',
            'port' => 80,
            'path' => '/a-page',
            'query' => '',
            'fragment' => null,

            'route' => '/a-page',
            'paths' => ['a-page'],
            'params' => null,
            'url' => '/a-page',
            'environment' => 'localhost',
            'basename' => 'a-page',
            'base' => 'http://localhost',
            'currentPage' => 1,
            'rootUrl' => 'http://localhost',
            'extension' => null,
            'addNonce' => 'http://localhost/a-page/nonce:{{nonce}}',
        ],
        'http://localhost:8080/a-page' => [
            'scheme' => 'http://',
            'user' => null,
            'password' => null,
            'host' => 'localhost',
            'port' => 8080,
            'path' => '/a-page',
            'query' => '',
            'fragment' => null,

            'route' => '/a-page',
            'paths' => ['a-page'],
            'params' => null,
            'url' => '/a-page',
            'environment' => 'localhost',
            'basename' => 'a-page',
            'base' => 'http://localhost:8080',
            'currentPage' => 1,
            'rootUrl' => 'http://localhost:8080',
            'extension' => null,
            'addNonce' => 'http://localhost:8080/a-page/nonce:{{nonce}}',
        ],
        'http://localhost:443/a-page' => [
            'scheme' => 'http://',
            'user' => null,
            'password' => null,
            'host' => 'localhost',
            'port' => 443,
            'path' => '/a-page',
            'query' => '',
            'fragment' => null,

            'route' => '/a-page',
            'paths' => ['a-page'],
            'params' => null,
            'url' => '/a-page',
            'environment' => 'localhost',
            'basename' => 'a-page',
            'base' => 'http://localhost:443',
            'currentPage' => 1,
            'rootUrl' => 'http://localhost:443',
            'extension' => null,
            'addNonce' => 'http://localhost:443/a-page/nonce:{{nonce}}',
        ],
        // Extension tests.
        'http://localhost/a-page.html' => [
            'scheme' => 'http://',
            'user' => null,
            'password' => null,
            'host' => 'localhost',
            'port' => 80,
            'path' => '/a-page',
            'query' => '',
            'fragment' => null,

            'route' => '/a-page',
            'paths' => ['a-page'],
            'params' => null,
            'url' => '/a-page',
            'environment' => 'localhost',
            'basename' => 'a-page.html',
            'base' => 'http://localhost',
            'currentPage' => 1,
            'rootUrl' => 'http://localhost',
            'extension' => 'html',
            'addNonce' => 'http://localhost/a-page.html/nonce:{{nonce}}',
            '__toString' => 'http://localhost/a-page', // FIXME <-
        ],
        'http://localhost/a-page.json' => [
            'scheme' => 'http://',
            'user' => null,
            'password' => null,
            'host' => 'localhost',
            'port' => 80,
            'path' => '/a-page',
            'query' => '',
            'fragment' => null,

            'route' => '/a-page',
            'paths' => ['a-page'],
            'params' => null,
            'url' => '/a-page',
            'environment' => 'localhost',
            'basename' => 'a-page.json',
            'base' => 'http://localhost',
            'currentPage' => 1,
            'rootUrl' => 'http://localhost',
            'extension' => 'json',
            'addNonce' => 'http://localhost/a-page.json/nonce:{{nonce}}',
            '__toString' => 'http://localhost/a-page', // FIX ME <-
        ],
        'http://localhost/admin/ajax.json/task:getnewsfeed' => [
            'scheme' => 'http://',
            'user' => null,
            'password' => null,
            'host' => 'localhost',
            'port' => 80,
            'path' => '/admin/ajax',
            'query' => '',
            'fragment' => null,

            'route' => '/admin/ajax',
            'paths' => ['admin', 'ajax'],
            'params' => '/task:getnewsfeed',
            'url' => '/admin/ajax',
            'environment' => 'localhost',
            'basename' => 'ajax.json',
            'base' => 'http://localhost',
            'currentPage' => 1,
            'rootUrl' => 'http://localhost',
            'extension' => 'json',
            'addNonce' => 'http://localhost/admin/ajax.json/task:getnewsfeed/nonce:{{nonce}}',
            '__toString' => 'http://localhost/admin/ajax/task:getnewsfeed',
        ],
        'http://localhost/grav/admin/media.json/route:L1VzZXJzL3JodWsvd29ya3NwYWNlL2dyYXYtZGVtby1zYW1wbGVyL3VzZXIvYXNzZXRzL3FRMXB4Vk1ERTNJZzh5Ni5qcGc=/task:removeFileFromBlueprint/proute:/blueprint:Y29uZmlnL2RldGFpbHM=/type:config/field:deep.nested.custom_file/path:dXNlci9hc3NldHMvcVExcHhWTURFM0lnOHk2LmpwZw==' => [
            'scheme' => 'http://',
            'user' => null,
            'password' => null,
            'host' => 'localhost',
            'port' => 80,
            'path' => '/grav/admin/media',
            'query' => '',
            'fragment' => null,

            'route' => '/grav/admin/media',
            'paths' => ['grav','admin','media'],
            'params' => '/route:L1VzZXJzL3JodWsvd29ya3NwYWNlL2dyYXYtZGVtby1zYW1wbGVyL3VzZXIvYXNzZXRzL3FRMXB4Vk1ERTNJZzh5Ni5qcGc=/task:removeFileFromBlueprint/proute:/blueprint:Y29uZmlnL2RldGFpbHM=/type:config/field:deep.nested.custom_file/path:dXNlci9hc3NldHMvcVExcHhWTURFM0lnOHk2LmpwZw==',
            'url' => '/grav/admin/media',
            'environment' => 'localhost',
            'basename' => 'media.json',
            'base' => 'http://localhost',
            'currentPage' => 1,
            'rootUrl' => 'http://localhost',
            'extension' => 'json',
            'addNonce' => 'http://localhost/grav/admin/media.json/route:L1VzZXJzL3JodWsvd29ya3NwYWNlL2dyYXYtZGVtby1zYW1wbGVyL3VzZXIvYXNzZXRzL3FRMXB4Vk1ERTNJZzh5Ni5qcGc=/task:removeFileFromBlueprint/proute:/blueprint:Y29uZmlnL2RldGFpbHM=/type:config/field:deep.nested.custom_file/path:dXNlci9hc3NldHMvcVExcHhWTURFM0lnOHk2LmpwZw==/nonce:{{nonce}}',
            '__toString' => 'http://localhost/grav/admin/media/route:L1VzZXJzL3JodWsvd29ya3NwYWNlL2dyYXYtZGVtby1zYW1wbGVyL3VzZXIvYXNzZXRzL3FRMXB4Vk1ERTNJZzh5Ni5qcGc=/task:removeFileFromBlueprint/proute:/blueprint:Y29uZmlnL2RldGFpbHM=/type:config/field:deep.nested.custom_file/path:dXNlci9hc3NldHMvcVExcHhWTURFM0lnOHk2LmpwZw==', // FIXME <-
        ],
        'http://localhost/a-page.foo' => [
            'scheme' => 'http://',
            'user' => null,
            'password' => null,
            'host' => 'localhost',
            'port' => 80,
            'path' => '/a-page.foo',
            'query' => '',
            'fragment' => null,

            'route' => '/a-page.foo',
            'paths' => ['a-page.foo'],
            'params' => null,
            'url' => '/a-page.foo',
            'environment' => 'localhost',
            'basename' => 'a-page.foo',
            'base' => 'http://localhost',
            'currentPage' => 1,
            'rootUrl' => 'http://localhost',
            'extension' => 'foo',
            'addNonce' => 'http://localhost/a-page.foo/nonce:{{nonce}}',
            '__toString' => 'http://localhost/a-page.foo'
        ],
        // Fragment tests.
        'http://localhost:8080/a/b/c#my-fragment' => [
            'scheme' => 'http://',
            'user' => null,
            'password' => null,
            'host' => 'localhost',
            'port' => 8080,
            'path' => '/a/b/c',
            'query' => '',
            'fragment' => 'my-fragment',

            'route' => '/a/b/c',
            'paths' => ['a', 'b', 'c'],
            'params' => null,
            'url' => '/a/b/c',
            'environment' => 'localhost',
            'basename' => 'c',
            'base' => 'http://localhost:8080',
            'currentPage' => 1,
            'rootUrl' => 'http://localhost:8080',
            'extension' => null,
            'addNonce' => 'http://localhost:8080/a/b/c/nonce:{{nonce}}#my-fragment',
        ],
        // Attacks.
        '"><script>alert</script>://localhost' => [
            'scheme' => '',
            'user' => null,
            'password' => null,
            'host' => null,
            'port' => null,
            'path' => '%22%3E%3Cscript%3Ealert%3C/localhost',
            'query' => '',
            'fragment' => null,

            'route' => '/%22%3E%3Cscript%3Ealert%3C/localhost',
            'paths' => ['%22%3E%3Cscript%3Ealert%3C', 'localhost'],
            'params' => '/script%3E:',
            'url' => '%22%3E%3Cscript%3Ealert%3C//localhost',
            'environment' => 'unknown',
            'basename' => 'localhost',
            'base' => '',
            'currentPage' => 1,
            'rootUrl' => '',
            'extension' => null,
            //'addNonce' => '%22%3E%3Cscript%3Ealert%3C/localhost/script%3E:/nonce:{{nonce}}', // FIXME <-
            '__toString' => '%22%3E%3Cscript%3Ealert%3C/localhost/script%3E:' // FIXME <-
        ],
        'http://"><script>alert</script>' => [
            'scheme' => 'http://',
            'user' => null,
            'password' => null,
            'host' => 'unknown',
            'port' => 80,
            'path' => '/script%3E',
            'query' => '',
            'fragment' => null,

            'route' => '/script%3E',
            'paths' => ['script%3E'],
            'params' => null,
            'url' => '/script%3E',
            'environment' => 'unknown',
            'basename' => 'script%3E',
            'base' => 'http://unknown',
            'currentPage' => 1,
            'rootUrl' => 'http://unknown',
            'extension' => null,
            'addNonce' => 'http://unknown/script%3E/nonce:{{nonce}}',
            '__toString' => 'http://unknown/script%3E'
        ],
        'http://localhost/"><script>alert</script>' => [
            'scheme' => 'http://',
            'user' => null,
            'password' => null,
            'host' => 'localhost',
            'port' => 80,
            'path' => '/%22%3E%3Cscript%3Ealert%3C/script%3E',
            'query' => '',
            'fragment' => null,

            'route' => '/%22%3E%3Cscript%3Ealert%3C/script%3E',
            'paths' => ['%22%3E%3Cscript%3Ealert%3C', 'script%3E'],
            'params' => null,
            'url' => '/%22%3E%3Cscript%3Ealert%3C/script%3E',
            'environment' => 'localhost',
            'basename' => 'script%3E',
            'base' => 'http://localhost',
            'currentPage' => 1,
            'rootUrl' => 'http://localhost',
            'extension' => null,
            'addNonce' => 'http://localhost/%22%3E%3Cscript%3Ealert%3C/script%3E/nonce:{{nonce}}',
            '__toString' => 'http://localhost/%22%3E%3Cscript%3Ealert%3C/script%3E'
        ],
        'http://localhost/something/p1:foo/p2:"><script>alert</script>' => [
            'scheme' => 'http://',
            'user' => null,
            'password' => null,
            'host' => 'localhost',
            'port' => 80,
            'path' => '/something/script%3E',
            'query' => '',
            'fragment' => null,

            'route' => '/something/script%3E',
            'paths' => ['something', 'script%3E'],
            'params' => '/p1:foo/p2:%22%3E%3Cscript%3Ealert%3C',
            'url' => '/something/script%3E',
            'environment' => 'localhost',
            'basename' => 'script%3E',
            'base' => 'http://localhost',
            'currentPage' => 1,
            'rootUrl' => 'http://localhost',
            'extension' => null,
            //'addNonce' => 'http://localhost/something/script%3E/p1:foo/p2:%22%3E%3Cscript%3Ealert%3C/nonce:{{nonce}}', // FIXME <-
            '__toString' => 'http://localhost/something/script%3E/p1:foo/p2:%22%3E%3Cscript%3Ealert%3C'
        ],
        'http://localhost/something?p="><script>alert</script>' => [
            'scheme' => 'http://',
            'user' => null,
            'password' => null,
            'host' => 'localhost',
            'port' => 80,
            'path' => '/something',
            'query' => 'p=%22%3E%3Cscript%3Ealert%3C%2Fscript%3E',
            'fragment' => null,

            'route' => '/something',
            'paths' => ['something'],
            'params' => null,
            'url' => '/something',
            'environment' => 'localhost',
            'basename' => 'something',
            'base' => 'http://localhost',
            'currentPage' => 1,
            'rootUrl' => 'http://localhost',
            'extension' => null,
            'addNonce' => 'http://localhost/something/nonce:{{nonce}}?p=%22%3E%3Cscript%3Ealert%3C/script%3E',
            '__toString' => 'http://localhost/something?p=%22%3E%3Cscript%3Ealert%3C/script%3E'
        ],
        'http://localhost/something#"><script>alert</script>' => [
            'scheme' => 'http://',
            'user' => null,
            'password' => null,
            'host' => 'localhost',
            'port' => 80,
            'path' => '/something',
            'query' => '',
            'fragment' => '%22%3E%3Cscript%3Ealert%3C/script%3E',

            'route' => '/something',
            'paths' => ['something'],
            'params' => null,
            'url' => '/something',
            'environment' => 'localhost',
            'basename' => 'something',
            'base' => 'http://localhost',
            'currentPage' => 1,
            'rootUrl' => 'http://localhost',
            'extension' => null,
            'addNonce' => 'http://localhost/something/nonce:{{nonce}}#%22%3E%3Cscript%3Ealert%3C/script%3E',
            '__toString' => 'http://localhost/something#%22%3E%3Cscript%3Ealert%3C/script%3E'
        ],
        'https://www.getgrav.org/something/"><script>eval(atob("aGlzdG9yeS5wdXNoU3RhdGUoJycsJycsJy8nKTskKCdoZWFkLGJvZHknKS5odG1sKCcnKS5sb2FkKCcvJyk7JC5wb3N0KCcvYWRtaW4nLGZ1bmN0aW9uKGRhdGEpeyQucG9zdCgkKGRhdGEpLmZpbmQoJ1tpZD1hZG1pbi11c2VyLWRldGFpbHNdIGEnKS5hdHRyKCdocmVmJykseydhZG1pbi1ub25jZSc6JChkYXRhKS5maW5kKCdbZGF0YS1jbGVhci1jYWNoZV0nKS5hdHRyKCdkYXRhLWNsZWFyLWNhY2hlJykuc3BsaXQoJzonKS5wb3AoKS50cmltKCksJ2RhdGFbcGFzc3dvcmRdJzonSW0zdjFsaDR4eDByJywndGFzayc6J3NhdmUnfSl9KQ=="))</script><' => [
            'scheme' => 'https://',
            'user' => null,
            'password' => null,
            'host' => 'www.getgrav.org',
            'port' => 443,
            'path' => '/something/%22%3E%3Cscript%3Eeval%28atob%28%22aGlzdG9yeS5wdXNoU3RhdGUoJycsJycsJy8nKTskKCdoZWFkLGJvZHknKS5odG1sKCcnKS5sb2FkKCcvJyk7JC5wb3N0KCcvYWRtaW4nLGZ1bmN0aW9uKGRhdGEpeyQucG9zdCgkKGRhdGEpLmZpbmQoJ1tpZD1hZG1pbi11c2VyLWRldGFpbHNdIGEnKS5hdHRyKCdocmVmJykseydhZG1pbi1ub25jZSc6JChkYXRhKS5maW5kKCdbZGF0YS1jbGVhci1jYWNoZV0nKS5hdHRyKCdkYXRhLWNsZWFyLWNhY2hlJykuc3BsaXQoJzonKS5wb3AoKS50cmltKCksJ2RhdGFbcGFzc3dvcmRdJzonSW0zdjFsaDR4eDByJywndGFzayc6J3NhdmUnfSl9KQ==%22%29%29%3C/script%3E%3C',
            'query' => '',
            'fragment' => null,

            'route' => '/something/%22%3E%3Cscript%3Eeval%28atob%28%22aGlzdG9yeS5wdXNoU3RhdGUoJycsJycsJy8nKTskKCdoZWFkLGJvZHknKS5odG1sKCcnKS5sb2FkKCcvJyk7JC5wb3N0KCcvYWRtaW4nLGZ1bmN0aW9uKGRhdGEpeyQucG9zdCgkKGRhdGEpLmZpbmQoJ1tpZD1hZG1pbi11c2VyLWRldGFpbHNdIGEnKS5hdHRyKCdocmVmJykseydhZG1pbi1ub25jZSc6JChkYXRhKS5maW5kKCdbZGF0YS1jbGVhci1jYWNoZV0nKS5hdHRyKCdkYXRhLWNsZWFyLWNhY2hlJykuc3BsaXQoJzonKS5wb3AoKS50cmltKCksJ2RhdGFbcGFzc3dvcmRdJzonSW0zdjFsaDR4eDByJywndGFzayc6J3NhdmUnfSl9KQ==%22%29%29%3C/script%3E%3C',
            'paths' => ['something', '%22%3E%3Cscript%3Eeval%28atob%28%22aGlzdG9yeS5wdXNoU3RhdGUoJycsJycsJy8nKTskKCdoZWFkLGJvZHknKS5odG1sKCcnKS5sb2FkKCcvJyk7JC5wb3N0KCcvYWRtaW4nLGZ1bmN0aW9uKGRhdGEpeyQucG9zdCgkKGRhdGEpLmZpbmQoJ1tpZD1hZG1pbi11c2VyLWRldGFpbHNdIGEnKS5hdHRyKCdocmVmJykseydhZG1pbi1ub25jZSc6JChkYXRhKS5maW5kKCdbZGF0YS1jbGVhci1jYWNoZV0nKS5hdHRyKCdkYXRhLWNsZWFyLWNhY2hlJykuc3BsaXQoJzonKS5wb3AoKS50cmltKCksJ2RhdGFbcGFzc3dvcmRdJzonSW0zdjFsaDR4eDByJywndGFzayc6J3NhdmUnfSl9KQ==%22%29%29%3C', 'script%3E%3C'],
            'params' => null,
            'url' => '/something/%22%3E%3Cscript%3Eeval%28atob%28%22aGlzdG9yeS5wdXNoU3RhdGUoJycsJycsJy8nKTskKCdoZWFkLGJvZHknKS5odG1sKCcnKS5sb2FkKCcvJyk7JC5wb3N0KCcvYWRtaW4nLGZ1bmN0aW9uKGRhdGEpeyQucG9zdCgkKGRhdGEpLmZpbmQoJ1tpZD1hZG1pbi11c2VyLWRldGFpbHNdIGEnKS5hdHRyKCdocmVmJykseydhZG1pbi1ub25jZSc6JChkYXRhKS5maW5kKCdbZGF0YS1jbGVhci1jYWNoZV0nKS5hdHRyKCdkYXRhLWNsZWFyLWNhY2hlJykuc3BsaXQoJzonKS5wb3AoKS50cmltKCksJ2RhdGFbcGFzc3dvcmRdJzonSW0zdjFsaDR4eDByJywndGFzayc6J3NhdmUnfSl9KQ==%22%29%29%3C/script%3E%3C',
            'environment' => 'www.getgrav.org',
            'basename' => 'script%3E%3C',
            'base' => 'https://www.getgrav.org',
            'currentPage' => 1,
            'rootUrl' => 'https://www.getgrav.org',
            'extension' => null,
            'addNonce' => 'https://www.getgrav.org/something/%22%3E%3Cscript%3Eeval%28atob%28%22aGlzdG9yeS5wdXNoU3RhdGUoJycsJycsJy8nKTskKCdoZWFkLGJvZHknKS5odG1sKCcnKS5sb2FkKCcvJyk7JC5wb3N0KCcvYWRtaW4nLGZ1bmN0aW9uKGRhdGEpeyQucG9zdCgkKGRhdGEpLmZpbmQoJ1tpZD1hZG1pbi11c2VyLWRldGFpbHNdIGEnKS5hdHRyKCdocmVmJykseydhZG1pbi1ub25jZSc6JChkYXRhKS5maW5kKCdbZGF0YS1jbGVhci1jYWNoZV0nKS5hdHRyKCdkYXRhLWNsZWFyLWNhY2hlJykuc3BsaXQoJzonKS5wb3AoKS50cmltKCksJ2RhdGFbcGFzc3dvcmRdJzonSW0zdjFsaDR4eDByJywndGFzayc6J3NhdmUnfSl9KQ==%22%29%29%3C/script%3E%3C/nonce:{{nonce}}',
            '__toString' => 'https://www.getgrav.org/something/%22%3E%3Cscript%3Eeval%28atob%28%22aGlzdG9yeS5wdXNoU3RhdGUoJycsJycsJy8nKTskKCdoZWFkLGJvZHknKS5odG1sKCcnKS5sb2FkKCcvJyk7JC5wb3N0KCcvYWRtaW4nLGZ1bmN0aW9uKGRhdGEpeyQucG9zdCgkKGRhdGEpLmZpbmQoJ1tpZD1hZG1pbi11c2VyLWRldGFpbHNdIGEnKS5hdHRyKCdocmVmJykseydhZG1pbi1ub25jZSc6JChkYXRhKS5maW5kKCdbZGF0YS1jbGVhci1jYWNoZV0nKS5hdHRyKCdkYXRhLWNsZWFyLWNhY2hlJykuc3BsaXQoJzonKS5wb3AoKS50cmltKCksJ2RhdGFbcGFzc3dvcmRdJzonSW0zdjFsaDR4eDByJywndGFzayc6J3NhdmUnfSl9KQ==%22%29%29%3C/script%3E%3C'
        ],
    ];

    protected function _before()
    {
        $grav = Fixtures::get('grav');
        $this->grav = $grav();
        $this->uri = $this->grav['uri'];
    }

    protected function _after()
    {
    }

    protected function runTestSet(array $tests, $method, $params = [])
    {
        foreach ($tests as $url => $candidates) {
            if (!array_key_exists($method, $candidates) && $method !== '__toString') {
                continue;
            }
            if ($method === 'addNonce') {
                $nonce = Utils::getNonce('test-action');
                $expected = str_replace('{{nonce}}', $nonce, $candidates[$method]);

                $this->assertSame($expected, Uri::addNonce($url, 'test-action'));

                continue;
            }

            $this->uri->initializeWithURL($url)->init();
            if ($method === '__toString' && !isset($candidates[$method])) {
                $expected = $url;
            } else {
                $expected = $candidates[$method];
            }

            if ($params) {
                $result = call_user_func_array([$this->uri, $method], $params);
            } else {
                $result = $this->uri->{$method}();
            }

            $this->assertSame($expected, $result, "Test \$url->{$method}() for {$url}");
            // Deal with $url->query($key)
            if ($method === 'query') {
                parse_str($expected, $queryParams);
                foreach ($queryParams as $key => $value) {
                    $this->assertSame($value, $this->uri->{$method}($key), "Test \$url->{$method}('{$key}') for {$url}");
                }
                $this->assertNull($this->uri->{$method}('non-existing'), "Test \$url->{$method}('non-existing') for {$url}");
            }
        }
    }

    public function testValidatingHostname()
    {
        $this->assertTrue($this->uri->validateHostname('localhost'));
        $this->assertTrue($this->uri->validateHostname('google.com'));
        $this->assertTrue($this->uri->validateHostname('google.it'));
        $this->assertTrue($this->uri->validateHostname('goog.le'));
        $this->assertTrue($this->uri->validateHostname('goog.wine'));
        $this->assertTrue($this->uri->validateHostname('goog.localhost'));

        $this->assertFalse($this->uri->validateHostname('localhost:80') );
        $this->assertFalse($this->uri->validateHostname('http://localhost'));
        $this->assertFalse($this->uri->validateHostname('localhost!'));
    }

    public function testToString()
    {
        $this->runTestSet($this->tests, '__toString');
    }

    public function testScheme()
    {
        $this->runTestSet($this->tests, 'scheme');
    }

    public function testUser()
    {
        $this->runTestSet($this->tests, 'user');
    }

    public function testPassword()
    {
        $this->runTestSet($this->tests, 'password');
    }

    public function testHost()
    {
        $this->runTestSet($this->tests, 'host');
    }

    public function testPort()
    {
        $this->runTestSet($this->tests, 'port');
    }

    public function testPath()
    {
        $this->runTestSet($this->tests, 'path');
    }

    public function testQuery()
    {
        $this->runTestSet($this->tests, 'query');
    }

    public function testFragment()
    {
        $this->runTestSet($this->tests, 'fragment');

        $this->uri->fragment('something-new');
        $this->assertSame('something-new', $this->uri->fragment());
    }

    public function testPaths()
    {
        $this->runTestSet($this->tests, 'paths');
    }

    public function testRoute()
    {
        $this->runTestSet($this->tests, 'route');
    }

    public function testParams()
    {
        $this->runTestSet($this->tests, 'params');

        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper:xxx')->init();
        $this->assertSame('/ueper:xxx', $this->uri->params('ueper'));
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper:xxx/test:yyy')->init();
        $this->assertSame('/ueper:xxx', $this->uri->params('ueper'));
        $this->assertSame('/test:yyy', $this->uri->params('test'));
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper:xxx++/test:yyy')->init();
        $this->assertSame('/ueper:xxx++/test:yyy', $this->uri->params());
        $this->assertSame('/ueper:xxx++', $this->uri->params('ueper'));
        $this->assertSame('/test:yyy', $this->uri->params('test'));
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper:xxx++/test:yyy#something')->init();
        $this->assertSame('/ueper:xxx++/test:yyy', $this->uri->params());
        $this->assertSame('/ueper:xxx++', $this->uri->params('ueper'));
        $this->assertSame('/test:yyy', $this->uri->params('test'));
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper:xxx++/test:yyy?foo=bar')->init();
        $this->assertSame('/ueper:xxx++/test:yyy', $this->uri->params());
        $this->assertSame('/ueper:xxx++', $this->uri->params('ueper'));
        $this->assertSame('/test:yyy', $this->uri->params('test'));
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper?test=x')->init();
        $this->assertNull($this->uri->params());
        $this->assertNull($this->uri->params('ueper'));
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper?test=x&test2=y')->init();
        $this->assertNull($this->uri->params());
        $this->assertNull($this->uri->params('ueper'));
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper?test=x&test2=y&test3=x&test4=y')->init();
        $this->assertNull($this->uri->params());
        $this->assertNull($this->uri->params('ueper'));
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper?test=x&test2=y&test3=x&test4=y/test')->init();
        $this->assertNull($this->uri->params());
        $this->assertNull($this->uri->params('ueper'));
        $this->uri->initializeWithURL('http://localhost:8080/a/b/c/d')->init();
        $this->assertNull($this->uri->params());
        $this->assertNull($this->uri->params('ueper'));
        $this->uri->initializeWithURL('http://localhost:8080/a/b/c/d/e/f/a/b/c/d/e/f/a/b/c/d/e/f')->init();
        $this->assertNull($this->uri->params());
        $this->assertNull($this->uri->params('ueper'));
    }

    public function testParam()
    {
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper:xxx')->init();
        $this->assertSame('xxx', $this->uri->param('ueper'));
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper:xxx/test:yyy')->init();
        $this->assertSame('xxx', $this->uri->param('ueper'));
        $this->assertSame('yyy', $this->uri->param('test'));
        $this->uri->initializeWithURL('http://localhost:8080/grav/it/ueper:xxx++/test:yy%20y/foo:bar_baz-bank')->init();
        $this->assertSame('xxx++', $this->uri->param('ueper'));
        $this->assertSame('yy y', $this->uri->param('test'));
        $this->assertSame('bar_baz-bank', $this->uri->param('foo'));
    }

    public function testUrl()
    {
        $this->runTestSet($this->tests, 'url');
    }

    public function testExtension()
    {
        $this->runTestSet($this->tests, 'extension');

        $this->uri->initializeWithURL('http://localhost/a-page')->init();
        $this->assertSame('x', $this->uri->extension('x'));
    }

    public function testEnvironment()
    {
        $this->runTestSet($this->tests, 'environment');
    }

    public function testBasename()
    {
        $this->runTestSet($this->tests, 'basename');
    }

    public function testBase()
    {
        $this->runTestSet($this->tests, 'base');
    }

    public function testRootUrl()
    {
        $this->runTestSet($this->tests, 'rootUrl', [true]);

        $this->uri->initializeWithUrlAndRootPath('https://localhost/grav/page-foo', '/grav')->init();
        $this->assertSame('/grav', $this->uri->rootUrl());
        $this->assertSame('https://localhost/grav', $this->uri->rootUrl(true));
    }

    public function testCurrentPage()
    {
        $this->runTestSet($this->tests, 'currentPage');

        $this->uri->initializeWithURL('http://localhost:8080/a-page/page:2')->init();
        $this->assertSame('2', $this->uri->currentPage());
    }

    public function testReferrer()
    {
        $this->uri->initializeWithURL('http://localhost/foo/page:test')->init();
        $this->assertSame('/foo', $this->uri->referrer());
        $this->uri->initializeWithURL('http://localhost/foo/bar/page:test')->init();
        $this->assertSame('/foo/bar', $this->uri->referrer());
    }

    public function testIp()
    {
        $this->uri->initializeWithURL('http://localhost/foo/page:test')->init();
        $this->assertSame('UNKNOWN', Uri::ip());
    }

    public function testIsExternal()
    {
        $this->uri->initializeWithURL('http://localhost/')->init();
        $this->assertFalse(Uri::isExternal('/test'));
        $this->assertFalse(Uri::isExternal('/foo/bar'));
        $this->assertTrue(Uri::isExternal('http://localhost/test'));
        $this->assertTrue(Uri::isExternal('http://google.it/test'));
    }

    public function testBuildUrl()
    {
        $parsed_url = [
            'scheme' => 'http',
            'host'   => 'localhost',
            'port'   => 8080,
        ];

        $this->assertSame('http://localhost:8080', Uri::buildUrl($parsed_url));

        $parsed_url = [
            'scheme'   => 'http',
            'host'     => 'localhost',
            'port'     => 8080,
            'user'     => 'foo',
            'pass'     => 'bar',
            'path'     => '/test',
            'query'    => 'x=2',
            'fragment' => 'xxx',
        ];

        $this->assertSame('http://foo:bar@localhost:8080/test?x=2#xxx', Uri::buildUrl($parsed_url));
    }

    public function testConvertUrl()
    {

    }

    public function testAddNonce()
    {
        $this->runTestSet($this->tests, 'addNonce');
    }
}
