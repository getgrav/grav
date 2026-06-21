<?php

use Grav\Common\Config\Config;
use Grav\Common\Twig\Sandbox\SandboxConfig;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the read-only filtered Config facade injected as the `config`
 * Twig variable inside sandboxed editor renders.
 *
 * Covers GHSA-j274-39qw-32c9: editor-role users dumping plugin secrets via
 * `config.toArray()`. The facade redacts denied dot-prefixes from every read
 * shape (get / value / offsetGet / offsetExists / toArray).
 */
class SandboxConfigTest extends TestCase
{
    private const SAMPLE = [
        'site' => [
            'title' => 'My Site',
            'default_lang' => 'en',
        ],
        'system' => [
            'timezone' => 'UTC',
            'pages' => ['theme' => 'quark'],
        ],
        'plugins' => [
            'email' => [
                'mailer' => 'smtp',
                'smtp' => [
                    'server' => 'smtp.example.com',
                    'user' => 'admin@example.com',
                    'password' => 'super-secret',
                ],
            ],
            'recaptcha' => ['secret_key' => 'rc-secret'],
        ],
        'streams' => [
            'schemes' => ['user' => '...'],
        ],
        'theme' => ['custom_color' => '#fff'],
    ];

    private function build(array $denied = ['plugins', 'streams']): SandboxConfig
    {
        return new SandboxConfig(new Config(self::SAMPLE), $denied);
    }

    // =========================================================================
    // get() — denied prefix returns default; legitimate paths pass through
    // =========================================================================

    public function testGet_DeniedTopLevelReturnsDefault(): void
    {
        self::assertNull($this->build()->get('plugins'));
        self::assertSame('fallback', $this->build()->get('plugins', 'fallback'));
    }

    public function testGet_DeniedDescendantReturnsDefault(): void
    {
        self::assertNull($this->build()->get('plugins.email.smtp.password'));
        self::assertSame('x', $this->build()->get('plugins.email.smtp.password', 'x'));
    }

    public function testGet_LegitimatePathPassesThrough(): void
    {
        self::assertSame('My Site', $this->build()->get('site.title'));
        self::assertSame('UTC', $this->build()->get('system.timezone'));
        self::assertSame('#fff', $this->build()->get('theme.custom_color'));
    }

    public function testGet_MissingKeyReturnsDefault(): void
    {
        self::assertNull($this->build()->get('site.does_not_exist'));
        self::assertSame('z', $this->build()->get('site.does_not_exist', 'z'));
    }

    public function testGet_RootReturnsTreeWithDeniedSubtreesRemoved(): void
    {
        $tree = $this->build()->get('');
        self::assertIsArray($tree);
        self::assertArrayHasKey('site', $tree);
        self::assertArrayHasKey('system', $tree);
        self::assertArrayHasKey('theme', $tree);
        self::assertArrayNotHasKey('plugins', $tree, 'plugins.* must be filtered from full tree');
        self::assertArrayNotHasKey('streams', $tree, 'streams.* must be filtered from full tree');
    }

    public function testGet_NonRootSubtreeReturnsFilteredArray(): void
    {
        $sandbox = $this->build(['system.pages']);
        $system = $sandbox->get('system');
        self::assertIsArray($system);
        self::assertSame('UTC', $system['timezone']);
        self::assertArrayNotHasKey('pages', $system);
    }

    public function testGet_CustomSeparatorIsHonored(): void
    {
        // `Config::get` accepts a separator override; the facade must apply
        // its denied-path check after normalizing to the same shape.
        self::assertNull($this->build()->get('plugins/email/smtp/password', null, '/'));
        self::assertSame('My Site', $this->build()->get('site/title', null, '/'));
    }

    // =========================================================================
    // value() — alias for get() with the same filtering
    // =========================================================================

    public function testValue_BehavesLikeGet(): void
    {
        $cfg = $this->build();
        self::assertSame($cfg->get('site.title'), $cfg->value('site.title'));
        self::assertSame($cfg->get('plugins', 'def'), $cfg->value('plugins', 'def'));
    }

    // =========================================================================
    // toArray() — recursive subtree removal
    // =========================================================================

    public function testToArray_StripsDeniedTopLevelKeys(): void
    {
        $arr = $this->build()->toArray();
        self::assertArrayNotHasKey('plugins', $arr);
        self::assertArrayNotHasKey('streams', $arr);
        self::assertArrayHasKey('site', $arr);
    }

