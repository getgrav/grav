<?php

use Codeception\Util\Fixtures;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use Grav\Console\Application\CommandLoader\PluginCommandLoader;

/**
 * A plugin's console commands answer to their name and to every alias they declare.
 */
class PluginCommandLoaderTest extends \PHPUnit\Framework\TestCase
{
    /** @var string */
    protected $folder;

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $grav();

        $this->folder = 'tmp/plugin-command-loader-' . bin2hex(random_bytes(4));
        $class = 'ZzAliasProbe' . bin2hex(random_bytes(3)) . 'Command';
        mkdir(GRAV_ROOT . "/{$this->folder}/aliasprobe/cli", 0777, true);
        file_put_contents(GRAV_ROOT . "/{$this->folder}/aliasprobe/cli/{$class}.php", <<<PHP
<?php
namespace Grav\\Plugin\\Console;

class {$class} extends \\Symfony\\Component\\Console\\Command\\Command
{
    protected function configure(): void
    {
        \$this->setName('probe')->setAliases(['pr', 'pb']);
    }
}
PHP);
        Grav::instance()['locator']->addPath('plugins', '', $this->folder, false);
    }

    protected function tearDown(): void
    {
        Folder::delete(GRAV_ROOT . '/' . $this->folder);
        parent::tearDown();
    }

    public function testCommandsAnswerToTheirAliases(): void
    {
        $loader = new PluginCommandLoader('aliasprobe');

        self::assertEqualsCanonicalizing(['probe', 'pr', 'pb'], $loader->getNames());
        self::assertSame($loader->get('probe'), $loader->get('pr'));
        self::assertSame($loader->get('probe'), $loader->get('pb'));
        self::assertFalse($loader->has('nope'));
    }
}
