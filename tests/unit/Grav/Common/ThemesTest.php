<?php

use Codeception\Util\Fixtures;
use Grav\Common\Data\Blueprint;
use Grav\Common\Grav;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;
use RocketTheme\Toolbox\StreamWrapper\ReadOnlyStream;

class ThemesTest extends \PHPUnit\Framework\TestCase
{
    /** @var Grav */
    protected $grav;

    /** @var string */
    protected $themeDir;

    /** @var bool */
    protected $registeredThemeStream = false;

    protected function setUp(): void
    {
        parent::setUp();

        $grav = Fixtures::get('grav');
        $this->grav = $grav();
        $this->themeDir = sys_get_temp_dir() . '/grav-theme-blueprints-' . bin2hex(random_bytes(6));
        mkdir($this->themeDir, 0775, true);

        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];
        $locator->resetScheme('theme');
        $locator->addPath('theme', '', $this->themeDir);

        if (!in_array('theme', stream_get_wrappers(), true)) {
            stream_wrapper_register('theme', ReadOnlyStream::class);
            $this->registeredThemeStream = true;
        }

        $this->grav['config']->set('system.pages.theme', 'testing');
    }

    protected function tearDown(): void
    {
        $this->grav['locator']->resetScheme('theme');
        if ($this->registeredThemeStream) {
            stream_wrapper_unregister('theme');
        }

        $this->removeDirectory($this->themeDir);

        parent::tearDown();
    }

    public function testInitThemeRegistersBlueprintsWithoutPagesDirectory(): void
    {
        $file = $this->writeBlueprint('flex-objects/issue-4303.yaml');

        $this->grav['themes']->initTheme();

        self::assertSame($file, $this->grav['locator']->findResource('blueprints://flex-objects/issue-4303.yaml'));
    }

    public function testInitThemeStillRegistersPageBlueprints(): void
    {
        $file = $this->writeBlueprint('pages/issue-4303.yaml');

        $this->grav['themes']->initTheme();

        self::assertSame($file, $this->grav['locator']->findResource('blueprints://pages/issue-4303.yaml'));
    }

    public function testInitThemeRegistersMultipleNamespacesIndependently(): void
    {
        $pagesFile = $this->writeBlueprint('pages/issue-4303.yaml');
        $flexFile = $this->writeBlueprint('flex-objects/issue-4303.yaml');

        $this->grav['themes']->initTheme();

        self::assertSame($pagesFile, $this->grav['locator']->findResource('blueprints://pages/issue-4303.yaml'));
        self::assertSame($flexFile, $this->grav['locator']->findResource('blueprints://flex-objects/issue-4303.yaml'));
    }

    /**
     * Regression test for the reverted 8488c81d7: mounting the whole
     * blueprints/ tree at the blueprints:// root let a theme's own
     * root-level, self-extending blueprint (Quark's blueprints/default.yaml,
     * `extends@: default`) shadow the stream root and recurse into itself.
     * Root-level files must never resolve through blueprints:// at all.
     */
    public function testInitThemeDoesNotMountRootLevelBlueprintsAtStreamRoot(): void
    {
        $this->writeBlueprint('default.yaml', "extends@: default\ntitle: Issue 4303\n");

        $this->grav['themes']->initTheme();

        self::assertFalse($this->grav['locator']->findResource('blueprints://default.yaml'));
    }

    /**
     * A theme's own pages/ blueprint extending the system default is a
     * different, already-supported case from the Quark collision above: the
     * system default lives at blueprints://pages/default.yaml, a slot that
     * already accepts theme/user overrides (locator override groups return
     * every registered layer via findResources(), not just the closest one),
     * so `extends@: default` here must inherit system fields rather than
     * resolve back to itself or find nothing.
     */
    public function testThemePageBlueprintExtendsSystemDefaultWithoutRecursion(): void
    {
        $this->writeBlueprint('pages/issue-4303-override.yaml', "extends@: default\ntitle: Theme Override\n");

        $this->grav['themes']->initTheme();

        $blueprint = new Blueprint('issue-4303-override');
        $blueprint->setContext('blueprints://pages');
        $blueprint->load()->init();

        // The theme's own title wins over the inherited one, but the rest of
        // the system default (the whole tabbed field structure) still comes
        // through the extends@ chain.
        self::assertSame('Theme Override', $blueprint->get('title'));
        self::assertArrayHasKey('content', $blueprint->get('form/fields/tabs/fields'));
    }

    protected function writeBlueprint(string $path, string $content = "title: Issue 4303\n"): string
    {
        $file = $this->themeDir . '/blueprints/' . $path;
        mkdir(dirname($file), 0775, true);
        file_put_contents($file, $content);

        return $file;
    }

    protected function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        foreach (scandir($directory) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory . '/' . $entry;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }

        rmdir($directory);
    }
}
