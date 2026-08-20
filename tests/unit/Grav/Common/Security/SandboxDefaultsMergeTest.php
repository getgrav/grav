<?php

use Grav\Common\Grav;
use Grav\Common\Security;
use Grav\Common\Twig\Sandbox\SandboxDefaults;
use Twig\Sandbox\SecurityNotAllowedFilterError;
use Twig\Sandbox\SecurityNotAllowedFunctionError;
use Twig\Sandbox\SecurityNotAllowedMethodError;
use Twig\Sandbox\SecurityNotAllowedTagError;

/**
 * Security-config audit 2026-08-12: the Twig-sandbox default allowlists moved
 * from user-editable security.yaml into code (SandboxDefaults). These tests
 * lock in the three properties that make that move safe:
 *
 *   1. The shipped defaults are in force with an EMPTY user config (they no
 *      longer live in the YAML file, so a missing key must still allow them).
 *   2. `allowed_*` is purely ADDITIVE — a user entry widens, and cannot drop a
 *      default by omission (the old replace-merge freeze hazard is gone).
 *   3. `denied_*` is the explicit tightening path and wins over defaults, user
 *      additions, and (implicitly) plugin event additions.
 */
class SandboxDefaultsMergeTest extends \PHPUnit\Framework\TestCase
{
    /** @var array<string,mixed> */
    private array $saved = [];

    private array $keys = [
        'security.twig_sandbox.allowed_tags',
        'security.twig_sandbox.allowed_filters',
        'security.twig_sandbox.allowed_functions',
        'security.twig_sandbox.allowed_methods',
        'security.twig_sandbox.allowed_properties',
        'security.twig_sandbox.denied_tags',
        'security.twig_sandbox.denied_filters',
        'security.twig_sandbox.denied_functions',
        'security.twig_sandbox.denied_methods',
        'security.twig_sandbox.denied_properties',
        'security.twig_sandbox.config_denied_paths',
        'security.twig_content.config_access',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $config = Grav::instance()['config'];
        foreach ($this->keys as $k) {
            $this->saved[$k] = $config->get($k);
        }
        $this->resetPolicyCache();
    }

    protected function tearDown(): void
    {
        $config = Grav::instance()['config'];
        foreach ($this->saved as $k => $v) {
            $config->set($k, $v);
        }
        $this->resetPolicyCache();
        parent::tearDown();
    }

    private function resetPolicyCache(): void
    {
        $reflection = new ReflectionClass(Security::class);
        foreach (['twigSandboxPolicy', 'twigSandboxPolicyKey'] as $prop) {
            if ($reflection->hasProperty($prop)) {
                $p = $reflection->getProperty($prop);
                $p->setAccessible(true);
                $p->setValue(null, null);
            }
        }
    }

    private function set(string $key, $value): void
    {
        Grav::instance()['config']->set($key, $value);
        $this->resetPolicyCache();
    }

    // 1. Defaults in force with empty user config ----------------------------

    public function testDefaults_InForceWithEmptyUserConfig(): void
    {
        foreach (['allowed_tags', 'allowed_filters', 'allowed_functions'] as $flat) {
            // The shipped security.yaml carries no inline lists anymore.
            self::assertSame([], (array) Grav::instance()['config']->get("security.twig_sandbox.$flat"));
        }

        $policy = Security::buildTwigSandboxPolicy();
        // A representative default from each list must still be allowed.
        $this->assertAllowed(fn() => $policy->checkSecurity(['for'], ['escape'], ['url']));
    }

    public function testDefaults_EffectiveListMatchesSandboxDefaults(): void
    {
        $effective = Security::effectiveSandboxList('filters');
        // Same set as the code defaults (order/dupes aside) when nothing added.
        self::assertEqualsCanonicalizing(SandboxDefaults::filters(), $effective);
        self::assertContains('escape', $effective);
        self::assertNotContains('raw', $effective, 'raw must never be a default filter');
    }

    // 2. allowed_* is additive ----------------------------------------------

    public function testAllowed_AddsWithoutDroppingDefaults(): void
    {
        $this->set('security.twig_sandbox.allowed_functions', ['unite_gallery']);
        $policy = Security::buildTwigSandboxPolicy();

        // The user addition is now allowed...
        $this->assertAllowed(fn() => $policy->checkSecurity([], [], ['unite_gallery']));
        // ...and a default the user never mentioned is STILL allowed (this is the
        // freeze hazard the collapse removes: with replace-merge, setting the key
        // would have dropped every default).
        $this->assertAllowed(fn() => $policy->checkSecurity([], [], ['url']));
        $this->assertAllowed(fn() => $policy->checkSecurity([], ['escape'], []));
    }

