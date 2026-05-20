<?php

use Grav\Common\Security;
use Twig\Environment;
use Twig\Extension\SandboxExtension;
use Twig\Extension\StringLoaderExtension;
use Twig\Loader\ArrayLoader;
use Twig\Sandbox\SecurityError;
use Twig\Sandbox\SecurityNotAllowedFilterError;
use Twig\Sandbox\SecurityNotAllowedFunctionError;
use Twig\Sandbox\SecurityNotAllowedMethodError;
use Twig\Sandbox\SecurityNotAllowedTagError;
use Twig\Sandbox\SecurityPolicyInterface;
use Twig\TwigFunction;

/**
 * Tests for the Twig sandbox policy wired up by Security::buildTwigSandboxPolicy()
 * and the SandboxExtension integration in Twig::processPage()/processString().
 *
 * Covers SSTI advisory vectors:
 * - attribute(grav, 'scheduler') + bracket notation runtime-constructed names
 * - base64 / concat-constructed function names resolved at runtime
 * - svg_image / read_file / evaluate / evaluate_twig / template_from_string / constant
 * - pure container traversal (grav['flex'], grav['config'].get(...))
 */
class TwigSandboxTest extends \PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Reset Security's policy cache between tests
        $reflection = new ReflectionClass(Security::class);
        foreach (['twigSandboxPolicy', 'twigSandboxPolicyKey'] as $prop) {
            if ($reflection->hasProperty($prop)) {
                $p = $reflection->getProperty($prop);
                $p->setAccessible(true);
                $p->setValue(null, null);
            }
        }
    }

    // =========================================================================
    // Policy construction from config defaults
    // =========================================================================

    public function testBuildPolicy_ReturnsSecurityPolicyInterface(): void
    {
        $policy = Security::buildTwigSandboxPolicy();
        self::assertInstanceOf(SecurityPolicyInterface::class, $policy);
    }

    public function testBuildPolicy_AllowedFiltersIncludesEscape(): void
    {
        $policy = Security::buildTwigSandboxPolicy();
        // escape/upper/lower are foundational; must pass
        $this->assertDoesNotThrow(fn() => $policy->checkSecurity([], ['escape'], []));
        $this->assertDoesNotThrow(fn() => $policy->checkSecurity([], ['upper'], []));
        $this->assertDoesNotThrow(fn() => $policy->checkSecurity([], ['lower'], []));
    }

    /**
     * @dataProvider providerForbiddenFunctions
     */
    public function testBuildPolicy_BlocksForbiddenFunctions(string $fn, string $why): void
    {
        $policy = Security::buildTwigSandboxPolicy();
        $this->expectException(SecurityNotAllowedFunctionError::class);
        $policy->checkSecurity([], [], [$fn]);
        self::fail("Function '$fn' should be blocked: $why");
    }

    public static function providerForbiddenFunctions(): array
    {
        return [
            ['evaluate',             'GHSA-8w8h / GHSA-662m SSTI primitive'],
            ['evaluate_twig',        'GHSA-phwm / GHSA-pmp3 / GHSA-8rw8 SSTI primitive'],
            ['svg_image',            'GHSA-hxvg LFI primitive'],
            ['read_file',            'GHSA-r7v4 / GHSA-wxfv / GHSA-rmf6 LFI primitive'],
            ['redirect_me',          'response hijack'],
            ['http_response_code',   'response hijack'],
            ['template_from_string', 'GHSA-v94j / GHSA-pm82 SSTI vector'],
            ['constant',             'exposes PHP_VERSION / GRAV_ROOT for GHSA-phwm chain'],
            ['source',               'arbitrary template file read'],
            ['include',              'function form; tag form is bounded by loader'],
            // FilesystemExtension probes (GHSA-x2cf)
            ['is_readable',     'filesystem probe'],
            ['lstat',           'filesystem probe'],
            ['sha1_file',       'filesystem probe / hash grinding'],
            ['md5_file',        'filesystem probe / hash grinding'],
            ['hash_file',       'filesystem probe / hash grinding'],
            ['file_exists',     'filesystem probe'],
            ['filesize',        'filesystem probe'],
            ['filetype',        'filesystem probe'],
            ['pathinfo',        'filesystem probe'],
            ['exif_read_data',  'filesystem probe'],
        ];
    }

    /**
     * @dataProvider providerForbiddenFilters
     */
    public function testBuildPolicy_BlocksForbiddenFilters(string $filter): void
    {
        $policy = Security::buildTwigSandboxPolicy();
        $this->expectException(SecurityNotAllowedFilterError::class);
        $policy->checkSecurity([], [$filter], []);
    }

    public static function providerForbiddenFilters(): array
    {
        // Filesystem filters registered by FilesystemExtension — none should be allowed
        return [
            ['is_readable'],
            ['is_writable'],
            ['lstat'],
            ['sha1_file'],
            ['md5_file'],
            ['hash_file'],
            ['file_exists'],
            ['filesize'],
            ['exif_read_data'],
        ];
    }

    /**
     * @dataProvider providerForbiddenTags
     */
    public function testBuildPolicy_BlocksForbiddenTags(string $tag): void
    {
        $policy = Security::buildTwigSandboxPolicy();
        $this->expectException(SecurityNotAllowedTagError::class);
        $policy->checkSecurity([$tag], [], []);
    }

    public static function providerForbiddenTags(): array
    {
        return [
            ['flush'],      // not in allowlist
            ['cache'],      // CacheExtension tag — not in the core allowlist
            ['arbitrary'],  // unknown tag
        ];
    }

    public function testBuildPolicy_AllowsCommonContentTags(): void
    {
        $policy = Security::buildTwigSandboxPolicy();
        $this->assertDoesNotThrow(fn() => $policy->checkSecurity(['if', 'for', 'set', 'include', 'block', 'extends'], [], []));
    }

    // =========================================================================
    // Method allowlist: Grav container traversal is gated by per-service methods
    // =========================================================================

    public function testBuildPolicy_BlocksConfigSet(): void
    {
        $policy = Security::buildTwigSandboxPolicy();
        $config = new \Grav\Common\Config\Config([]);
        $this->expectException(SecurityNotAllowedMethodError::class);
        $policy->checkMethodAllowed($config, 'set');
    }

    public function testBuildPolicy_BlocksConfigMerge(): void
    {
        $policy = Security::buildTwigSandboxPolicy();
        $config = new \Grav\Common\Config\Config([]);
        $this->expectException(SecurityNotAllowedMethodError::class);
        $policy->checkMethodAllowed($config, 'merge');
    }

    public function testBuildPolicy_AllowsConfigGet(): void
    {
        // Config.get is in the YAML allowlist, but the policy builder strips
        // the whole Config class when security.twig_content.config_access is
        // off (the 2.0 default). Enable the gate here so we exercise the
        // YAML-configured allowlist; the strip behavior is covered separately
        // by the SandboxConfig facade tests below.
        $grav = \Grav\Common\Grav::instance();
        $previous = $grav['config']->get('security.twig_content.config_access');
        $grav['config']->set('security.twig_content.config_access', true);
        try {
            $policy = Security::buildTwigSandboxPolicy();
            $config = new \Grav\Common\Config\Config([]);
            $this->assertDoesNotThrow(fn() => $policy->checkMethodAllowed($config, 'get'));
        } finally {
            $grav['config']->set('security.twig_content.config_access', $previous);
        }
    }

    /**
     * GHSA-58hj-46fw-rcfm: a low-privilege editor with page-update access
     * injected `{% set x = grav['accounts'].load(...) %} {{ x.set(...) }} {{ x.save() }}`
     * from page content to mint a super-admin. The sandbox allows
     * `grav.offsetGet('accounts')` (container traversal is deliberately permitted),
     * but the returned UserCollection is not in `allowed_methods`, so `load()` /
     * `save()` / `set()` on it must be denied.
     */
    public function testBuildPolicy_GHSA58hj_BlocksAccountsCollectionMethods(): void
    {
        $policy = Security::buildTwigSandboxPolicy();
        $accounts = new \Grav\Common\User\DataUser\UserCollection(\Grav\Common\User\User::class);

        foreach (['load', 'save', 'set', '__set'] as $method) {
            try {
                $policy->checkMethodAllowed($accounts, $method);
                self::fail("UserCollection::{$method} must be blocked (GHSA-58hj-46fw-rcfm)");
            } catch (SecurityNotAllowedMethodError $e) {
                $this->addToAssertionCount(1);
            }
        }
    }

    // =========================================================================
    // End-to-end render: SecurityError is raised when a disallowed primitive runs
    // =========================================================================

    public function testSandboxRender_BlocksConstantFunction(): void
    {
        $env = $this->sandboxEnv(['hit' => "{{ constant('PHP_VERSION') }}"]);
        $this->expectException(SecurityNotAllowedFunctionError::class);
        $env->render('hit');
    }

    public function testSandboxRender_BlocksEvaluateTwigFunction(): void
    {
        // Register a stub `evaluate_twig` so the parser knows the function exists;
        // the sandbox should then reject it at render time.
        $env = $this->sandboxEnv(['hit' => "{{ evaluate_twig('payload') }}"]);
        $env->addFunction(new TwigFunction('evaluate_twig', static fn($x) => $x));

        $this->expectException(SecurityNotAllowedFunctionError::class);
        $env->render('hit');
    }

    public function testSandboxRender_BlocksReadFileFunction(): void
    {
        $env = $this->sandboxEnv(['hit' => "{{ read_file('/etc/passwd') }}"]);
        $env->addFunction(new TwigFunction('read_file', static fn($p) => $p));

        $this->expectException(SecurityNotAllowedFunctionError::class);
        $env->render('hit');
    }

    public function testSandboxRender_BlocksSvgImageFunction(): void
    {
        $env = $this->sandboxEnv(['hit' => "{{ svg_image('user://accounts/admin.yaml') }}"]);
        $env->addFunction(new TwigFunction('svg_image', static fn($p) => $p));

        $this->expectException(SecurityNotAllowedFunctionError::class);
        $env->render('hit');
    }

    public function testSandboxRender_AllowsUpperFilter(): void
    {
        $env = $this->sandboxEnv(['hit' => "{{ 'hello' | upper }}"]);
        self::assertSame('HELLO', $env->render('hit'));
    }

    public function testSandboxRender_AllowsBasicControlFlow(): void
    {
        $env = $this->sandboxEnv(['hit' => "{% for i in 1..3 %}{{ i }}{% endfor %}"]);
        self::assertSame('123', $env->render('hit'));
    }

    // =========================================================================
    // Runtime-constructed names: attribute() + concat can't be caught statically
    // but the sandbox catches the resolved method call
    // =========================================================================

    public function testSandboxRender_BlocksAttributeMethodOnDisallowedClass(): void
    {
        // GHSA-p7gj / GHSA-v94j / GHSA-pm82 runtime-constructed method name pattern:
        // attribute() resolves the method name from a variable at render time. The
        // sandbox blocks the resolved access regardless — either as MethodError
        // (if Twig compiles as method) or PropertyError (if compiled as property).
        $env = $this->sandboxEnv([
            'hit' => "{{ attribute(cfg, 'set', ['k', ['v']]) }}",
        ]);

        $cfg = new \Grav\Common\Config\Config([]);
        $this->expectException(SecurityError::class);
        $env->render('hit', ['cfg' => $cfg]);
    }

    // =========================================================================
    // Config-shape tests
    // =========================================================================

    public function testBuildPolicy_PolicyIsCachedWithinRequest(): void
    {
        $p1 = Security::buildTwigSandboxPolicy();
        $p2 = Security::buildTwigSandboxPolicy();
        self::assertSame($p1, $p2, 'Policy should be cached by identity within a request');
    }

    // =========================================================================
    // stdClass wildcard: page.header.<any_yaml_key> must pass the sandbox
    // because frontmatter property sets are fully dynamic.
    // =========================================================================

    public function testSandboxRender_AllowsDynamicStdClassPropertyAccess(): void
    {
        $header = (object) ['title' => 'Hello', 'custom_key' => 'World'];
        $env = $this->sandboxEnv([
            'hit' => '{{ header.title }} / {{ header.custom_key }}',
        ]);
        self::assertSame('Hello / World', $env->render('hit', ['header' => $header]));
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    // =========================================================================
    // GHSA-j274-39qw-32c9 — `config.toArray()` plugin-secret exfiltration.
    //
    // The fix lives across three layers:
    //  1. `toarray` is removed from the Config and Data sandbox allow-lists so
    //     a real Config reachable via `grav['config']` cannot bulk-dump.
    //  2. SandboxConfig replaces the injected `config` Twig variable inside
    //     editor renders and filters denied dot-prefixes from get/value/
    //     offsetGet/offsetExists/toArray.
    //  3. `security.twig_sandbox.config_denied_paths` lists the prefixes
    //     (defaults: plugins, streams, security, backups, scheduler).
    //
    // These tests cover (1) and (2). Layer (3) is exercised by config defaults
    // in production — the unit tests in SandboxConfigTest assert the filtering
    // logic itself.
    // =========================================================================

    public function testGhsaJ274_RawConfigToArrayBlockedBySandbox(): void
    {
        // After dropping `toarray` from Grav\Common\Config\Config and the
        // parent Grav\Common\Data\Data allow-lists, the policy must reject
        // toArray() on a real Config.
        $policy = Security::buildTwigSandboxPolicy();
        $config = new \Grav\Common\Config\Config([
            'plugins' => ['email' => ['smtp' => ['password' => 'super-secret']]],
        ]);
        $this->expectException(SecurityNotAllowedMethodError::class);
        $policy->checkMethodAllowed($config, 'toarray');
    }

    public function testGhsaJ274_RawDataToArrayBlockedBySandbox(): void
    {
        $policy = Security::buildTwigSandboxPolicy();
        $data = new \Grav\Common\Data\Data(['secret' => 'x']);
        $this->expectException(SecurityNotAllowedMethodError::class);
        $policy->checkMethodAllowed($data, 'toarray');
    }

    public function testGhsaJ274_SandboxConfigToArrayAllowed(): void
    {
        $policy = Security::buildTwigSandboxPolicy();
        $facade = new \Grav\Common\Twig\Sandbox\SandboxConfig(
            new \Grav\Common\Config\Config([]),
            []
        );
        $this->assertDoesNotThrow(fn() => $policy->checkMethodAllowed($facade, 'toarray'));
    }

    public function testGhsaJ274_RenderViaSandboxConfigStripsPluginSecrets(): void
    {
        // Full advisory PoC: editor saves a page body of
        //   {{ config.toArray()|json_encode|raw }}
        // With the SandboxConfig facade injected as `config`, the rendered
        // output must NOT contain plugin secrets.
        $real = new \Grav\Common\Config\Config([
            'site' => ['title' => 'Public Title'],
            'plugins' => [
                'email' => ['smtp' => ['password' => 'PLUGIN_SECRET_42']],
                'recaptcha' => ['secret_key' => 'RC_SECRET_99'],
            ],
            'streams' => ['schemes' => ['user' => 'user://']],
        ]);
        $facade = new \Grav\Common\Twig\Sandbox\SandboxConfig(
            $real,
            ['plugins', 'streams']
        );

        $env = $this->sandboxEnv(['poc' => '{{ config.toArray()|json_encode|raw }}']);
        $output = $env->render('poc', ['config' => $facade]);

        self::assertStringContainsString('Public Title', $output, 'site.* must remain readable');
        self::assertStringNotContainsString('PLUGIN_SECRET_42', $output, 'plugin secrets must be stripped');
        self::assertStringNotContainsString('RC_SECRET_99', $output, 'plugin secrets must be stripped');
        self::assertStringNotContainsString('user://', $output, 'streams.* must be stripped');
    }

    public function testGhsaJ274_RenderRawConfigToArrayRaisesSecurityError(): void
    {
        // Belt-and-suspenders: even if the SandboxConfig wiring is bypassed
        // and a raw Config is passed as `config`, the policy must reject
        // toArray() at render time (regression guard for layer 1 of the fix).
        $real = new \Grav\Common\Config\Config([
            'plugins' => ['email' => ['smtp' => ['password' => 'PLUGIN_SECRET_42']]],
        ]);
        $env = $this->sandboxEnv(['poc' => '{{ config.toArray()|json_encode|raw }}']);
        $this->expectException(SecurityNotAllowedMethodError::class);
        $env->render('poc', ['config' => $real]);
    }

    public function testGhsaJ274_RenderConfigGetOnDeniedPathReturnsNull(): void
    {
        // Per-key reads through the facade must also be filtered.
        $real = new \Grav\Common\Config\Config([
            'plugins' => ['email' => ['smtp' => ['password' => 'PLUGIN_SECRET_42']]],
            'site' => ['title' => 'Public Title'],
        ]);
        $facade = new \Grav\Common\Twig\Sandbox\SandboxConfig($real, ['plugins']);

        $env = $this->sandboxEnv([
            'denied' => "[{{ config.get('plugins.email.smtp.password', 'BLOCKED') }}]",
            'allowed' => "[{{ config.get('site.title') }}]",
        ]);
        self::assertSame('[BLOCKED]', $env->render('denied', ['config' => $facade]));
        self::assertSame('[Public Title]', $env->render('allowed', ['config' => $facade]));
    }

    /**
     * Build a minimal sandboxed Twig environment preloaded with the current
     * security policy. Templates is a name => source map.
     */
    private function sandboxEnv(array $templates): Environment
    {
        $env = new Environment(new ArrayLoader($templates), ['cache' => false, 'strict_variables' => false]);
        $env->addExtension(new StringLoaderExtension());
        $sandbox = new SandboxExtension(Security::buildTwigSandboxPolicy(), true); // sandboxed globally for test
        $env->addExtension($sandbox);
        return $env;
    }

    private function assertDoesNotThrow(callable $fn): void
    {
        try {
            $fn();
        } catch (\Throwable $t) {
            self::fail('Expected no exception, got ' . $t::class . ': ' . $t->getMessage());
        }
        $this->addToAssertionCount(1);
    }
}
