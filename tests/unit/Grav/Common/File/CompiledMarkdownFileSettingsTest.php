<?php

use Grav\Common\File\CompiledMarkdownFile;
use RocketTheme\Toolbox\File\YamlFile;

/**
 * Page frontmatter uses the same YAML parser settings as the configuration files.
 */
class CompiledMarkdownFileSettingsTest extends \PHPUnit\Framework\TestCase
{
    /** @var array */
    protected $globals;

    protected function setUp(): void
    {
        parent::setUp();
        $this->globals = YamlFile::globalSettings();
    }

    protected function tearDown(): void
    {
        YamlFile::globalSettings($this->globals);
        CompiledMarkdownFile::$nativeYaml = false;
        parent::tearDown();
    }

    public function testNativeIsOffUnlessOptedIn(): void
    {
        $file = CompiledMarkdownFile::instance(sys_get_temp_dir() . '/grav-md-settings-' . bin2hex(random_bytes(4)) . '.md');

        YamlFile::globalSettings(['compat' => true, 'native' => true]);
        self::assertNotTrue($file->setting('native'));
    }

    public function testNativeFollowsTheYamlGlobalSettings(): void
    {
        $file = CompiledMarkdownFile::instance(sys_get_temp_dir() . '/grav-md-settings-' . bin2hex(random_bytes(4)) . '.md');
        CompiledMarkdownFile::$nativeYaml = true;

        YamlFile::globalSettings(['compat' => true, 'native' => true]);
        self::assertTrue($file->setting('native'));

        YamlFile::globalSettings(['compat' => true, 'native' => false]);
        self::assertFalse($file->setting('native'));

        // A file's own setting still wins.
        $file->settings(['native' => true]);
        self::assertTrue($file->setting('native'));
    }

    public function testCompatKeepsItsMarkdownDefault(): void
    {
        $file = CompiledMarkdownFile::instance(sys_get_temp_dir() . '/grav-md-settings-' . bin2hex(random_bytes(4)) . '.md');

        YamlFile::globalSettings(['compat' => false, 'native' => true]);
        self::assertTrue($file->setting('compat', true));
        self::assertNull($file->setting('inline'));
    }
}