    public function testAllowed_MethodRowAddsToClassWithoutDroppingDefaults(): void
    {
        $this->set('security.twig_sandbox.allowed_methods', [
            ['class' => 'Grav\Common\Page\Pages', 'methods' => 'customthing'],
        ]);
        $policy = Security::buildTwigSandboxPolicy();
        $pages = $this->stub('Grav\Common\Page\Pages');

        $this->assertAllowed(fn() => $policy->checkMethodAllowed($pages, 'customthing'));
        // Default Pages methods survive the addition (freeze hazard removed).
        $this->assertAllowed(fn() => $policy->checkMethodAllowed($pages, 'all'));
    }

    // 3. denied_* tightens and wins -----------------------------------------

    public function testDenied_RemovesADefaultFilter(): void
    {
        $this->set('security.twig_sandbox.denied_filters', ['escape']);
        $policy = Security::buildTwigSandboxPolicy();

        $this->expectException(SecurityNotAllowedFilterError::class);
        $policy->checkSecurity([], ['escape'], []);
    }

    public function testDenied_RemovesADefaultTag(): void
    {
        $this->set('security.twig_sandbox.denied_tags', ['include']);
        $policy = Security::buildTwigSandboxPolicy();

        $this->expectException(SecurityNotAllowedTagError::class);
        $policy->checkSecurity(['include'], [], []);
    }

    public function testDenied_WinsOverAllowedAddition(): void
    {
        // Same member added and denied → denied wins (explicit tightening).
        $this->set('security.twig_sandbox.allowed_functions', ['range']);
        $this->set('security.twig_sandbox.denied_functions', ['range']);
        $policy = Security::buildTwigSandboxPolicy();

        $this->expectException(SecurityNotAllowedFunctionError::class);
        $policy->checkSecurity([], [], ['range']);
    }

    public function testDenied_WholeClassWithWildcard(): void
    {
        // Taxonomy has a single allowlist entry and no allowlisted ancestor, so
        // a wildcard denial removes its only reachable method cleanly.
        $this->set('security.twig_sandbox.denied_methods', [
            ['class' => 'Grav\Common\Taxonomy', 'methods' => '*'],
        ]);
        $policy = Security::buildTwigSandboxPolicy();
        $taxonomy = $this->stub('Grav\Common\Taxonomy');

        $this->expectException(SecurityNotAllowedMethodError::class);
        $policy->checkMethodAllowed($taxonomy, 'taxonomy');
    }

    public function testDenied_SingleMethodLeavesSiblings(): void
    {
        $this->set('security.twig_sandbox.denied_methods', [
            ['class' => 'Grav\Common\Page\Pages', 'methods' => 'all'],
        ]);
        $policy = Security::buildTwigSandboxPolicy();
        $pages = $this->stub('Grav\Common\Page\Pages');

        // Denied one default method...
        $threw = false;
        try {
            $policy->checkMethodAllowed($pages, 'all');
        } catch (SecurityNotAllowedMethodError) {
            $threw = true;
        }
        self::assertTrue($threw, 'all should be denied');
        // ...sibling defaults survive.
        $this->assertAllowed(fn() => $policy->checkMethodAllowed($pages, 'home'));
    }

    public function testDenied_ClassNameMatchedCaseInsensitively(): void
    {
        // PHP class names are case-insensitive and enforcement uses instanceof,
        // so a denial written in any casing must still take effect. (GHSA-3j7p)
        $this->set('security.twig_sandbox.denied_methods', [
            ['class' => 'grav\common\taxonomy', 'methods' => '*'],
        ]);
        $policy = Security::buildTwigSandboxPolicy();
        $taxonomy = $this->stub('Grav\Common\Taxonomy');

        $this->expectException(SecurityNotAllowedMethodError::class);
        $policy->checkMethodAllowed($taxonomy, 'taxonomy');
    }

    public function testDenied_NotReGrantedByAllowedSupertype(): void
    {
        // Grav\Common\Page\Media and MediaCollectionInterface are both allowlisted
        // with the same methods, and Media implements the interface. Denying the
        // concrete class must actually block it, not leave the interface entry
        // silently re-granting every method. (GHSA-3j7p)
        $this->set('security.twig_sandbox.denied_methods', [
            ['class' => 'Grav\Common\Page\Media', 'methods' => '*'],
        ]);
        $policy = Security::buildTwigSandboxPolicy();
        $media = $this->stub('Grav\Common\Page\Media');

        $this->expectException(SecurityNotAllowedMethodError::class);
        $policy->checkMethodAllowed($media, 'images');
    }

    // config_denied_paths additive ------------------------------------------

