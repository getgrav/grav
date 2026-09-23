<?php

use Composer\Autoload\ClassLoader;
use Grav\Common\Helpers\PluginAutoloader;

/**
 * The plugin autoloader index must find the same file for every class as asking the plugin
 * loaders one by one, in order: plugins that ship the same library keep loading the first copy.
 */
class PluginAutoloaderTest extends \PHPUnit\Framework\TestCase
{
    /** @var string */
    private $root;

    /** @var ClassLoader[] */
    private $loaders = [];

    /** @var string Namespace unique to this test run. */
    private $ns;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir() . '/grav-plugin-autoloader-' . bin2hex(random_bytes(4));
        $ns = 'GravAutoloadTest' . bin2hex(random_bytes(3));

        // a: PSR-4 for the shared namespace. b: a class map entry for the same class, and a deeper
        // prefix. c: the widest prefix, with its own copy of b's deeper class.
        $this->write("a/Dup.php", $ns, 'Shared', 'Dup', 'a');
        $this->write("b/map/Dup.php", $ns, 'Shared', 'Dup', 'b');
        $this->write("b/deep/Thing.php", $ns, 'Shared\\Deep', 'Thing', 'b');
        $this->write("c/Shared/Deep/Thing.php", $ns, 'Shared\\Deep', 'Thing', 'c');
        $this->write("c/Only.php", $ns, '', 'Only', 'c');
        $this->write("c/Shared/Dup.php", $ns, 'Shared', 'Dup', 'c');

        $a = new ClassLoader();
        $a->addPsr4("{$ns}\\Shared\\", $this->root . '/a');
        $b = new ClassLoader();
        $b->addClassMap(["{$ns}\\Shared\\Dup" => $this->root . '/b/map/Dup.php']);
        $b->addPsr4("{$ns}\\Shared\\Deep\\", $this->root . '/b/deep');
        $c = new ClassLoader();
        $c->addPsr4("{$ns}\\", $this->root . '/c');
        $this->loaders = [$a, $b, $c];

        $this->ns = $ns;
    }

    protected function tearDown(): void
    {
        foreach ($this->loaders as $loader) {
            $loader->unregister();
        }
        $this->removeDir($this->root);

        parent::tearDown();
    }

    public function testFindsTheSameFileAsTheLoadersInOrder(): void
    {
        $index = new PluginAutoloader($this->loaders);
        $classes = ['Shared\\Dup', 'Shared\\Deep\\Thing', 'Only', 'Shared\\Missing', 'Elsewhere\\Nope'];

        foreach ($classes as $class) {
            $class = "{$this->ns}\\{$class}";
            self::assertSame($this->chain($class), $index->findFile($class), $class);
        }

        self::assertSame($this->root . '/a/Dup.php', $index->findFile("{$this->ns}\\Shared\\Dup"), 'An earlier PSR-4 match beats a later class map');
        self::assertSame($this->root . '/b/deep/Thing.php', $index->findFile("{$this->ns}\\Shared\\Deep\\Thing"), 'An earlier loader beats a later one with a wider prefix');
    }

    public function testClassMapOrderIsKept(): void
    {
        $first = new ClassLoader();
        $first->addClassMap(["{$this->ns}\\Shared\\Dup" => $this->root . '/c/Shared/Dup.php']);
        $index = new PluginAutoloader([$first, $this->loaders[1]]);

        self::assertSame($this->root . '/c/Shared/Dup.php', $index->findFile("{$this->ns}\\Shared\\Dup"));
    }

    public function testRegistersInFrontOfThePluginLoaders(): void
    {
        foreach ($this->loaders as $loader) {
            $loader->register(false);
        }
        $index = new PluginAutoloader($this->loaders);
        $index->register();

        try {
            $stack = array_values(array_filter(spl_autoload_functions(), fn($f) => is_array($f) && in_array($f[0], array_merge([$index], $this->loaders), true)));
            self::assertSame([$index, ...$this->loaders], array_map(static fn($f) => $f[0], $stack));

            $class = "{$this->ns}\\Shared\\Deep\\Thing";
            self::assertSame('b', $class::WHO);
        } finally {
            $index->unregister();
        }
    }

    public function testPrefixAddedLaterStillLoads(): void
    {
        foreach ($this->loaders as $loader) {
            $loader->register(false);
        }
        $index = new PluginAutoloader($this->loaders);
        $index->register();

        try {
            $this->write('late/Late.php', $this->ns, 'Later', 'Late', 'late');
            $this->loaders[0]->addPsr4("{$this->ns}\\Later\\", $this->root . '/late');

            $class = "{$this->ns}\\Later\\Late";
            self::assertSame('late', $class::WHO, 'The plugin loaders behind the index still load what it does not know');
        } finally {
            $index->unregister();
        }
    }

    private function chain(string $class)
    {
        foreach ($this->loaders as $loader) {
            $file = $loader->findFile($class);
            if ($file !== false) {
                return $file;
            }
        }

        return false;
    }

    private function write(string $path, string $ns, string $sub, string $class, string $who): void
    {
        $file = $this->root . '/' . $path;
        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0777, true);
        }
        $namespace = $sub === '' ? $ns : "{$ns}\\{$sub}";
        file_put_contents($file, "<?php\nnamespace {$namespace};\nclass {$class} { const WHO = '{$who}'; }\n");
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($dir);
    }
}
