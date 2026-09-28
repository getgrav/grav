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

        BlueprintDynamicProvenanceProbe::$calls = 0;
    }

    protected function tearDown(): void
    {
        $config = $this->grav['config'];
        $config->undef('plugins.zzprobe');
        $config->undef('security.zzprobe');
        $config->undef('site.zzprobe');

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
}