    public function testConfigDeniedPaths_DefaultsPlusUserAdditions(): void
    {
        $effective = Security::effectiveConfigDeniedPaths();
        foreach (SandboxDefaults::configDeniedPaths() as $d) {
            self::assertContains($d, $effective, "default denied path $d must survive");
        }

        $this->set('security.twig_sandbox.config_denied_paths', ['site.integrations']);
        $effective = Security::effectiveConfigDeniedPaths();
        self::assertContains('site.integrations', $effective, 'user addition present');
        self::assertContains('plugins', $effective, 'default not dropped by user setting the key');
    }

    // migration planner (Security::planSandboxDefaultsMigration) --------------

    public function testMigration_UntouchedSiteYieldsNoPlan(): void
    {
        self::assertSame([], Security::planSandboxDefaultsMigration([]));
        self::assertSame([], Security::planSandboxDefaultsMigration(['enabled' => true]));
    }

    public function testMigration_SupersetYieldsNoPlan(): void
    {
        // A site that copied the full defaults and only ADDED entries never lost
        // anything under replace-merge, so nothing needs denying.
        $plan = Security::planSandboxDefaultsMigration([
            'allowed_filters' => array_merge(SandboxDefaults::filters(), ['myextra']),
        ]);
        self::assertArrayNotHasKey('denied_filters', $plan);
    }

    public function testMigration_TightenedFlatListDeniesTheOmittedDefaults(): void
    {
        // Old replace-merge: this list was the WHOLE filter policy → every other
        // default filter was blocked. Migration must deny them all back.
        $plan = Security::planSandboxDefaultsMigration([
            'allowed_filters' => ['upper', 'lower'],
        ]);
        self::assertContains('escape', $plan['denied_filters']);
        self::assertContains('markdown', $plan['denied_filters']);
        self::assertNotContains('upper', $plan['denied_filters']);
    }

    public function testMigration_OmittedMethodClassIsDeniedButKeptClassIsNot(): void
    {
        $plan = Security::planSandboxDefaultsMigration([
            'allowed_methods' => [
                ['class' => 'Grav\Common\Taxonomy', 'methods' => 'taxonomy'],
            ],
        ]);
        $classes = array_column($plan['denied_methods'], 'class');
        self::assertContains('Grav\Common\Page\Pages', $classes, 'omitted class denied');
        self::assertNotContains('Grav\Common\Taxonomy', $classes, 'kept class not denied');
    }

    /**
     * End-to-end: a tightened site's OLD effective policy (replace-merge of its
     * own list) must equal its NEW effective policy (defaults ∪ its list − the
     * migration's denials). This is the zero-behaviour-change guarantee.
     */
    public function testMigration_ReproducesOldEffectivePolicyForFlatList(): void
    {
        $userList = ['escape', 'upper', 'lower', 'trim'];

        $plan = Security::planSandboxDefaultsMigration(['allowed_filters' => $userList]);

        $this->set('security.twig_sandbox.allowed_filters', $userList);
        $this->set('security.twig_sandbox.denied_filters', $plan['denied_filters'] ?? []);

        $newEffective = Security::effectiveSandboxList('filters');
        // Old effective under replace-merge was exactly the user's list.
        self::assertEqualsCanonicalizing($userList, $newEffective);
    }

    // describeEffectiveSandbox (read-only admin view source) ------------------

    public function testDescribe_ShowsDefaultsAddedDeniedEffective(): void
    {
        $this->set('security.twig_sandbox.allowed_functions', ['unite_gallery']);
        $this->set('security.twig_sandbox.denied_functions', ['gist']);

        $d = Security::describeEffectiveSandbox(Grav::instance()['config']);
        $fn = $d['lists']['functions'];

        self::assertContains('unite_gallery', $fn['added']);
        self::assertContains('gist', $fn['denied']);
        self::assertContains('unite_gallery', $fn['effective'], 'addition is effective');
        self::assertContains('url', $fn['effective'], 'default survives');
        self::assertNotContains('gist', $fn['effective'], 'denied default removed');
        // Media actions are expanded in the effective method map.
        self::assertGreaterThan(8, count($d['lists']['methods']['effective']['Grav\Common\Page\Medium\Medium'] ?? []));
    }

    // helpers ----------------------------------------------------------------

    private function assertAllowed(callable $fn): void
    {
        try {
            $fn();
            self::assertTrue(true);
        } catch (\Twig\Sandbox\SecurityError $e) {
            self::fail('Expected allowed, got: ' . $e->getMessage());
        }
    }

    private function stub(string $class): object
    {
        return $this->getMockBuilder($class)->disableOriginalConstructor()->getMock();
    }
}