    public function testToArray_StripsNestedDeniedPaths(): void
    {
        $arr = $this->build(['plugins.email.smtp'])->toArray();
        self::assertArrayHasKey('plugins', $arr);
        self::assertArrayHasKey('email', $arr['plugins']);
        self::assertSame('smtp', $arr['plugins']['email']['mailer']);
        self::assertArrayNotHasKey('smtp', $arr['plugins']['email'], 'nested denied path must be removed');
        self::assertArrayHasKey('recaptcha', $arr['plugins'], 'sibling subtree untouched');
    }

    public function testToArray_NoDeniedPathsReturnsFullTree(): void
    {
        $arr = $this->build([])->toArray();
        self::assertArrayHasKey('plugins', $arr);
        self::assertArrayHasKey('streams', $arr);
        self::assertArrayHasKey('site', $arr);
    }

    public function testToArray_DoesNotEmitJsonContainingDeniedSecret(): void
    {
        // Direct exercise of the GHSA-j274 PoC payload — config.toArray()|json_encode
        // must not round-trip the smtp password.
        $json = json_encode($this->build()->toArray());
        self::assertIsString($json);
        self::assertStringNotContainsString('super-secret', $json);
        self::assertStringNotContainsString('rc-secret', $json);
        self::assertStringContainsString('My Site', $json);
    }

    // =========================================================================
    // ArrayAccess: `config['plugins']` and `'plugins' in config`
    // =========================================================================

    public function testOffsetGet_DeniedReturnsNull(): void
    {
        $cfg = $this->build();
        self::assertNull($cfg['plugins']);
    }

    public function testOffsetGet_LegitimateReturnsArray(): void
    {
        $cfg = $this->build();
        self::assertIsArray($cfg['site']);
        self::assertSame('My Site', $cfg['site']['title']);
    }

    public function testOffsetGet_PartiallyDeniedSubtreeIsFiltered(): void
    {
        $cfg = $this->build(['plugins.email']);
        $plugins = $cfg['plugins'];
        self::assertIsArray($plugins);
        self::assertArrayNotHasKey('email', $plugins);
        self::assertArrayHasKey('recaptcha', $plugins);
    }

    public function testOffsetExists_DeniedReturnsFalse(): void
    {
        $cfg = $this->build();
        self::assertFalse(isset($cfg['plugins']));
        self::assertTrue(isset($cfg['site']));
    }

    public function testOffsetSet_IsNoOp(): void
    {
        $real = new Config(self::SAMPLE);
        $cfg = new SandboxConfig($real, ['plugins']);
        $cfg['site'] = ['title' => 'Hijacked'];
        // Underlying Config is unchanged
        self::assertSame('My Site', $real->get('site.title'));
    }

    public function testOffsetUnset_IsNoOp(): void
    {
        $real = new Config(self::SAMPLE);
        $cfg = new SandboxConfig($real, ['plugins']);
        unset($cfg['site']);
        self::assertSame('My Site', $real->get('site.title'));
    }

    // =========================================================================
    // Denied-path normalization
    // =========================================================================

    public function testConstructor_TrimsAndDedupesDeniedPaths(): void
    {
        // Whitespace, empty, and duplicates must not break filtering or
        // accidentally deny everything.
        $cfg = new SandboxConfig(new Config(self::SAMPLE), [
            'plugins',
            ' plugins ',     // duplicate after trim
            '.streams',      // leading dot trimmed
            '',              // empty ignored
            'plugins',       // exact duplicate
        ]);
        self::assertNull($cfg->get('plugins'));
        self::assertNull($cfg->get('streams'));
        self::assertSame('My Site', $cfg->get('site.title'));
    }

    public function testConstructor_EmptyDeniedListAllowsEverything(): void
    {
        $cfg = new SandboxConfig(new Config(self::SAMPLE), []);
        self::assertSame('super-secret', $cfg->get('plugins.email.smtp.password'));
        $arr = $cfg->toArray();
        self::assertArrayHasKey('plugins', $arr);
    }

    /**
     * Defensive: a denied path is a *prefix*, not a substring. `plug` should
     * NOT redact `plugins`, and `site.tit` should NOT redact `site.title`.
     */
    public function testIsDenied_OnlyMatchesFullPathSegments(): void
    {
        $cfg = new SandboxConfig(new Config(self::SAMPLE), ['plug', 'site.tit']);
        self::assertSame('smtp', $cfg->get('plugins.email.mailer'));
        self::assertSame('My Site', $cfg->get('site.title'));
    }
}
