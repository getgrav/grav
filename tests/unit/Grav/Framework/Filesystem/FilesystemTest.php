<?php

use Grav\Framework\Filesystem\Filesystem;

class FilesystemTest extends \Codeception\TestCase\Test
{
    protected $class;

    protected $tests = [
        '' => [
            'parent' => '',
            'normalize' => '',
            'dirname' => '',
            'pathinfo' => [
                'basename' => '',
                'filename' => '',
            ]
        ],
        '.' => [
            'parent' => '',
            'normalize' => '',
            'dirname' => '.',
            'pathinfo' => [
                'dirname' => '.',
                'basename' => '.',
                'extension' => '',
                'filename' => '',
            ]
        ],
        './' => [
            'parent' => '',
            'normalize' => '',
            'dirname' => '.',
            'pathinfo' => [
                'dirname' => '.',
                'basename' => '.',
                'extension' => '',
                'filename' => '',
            ]
        ],
        '././.' => [
            'parent' => '',
            'normalize' => '',
            'dirname' => './.',
            'pathinfo' => [
                'dirname' => './.',
                'basename' => '.',
                'extension' => '',
                'filename' => '',
            ]
        ],
        '.file' => [
            'parent' => '.',
            'normalize' => '.file',
            'dirname' => '.',
            'pathinfo' => [
                'dirname' => '.',
                'basename' => '.file',
                'extension' => 'file',
                'filename' => '',
            ]
        ],
        '/' => [
            'parent' => '',
            'normalize' => '/',
            'dirname' => '/',
            'pathinfo' => [
                'dirname' => '/',
                'basename' => '',
                'filename' => '',
            ]
        ],
        '/absolute' => [
            'parent' => '/',
            'normalize' => '/absolute',
            'dirname' => '/',
            'pathinfo' => [
                'dirname' => '/',
                'basename' => 'absolute',
                'filename' => 'absolute',
            ]
        ],
        '/absolute/' => [
            'parent' => '/',
            'normalize' => '/absolute',
            'dirname' => '/',
            'pathinfo' => [
                'dirname' => '/',
                'basename' => 'absolute',
                'filename' => 'absolute',
            ]
        ],
        '/very/long/absolute/path' => [
            'parent' => '/very/long/absolute',
            'normalize' => '/very/long/absolute/path',
            'dirname' => '/very/long/absolute',
            'pathinfo' => [
                'dirname' => '/very/long/absolute',
                'basename' => 'path',
                'filename' => 'path',
            ]
        ],
        '/very/long/absolute/../path' => [
            'parent' => '/very/long',
            'normalize' => '/very/long/path',
            'dirname' => '/very/long/absolute/..',
            'pathinfo' => [
                'dirname' => '/very/long/absolute/..',
                'basename' => 'path',
                'filename' => 'path',
            ]
        ],
        'relative' => [
            'parent' => '.',
            'normalize' => 'relative',
            'dirname' => '.',
            'pathinfo' => [
                'dirname' => '.',
                'basename' => 'relative',
                'filename' => 'relative',
            ]
        ],
        'very/long/relative/path' => [
            'parent' => 'very/long/relative',
            'normalize' => 'very/long/relative/path',
            'dirname' => 'very/long/relative',
            'pathinfo' => [
                'dirname' => 'very/long/relative',
                'basename' => 'path',
                'filename' => 'path',
            ]
        ],
        'path/to/file.jpg' => [
            'parent' => 'path/to',
            'normalize' => 'path/to/file.jpg',
            'dirname' => 'path/to',
            'pathinfo' => [
                'dirname' => 'path/to',
                'basename' => 'file.jpg',
                'extension' => 'jpg',
                'filename' => 'file',
            ]
        ],
        'user://' => [
            'parent' => '',
            'normalize' => 'user://',
            'dirname' => 'user://',
            'pathinfo' => [
                'dirname' => 'user://',
                'basename' => '',
                'filename' => '',
                'scheme' => 'user',
            ]
        ],
        'user://.' => [
            'parent' => '',
            'normalize' => 'user://',
            'dirname' => 'user://',
            'pathinfo' => [
                'dirname' => 'user://',
                'basename' => '',
                'filename' => '',
                'scheme' => 'user',
            ]
        ],
        'user://././.' => [
            'parent' => '',
            'normalize' => 'user://',
            'dirname' => 'user://',
            'pathinfo' => [
                'dirname' => 'user://',
                'basename' => '',
                'filename' => '',
                'scheme' => 'user',
            ]
        ],
        'user://./././file' => [
            'parent' => 'user://',
            'normalize' => 'user://file',
            'dirname' => 'user://',
            'pathinfo' => [
                'dirname' => 'user://',
                'basename' => 'file',
                'filename' => 'file',
                'scheme' => 'user',
            ]
        ],
        'user://./././folder/file' => [
            'parent' => 'user://folder',
            'normalize' => 'user://folder/file',
            'dirname' => 'user://folder',
            'pathinfo' => [
                'dirname' => 'user://folder',
                'basename' => 'file',
                'filename' => 'file',
                'scheme' => 'user',
            ]
        ],
        'user://.file' => [
            'parent' => 'user://',
            'normalize' => 'user://.file',
            'dirname' => 'user://',
            'pathinfo' => [
                'dirname' => 'user://',
                'basename' => '.file',
                'extension' => 'file',
                'filename' => '',
                'scheme' => 'user',
            ]
        ],
        'user:///' => [
            'parent' => '',
            'normalize' => 'user:///',
            'dirname' => 'user:///',
            'pathinfo' => [
                'dirname' => 'user:///',
                'basename' => '',
                'filename' => '',
                'scheme' => 'user',
            ]
        ],
        'user:///absolute' => [
            'parent' => 'user:///',
            'normalize' => 'user:///absolute',
            'dirname' => 'user:///',
            'pathinfo' => [
                'dirname' => 'user:///',
                'basename' => 'absolute',
                'filename' => 'absolute',
                'scheme' => 'user',
            ]
        ],
        'user:///very/long/absolute/path' => [
            'parent' => 'user:///very/long/absolute',
            'normalize' => 'user:///very/long/absolute/path',
            'dirname' => 'user:///very/long/absolute',
            'pathinfo' => [
                'dirname' => 'user:///very/long/absolute',
                'basename' => 'path',
                'filename' => 'path',
                'scheme' => 'user',
            ]
        ],
        'user://relative' => [
            'parent' => 'user://',
            'normalize' => 'user://relative',
            'dirname' => 'user://',
            'pathinfo' => [
                'dirname' => 'user://',
                'basename' => 'relative',
                'filename' => 'relative',
                'scheme' => 'user',
            ]
        ],
        'user://very/long/relative/path' => [
            'parent' => 'user://very/long/relative',
            'normalize' => 'user://very/long/relative/path',
            'dirname' => 'user://very/long/relative',
            'pathinfo' => [
                'dirname' => 'user://very/long/relative',
                'basename' => 'path',
                'filename' => 'path',
                'scheme' => 'user',
            ]
        ],
        'user://path/to/file.jpg' => [
            'parent' => 'user://path/to',
            'normalize' => 'user://path/to/file.jpg',
            'dirname' => 'user://path/to',
            'pathinfo' => [
                'dirname' => 'user://path/to',
                'basename' => 'file.jpg',
                'extension' => 'jpg',
                'filename' => 'file',
                'scheme' => 'user',
            ]
        ],
    ];

    protected function _before()
    {
        $this->class = Filesystem::getInstance();
    }

    protected function _after()
    {
        unset($this->class);
    }

    protected function runTestSet(array $tests, $method)
    {
        $class = $this->class;
        foreach ($tests as $path => $candidates) {
            if (!array_key_exists($method, $candidates)) {
                continue;
            }

            $expected = $candidates[$method];

            $result = $class->{$method}($path);

            $this->assertSame($expected, $result, "Test {$method}('{$path}')");

            if (function_exists($method) && !strpos($path, '://')) {
                $cmp_result = $method($path);

                $this->assertSame($cmp_result, $result, "Compare to original {$method}('{$path}')");
            }
        }
    }

    public function testParent()
    {
        $this->runTestSet($this->tests, 'parent');
    }

    public function testNormalize()
    {
        $this->runTestSet($this->tests, 'normalize');
    }

    public function testDirname()
    {
        $this->runTestSet($this->tests, 'dirname');
    }

    public function testPathinfo()
    {
        $this->runTestSet($this->tests, 'pathinfo');
    }
}
