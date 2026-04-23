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
 * Covers advisory vectors that static cleanDangerousTwig() can't catch:
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
        $policy = Security::buildTwigSandboxPolicy();
        $config = new \Grav\Common\Config\Config([]);
        $this->assertDoesNotThrow(fn() => $policy->checkMethodAllowed($config, 'get'));
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
