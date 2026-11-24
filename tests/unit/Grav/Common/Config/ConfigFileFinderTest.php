<?php

use Codeception\Util\Fixtures;
use Grav\Common\Config\CompiledConfig;
use Grav\Common\Config\ConfigFileFinder;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;

/**
 * Only files are configuration files. A folder whose name matches the lookup (`x.yaml/`) must never
 * be listed: it would be loaded as an empty file and joined over the setting it is named after.
 */
class ConfigFileFinderTest extends \PHPUnit\Framework\TestCase
{
    /** @var Grav */
    protected $grav;

    /** @var string */
    protected $workDir;

    protected function setUp(): void
    {
        parent::setUp();

        $grav = Fixtures::get('grav');
        $this->grav = $grav();

        $this->workDir = sys_get_temp_dir() . '/grav-config-file-finder-' . bin2hex(random_bytes(4));

        // Configuration tree: real files, plus folders named like configuration files.
        $this->file('config/system.yaml');
        $this->file('config/plugins/email.yaml');
        mkdir($this->workDir . '/config/system/debugger/enabled/x.yaml', 0777, true);
        // Files below such a folder are still found.
        $this->file('config/site.yaml/nested.yaml');

        // Plugin tree: one real plugin, plus folders named like the looked up files.
        $this->file('plugins/real/real.yaml');
        $this->file('plugins/real/blueprints.yaml');
        mkdir($this->workDir . '/plugins/fake/fake.yaml', 0777, true);
        mkdir($this->workDir . '/plugins/fake/blueprints.yaml', 0777, true);
    }

    protected function tearDown(): void
    {
        Folder::delete($this->workDir);

        parent::tearDown();
    }

    public function testLocateFilesIgnoresFolders(): void
    {
        $path = $this->workDir . '/config';
        $list = (new ConfigFileFinder)->locateFiles([$path]);

        self::assertSame(['plugins/email', 'site.yaml/nested', 'system'], array_keys(reset($list)));
    }

    public function testGetFilesIgnoresFolders(): void
    {
        $path = $this->workDir . '/config';
        $list = (new ConfigFileFinder)->getFiles([$path]);

        self::assertSame(['plugins/email', 'site.yaml/nested', 'system'], array_keys($list));
    }

    public function testListFilesIgnoresFolders(): void
    {
        $path = $this->workDir . '/config';
        $list = (new ConfigFileFinder)->listFiles([$path]);

        self::assertSame(['plugins/email', 'site.yaml/nested', 'system'], array_keys($list));
    }

    public function testLocateInFoldersIgnoresFolders(): void
    {
        $path = $this->workDir . '/plugins';

        $list = (new ConfigFileFinder)->setBase('plugins')->locateInFolders([$path]);
        self::assertSame(['plugins/real'], array_keys(reset($list)));

        $list = (new ConfigFileFinder)->setBase('plugins')->locateInFolders([$path], 'blueprints');
        self::assertSame(['plugins/real'], array_keys(reset($list)));

        $list = (new ConfigFileFinder)->setBase('plugins')->locateFileInFolder('blueprints', [$path]);
        self::assertSame(['plugins/real'], array_keys($list));
    }

    /**
     * The whole chain: what the finder lists is what the compiled configuration is built from.
     */
    public function testFolderCannotOverrideSetting(): void
    {
        $this->file('config/system.yaml', "debugger:\n  enabled: false\n  provider: clockwork\n");
        mkdir($this->workDir . '/config/system/debugger/provider/x.yaml', 0777, true);
        mkdir($this->workDir . '/cache');

        // Outside of GRAV_ROOT the finder returns the absolute path without its leading slash.
        $root = str_starts_with($this->workDir, '/') ? '/' : '';
        $files = (new ConfigFileFinder)->locateFiles([$this->workDir . '/config']);
        $config = (new CompiledConfig($this->workDir . '/cache', $files, $root))->name('master-test')->load();

        self::assertFalse($config->get('system.debugger.enabled'));
        self::assertSame('clockwork', $config->get('system.debugger.provider'));
    }

    private function file(string $name, string $content = "enabled: true\n"): void
    {
        $filename = $this->workDir . '/' . $name;
        if (!is_dir(dirname($filename))) {
            mkdir(dirname($filename), 0777, true);
        }
        file_put_contents($filename, $content);
    }
}
