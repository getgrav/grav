<?php

use Codeception\Util\Fixtures;
use Grav\Common\Data\Blueprint;
use Grav\Common\Grav;

/**
 * A provider that is deliberately NOT on Blueprint's dynamic-callable allowlist.
 */
class BlueprintDynamicProvenanceProbe
{
    /** @var int */
    public static $calls = 0;

    public static function hit(): string
    {
        self::$calls++;

        return 'probe-ran';
    }
}

/**
 * Class BlueprintDynamicConfigProvenanceTest
 *
 * Page-authored `config-*@` directives read configuration through the same
 * redacting facade as sandboxed page Twig, and the schema applies the same
 * dynamic-call guard as Blueprint::init() when it resolves directives again.
 *
 * Naming convention: test{Method}_{issue}_{description}
 */
class BlueprintDynamicConfigProvenanceTest extends \PHPUnit\Framework\TestCase
{
    /** @var Grav */
    protected $grav;

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $this->grav = $grav();

        $config = $this->grav['config'];
        $config->set('plugins.zzprobe.secret', 'S3CRET-plugin');
        $config->set('security.zzprobe', 'S3CRET-security');
        $config->set('site.zzprobe', 'public-site-value');
        $config->set('zzprobe.secret', 'S3CRET-custom');
        $config->set('system.http.proxy_url', 'S3CRET-proxy');
        $config->set('security.twig_content.config_access', false);

