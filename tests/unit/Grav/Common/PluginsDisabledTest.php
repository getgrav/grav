<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Common\Plugin;
use Grav\Common\Plugins;

/**
 * A disabled plugin's main file must not run (grav#4374): code at its file scope, such as a
 * require of a class whose signature no longer matches core, took the whole site down even
 * with `enabled: false`. The plugin still has to be listed, with its blueprints, and its class
 * must still load when something refers to it.
 */
class PluginsDisabledTest extends \PHPUnit\Framework\TestCase
{
    /** @var Grav */
    protected $grav;

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $this->grav = $grav();

        $locator = $this->grav['locator'];
        $locator->resetScheme('plugins');
        $locator->addPath('plugins', '', [GRAV_ROOT . '/tests/fake/plugins-4374/plugins']);

        $config = $this->grav['config'];
        $config->set('plugins.disabled-probe.enabled', false);
        $config->set('plugins.lazy-probe.enabled', false);
        $config->set('plugins.enabled-probe.enabled', true);

        unset($this->grav['plugins']);
        $this->grav['plugins'] = new Plugins();
        $this->grav['plugins']->setup();
    }

    public function testDisabledPluginFileIsNotRun(): void
    {
        self::assertNotContains($this->file('disabled-probe'), get_included_files());
    }

    public function testEnabledPluginFileStillRuns(): void
    {
        self::assertContains($this->file('enabled-probe'), get_included_files());
        self::assertInstanceOf(\Grav\Plugin\EnabledProbePlugin::class, Plugins::getPlugin('enabled-probe'));
    }

    public function testDisabledPluginIsStillListed(): void
    {
        $plugin = Plugins::getPlugin('disabled-probe');
        self::assertInstanceOf(Plugin::class, $plugin);
        self::assertSame('1.0.0', $plugin->getBlueprint()->get('version'));
        self::assertArrayHasKey('disabled-probe', Plugins::all());
    }

    public function testDisabledPluginClassLoadsOnFirstUse(): void
    {
        self::assertNotContains($this->file('lazy-probe'), get_included_files());
        self::assertTrue(class_exists('Grav\Plugin\LAZYPROBEPLUGIN'));
        self::assertContains($this->file('lazy-probe'), get_included_files());
    }

    private function file(string $name): string
    {
        return realpath(GRAV_ROOT . "/tests/fake/plugins-4374/plugins/{$name}/{$name}.php");
    }
}