        BlueprintDynamicProvenanceProbe::$calls = 0;
    }

    protected function tearDown(): void
    {
        $config = $this->grav['config'];
        $config->undef('plugins.zzprobe');
        $config->undef('security.zzprobe');
        $config->undef('site.zzprobe');
        $config->undef('zzprobe');
        $config->set('system.http.proxy_url', null);
        $config->set('security.twig_content.config_access', false);

        parent::tearDown();
    }

    /**
     * @param bool|null $trusted
     * @return Blueprint
     */
    protected function blueprint(?bool $trusted): Blueprint
    {
        // The shape the form plugin builds from page frontmatter.
        $items = ['form' => ['fields' => [
            'plugin' => ['type' => 'text', 'config-default@' => 'plugins.zzprobe.secret'],
            'salt' => ['type' => 'text', 'config-placeholder@' => 'security.zzprobe'],
            'site' => ['type' => 'text', 'config-default@' => 'site.zzprobe'],
            'data' => ['type' => 'text', 'data-default@' => 'BlueprintDynamicProvenanceProbe::hit'],
        ]]];

        $blueprint = new Blueprint('zzprobe', $items, $trusted);
        $blueprint->load()->init();

        return $blueprint;
    }

    public function testInit_pageAuthoredConfigDirectiveIsRedacted(): void
    {
        $blueprint = $this->blueprint(null);
        self::assertFalse($blueprint->isTrusted());

        $fields = $blueprint->get('form/fields');
        self::assertNotSame('S3CRET-plugin', $fields['plugin']['default'] ?? null);
        self::assertNotSame('S3CRET-security', $fields['salt']['placeholder'] ?? null);
        // Paths sandboxed Twig may read still resolve.
        self::assertSame('public-site-value', $fields['site']['default'] ?? null);
    }

    public function testReadDynamicConfig_configAccessOffLimitsPageAuthoredDirectiveToSandboxTrees(): void
    {
        // Trees config_denied_paths does not name (groups, a custom user/config file)
        // are still out of reach: sandboxed Twig has an empty `config`.
        self::assertNull(Blueprint::readDynamicConfig('zzprobe.secret', null, false));
        self::assertNull(Blueprint::readDynamicConfig('zzprobe', null, false));
        self::assertSame('fallback', Blueprint::readDynamicConfig('', 'fallback', false));

        // The trees sandboxed Twig has as `system`, `site` and `theme`, still redacted.
        self::assertSame('public-site-value', Blueprint::readDynamicConfig('site.zzprobe', null, false));
        self::assertNotNull(Blueprint::readDynamicConfig('system.pages.theme', null, false));
        self::assertNull(Blueprint::readDynamicConfig('system.http.proxy_url', null, false));

        // A blueprint file keeps the raw config.
        self::assertSame('S3CRET-custom', Blueprint::readDynamicConfig('zzprobe.secret', null, true));
    }

    public function testReadDynamicConfig_configAccessOnLeavesOnlyDeniedPathsRedacted(): void
    {
        $this->grav['config']->set('security.twig_content.config_access', true);

        self::assertSame('S3CRET-custom', Blueprint::readDynamicConfig('zzprobe.secret', null, false));
        self::assertNull(Blueprint::readDynamicConfig('plugins.zzprobe.secret', null, false));
    }

    public function testGetDefaults_schemaDoesNotResolvePageAuthoredDirectives(): void
    {
        $defaults = $this->blueprint(null)->getDefaults();
        $flat = json_encode($defaults);

        self::assertStringNotContainsString('S3CRET-plugin', (string) $flat);
        self::assertStringNotContainsString('probe-ran', (string) $flat);
        self::assertSame(0, BlueprintDynamicProvenanceProbe::$calls, 'BlueprintSchema ran a page-authored data-*@ provider');
    }

    public function testInit_trustedBlueprintStillReadsConfig(): void
    {
        $blueprint = $this->blueprint(true);

        $fields = $blueprint->get('form/fields');
        self::assertSame('S3CRET-plugin', $fields['plugin']['default'] ?? null);
        self::assertSame('S3CRET-security', $fields['salt']['placeholder'] ?? null);
        self::assertSame('S3CRET-plugin', $blueprint->getDefaults()['form']['fields']['plugin'] ?? $blueprint->getDefaults()['plugin'] ?? null);
    }

    /**
     * Fields spelling their dynamic calls as the `data:` table the schema reads,
     * which carries no `@` key for Blueprint::extend() to attribute.
     *
     * @return array
     */
    protected function dataTableForm(): array
    {
        return ['form' => ['fields' => [
            'plugin' => ['type' => 'text', 'data' => ['default' => ['action' => 'config', 'params' => 'plugins.zzprobe.secret']]],
            'site' => ['type' => 'text', 'data' => ['default' => ['action' => 'config', 'params' => 'site.zzprobe']]],
            'data' => ['type' => 'text', 'data' => ['default' => ['action' => 'data', 'params' => 'BlueprintDynamicProvenanceProbe::hit']]],
        ]]];
    }

    /**
     * Models FlexForm::getBlueprint() / FlexDirectoryForm::getBlueprint(), which
     * append a page-frontmatter form to a file-defined blueprint.
     */
    public function testGetDefaults_schemaDoesNotTrustPageAuthoredDataTable(): void
    {
        $blueprint = new Blueprint(null, ['form' => ['fields' => [
            'own' => ['type' => 'text', 'data-default@' => 'BlueprintDynamicProvenanceProbe::hit'],
        ]]], true);
        $blueprint->extend($this->dataTableForm(), true);
        $blueprint->init();

        $defaults = $blueprint->getDefaults();

        // The table is dropped from page-authored fields; the fields themselves stay.
        self::assertArrayNotHasKey('plugin', $defaults);
        self::assertArrayNotHasKey('data', $defaults);
        self::assertArrayNotHasKey('site', $defaults);
        self::assertSame('text', $blueprint->get('form/fields/data/type'));
        self::assertNull($blueprint->get('form/fields/data/data'));
        // The blueprint's own directive was resolved by init() and keeps its value.
        self::assertSame('probe-ran', $defaults['own'] ?? null);
        self::assertSame(1, BlueprintDynamicProvenanceProbe::$calls, 'BlueprintSchema ran a page-authored data: table provider');
    }

    public function testGetDefaults_trustedDataTableStillResolves(): void
    {
        $blueprint = new Blueprint(null, ['form' => ['fields' => ['title' => ['type' => 'text']]]], true);
        $blueprint->extend($this->dataTableForm(), true, true);
        $blueprint->init();

        $defaults = $blueprint->getDefaults();

        self::assertSame('S3CRET-plugin', $defaults['plugin'] ?? null);
        self::assertSame('probe-ran', $defaults['data'] ?? null);
    }
}
