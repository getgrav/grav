<?php

use Codeception\Util\Fixtures;
use Grav\Common\Security;
use Grav\Common\Twig\Sandbox\GravSourcePolicy;
use Grav\Common\Twig\TwigEnvironment;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Extension\SandboxExtension;
use Twig\Extension\StringLoaderExtension;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;
use Twig\Sandbox\CompileTimeSourcePolicyInterface;
use Twig\Sandbox\SecurityError;
use Twig\Sandbox\SecurityNotAllowedFilterError;
use Twig\Sandbox\SecurityNotAllowedFunctionError;
use Twig\Sandbox\SecurityNotAllowedMethodError;
use Twig\Sandbox\SecurityNotAllowedPropertyError;
use Twig\Sandbox\SourcePolicyInterface;
use Twig\Source;

/**
 * Trusted disk templates compile without the Twig sandbox's runtime checks,
 * while editor-authored sources (`@Page:`, `@Var:`, `@EmailVar:`) and anything
 * rendered with the sandbox switched on keep them.
 *
 * Every environment here is wired the way Twig::init() wires Grav's: an
 * ArrayLoader for string templates chained in front of a FilesystemLoader, and
 * a SandboxExtension driven by GravSourcePolicy. The compile-time decision is a
 * getgrav/Twig fork feature (Twig\Sandbox\CompileTimeSourcePolicyInterface),
 * which GravSourcePolicy opts into. The "legacy" environment hands the extension
 * the same decisions through a plain SourcePolicyInterface instead, which is how
 * every template compiled before this change.
 */
class SourceSandboxCompilationTest extends TestCase
{
    /** @var string */
    private $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $reflection = new ReflectionClass(Security::class);
        foreach (['twigSandboxPolicy', 'twigSandboxPolicyKey'] as $prop) {
            if ($reflection->hasProperty($prop)) {
                $p = $reflection->getProperty($prop);
                $p->setAccessible(true);
                $p->setValue(null, null);
            }
        }

        // A unique folder per test keeps the loader cache key, and so the
        // compiled class name, unique too.
        $this->dir = sys_get_temp_dir() . '/grav-source-sandbox-' . bin2hex(random_bytes(6));
        mkdir($this->dir . '/partials', 0777, true);

        $this->writeTemplate('partials/probe.html.twig', '<b>{{ probe }}</b>');
        $this->writeTemplate('partials/child.html.twig', "{% extends 'partials/base.html.twig' %}{% block body %}[{{ probe }}]{% endblock %}");
        $this->writeTemplate('partials/base.html.twig', '<main>{% block body %}{% endblock %}</main>');
        $this->writeTemplate('partials/plain.html.twig', '<i>{{ name|upper }}</i>');
        $this->writeTemplate('partials/attr.html.twig', "{{ item.title }}|{{ item.getTitle() }}|{{ map['k'] }}|{{ list|map(v => v)|length }}");
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->dir);
        parent::tearDown();
    }

    // =========================================================================
    // (a) Editor-authored sources are still fully sandboxed
    // =========================================================================

    /**
     * @dataProvider sandboxedPrefixProvider
     */
    public function testSandboxedSource_BlocksDisallowedMethod(string $prefix): void
    {
        $env = $this->env([$prefix . 'm' => "{{ cfg.set('a', 'b') }}"]);

        $this->expectException(SecurityNotAllowedMethodError::class);
        $env->render($prefix . 'm', ['cfg' => new \Grav\Common\Config\Config([])]);
    }

    /**
     * @dataProvider sandboxedPrefixProvider
     */
    public function testSandboxedSource_BlocksDisallowedProperty(string $prefix): void
    {
        $env = $this->env([$prefix . 'p' => '{{ probe.secret }}']);

        $this->expectException(SecurityNotAllowedPropertyError::class);
        $env->render($prefix . 'p', ['probe' => new SourceSandboxProbe()]);
    }

    /**
     * @dataProvider sandboxedPrefixProvider
     */
    public function testSandboxedSource_BlocksDisallowedFilter(string $prefix): void
    {
        $env = $this->env([$prefix . 'f' => '{{ "<b>x</b>"|raw }}']);

        $this->expectException(SecurityNotAllowedFilterError::class);
        $env->render($prefix . 'f');
    }

    /**
     * @dataProvider sandboxedPrefixProvider
     */
    public function testSandboxedSource_BlocksDisallowedFunction(string $prefix): void
    {
        $env = $this->env([$prefix . 'fn' => "{{ constant('PHP_VERSION') }}"]);

        $this->expectException(SecurityNotAllowedFunctionError::class);
        $env->render($prefix . 'fn');
    }

    /**
     * @dataProvider sandboxedPrefixProvider
     */
    public function testSandboxedSource_BlocksToStringOfDisallowedObject(string $prefix): void
    {
        $env = $this->env([$prefix . 's' => '{{ probe }}']);

        $this->expectException(SecurityNotAllowedMethodError::class);
        $env->render($prefix . 's', ['probe' => new SourceSandboxProbe()]);
    }

    /**
     * @dataProvider sandboxedPrefixProvider
     */
    public function testSandboxedSource_CompilesWithChecks(string $prefix): void
    {
        $env = $this->env([$prefix . 'c' => '{{ probe }}']);
        $code = $env->compileSource($env->getLoader()->getSourceContext($prefix . 'c'));

        self::assertStringContainsString('ensureToStringAllowed', $code);
        self::assertStringContainsString('$this->sandbox->checkSecurity(', $code);
    }

    public static function sandboxedPrefixProvider(): array
    {
        return [
            '@Page:' => ['@Page:'],
            '@Var:' => ['@Var:'],
            '@EmailVar:' => ['@EmailVar:'],
        ];
    }

    /**
     * String templates that are not editor-authored still keep the checks, so a
     * `template_from_string()` result rendered under the sandbox is checked.
     */
    public function testStringTemplateWithoutPrefix_CompilesWithChecks(): void
    {
        $env = $this->env(['inline' => '{{ probe }}']);
        $code = $env->compileSource($env->getLoader()->getSourceContext('inline'));

        self::assertStringContainsString('ensureToStringAllowed', $code);
    }

    // =========================================================================
    // (b) Trusted disk templates compile without the checks, same output
    // =========================================================================

    public function testDiskTemplate_CompilesWithoutSandboxChecks(): void
    {
        $env = $this->env();
        $code = $env->compileSource($env->getLoader()->getSourceContext('partials/probe.html.twig'));

        self::assertStringNotContainsString('ensureToStringAllowed', $code);
        self::assertStringNotContainsString('checkSecurity', $code);
        // Only the guard that refuses to run under a sandboxed render remains.
        self::assertStringContainsString('public function ensureSecurityChecked(): void', $code);
        self::assertStringContainsString('if ($this->sandbox->isSandboxed()) {', $code);

        $legacy = $this->env([], false);
        $legacyCode = $legacy->compileSource($legacy->getLoader()->getSourceContext('partials/probe.html.twig'));
        self::assertStringContainsString('ensureToStringAllowed', $legacyCode);
    }

    /**
     * Attribute and method access, and the sandbox state handed to filters such
     * as `map`, compile the same way as without a SandboxExtension. (Arrow
     * function bodies are the exception, see the closure test below.)
     */
    public function testDiskTemplate_AttributeAccessCompilesWithoutSandboxChecks(): void
    {
        $env = $this->env();
        $code = $env->compileSource($env->getLoader()->getSourceContext('partials/attr.html.twig'));

        self::assertStringNotContainsString('->isSandboxed($this->source)', $code);
        self::assertStringNotContainsString('CoreExtension::ARRAY_LIKE_CLASSES', $code);
        self::assertStringContainsString('CoreExtension::map($this->env, false, ', $code);
        self::assertSame(0, preg_match('/CoreExtension::getAttribute\([^\n]*, true, \d+\)/', $code), 'getAttribute() calls must not be sandboxed');

        $legacy = $this->env([], false);
        $legacyCode = $legacy->compileSource($legacy->getLoader()->getSourceContext('partials/attr.html.twig'));
        self::assertStringContainsString('->isSandboxed($this->source)', $legacyCode);
        self::assertSame(1, preg_match('/CoreExtension::getAttribute\([^\n]*, true, \d+\)/', $legacyCode));
    }

    public function testDiskTemplate_RendersTheSameOutput(): void
    {
        $context = ['probe' => new SourceSandboxProbe(), 'name' => 'grav'];

        $context['item'] = new SourceSandboxItem();
        $context['map'] = ['k' => 'v'];
        $context['list'] = [new SourceSandboxItem(), new SourceSandboxItem()];

        foreach (['partials/probe.html.twig', 'partials/child.html.twig', 'partials/plain.html.twig', 'partials/attr.html.twig'] as $name) {
            self::assertSame(
                $this->env([], false)->render($name, $context),
                $this->env()->render($name, $context),
                $name
            );
        }

        // Trusted templates were never sandboxed, and still are not.
        self::assertSame('<b>probe</b>', $this->env()->render('partials/probe.html.twig', $context));
    }

    public function testDiskTemplate_CountsNoSourcePolicyCalls(): void
    {
        $context = ['probe' => 'x', 'item' => new SourceSandboxItem(), 'map' => ['k' => 'v'], 'list' => [new SourceSandboxItem()]];

        // The first render compiles, which asks the policy once per template.
        $counter = new CountingSourcePolicy();
        $env = $this->env([], true, $counter);
        $env->render('partials/child.html.twig', $context);
        $env->render('partials/attr.html.twig', $context);
        $counter->calls = 0;
        $env->render('partials/child.html.twig', $context);
        $env->render('partials/attr.html.twig', $context);

        self::assertSame(0, $counter->calls, 'A trusted template should not consult the source policy when it renders.');

        $counter = new CountingSourcePolicy();
        $legacy = $this->env([], false, $counter);
        $legacy->render('partials/child.html.twig', $context);
        $legacy->render('partials/attr.html.twig', $context);
        $counter->calls = 0;
        $legacy->render('partials/child.html.twig', $context);
        $legacy->render('partials/attr.html.twig', $context);

        self::assertGreaterThan(0, $counter->calls);
    }

    public function testDiskTemplate_ClassNamesChangeSoOldCompiledTemplatesAreNotReused(): void
    {
        $env = $this->env();
        $legacy = $this->env([], false);

        self::assertNotSame(
            $legacy->getTemplateClass('partials/probe.html.twig'),
            $env->getTemplateClass('partials/probe.html.twig')
        );
        self::assertStringEndsWith('___2', $env->getTemplateClass('partials/probe.html.twig', 2));
    }

    // =========================================================================
    // (c) Editor content including a disk partial behaves as before
    // =========================================================================

    public function testPageIncludingDiskPartial_PartialStaysTrusted(): void
    {
        $templates = ['@Page:inc' => "{% include 'partials/probe.html.twig' %}|{{ name|upper }}"];
        $context = ['probe' => new SourceSandboxProbe(), 'name' => 'grav'];

        $expected = $this->env($templates, false)->render('@Page:inc', $context);
        self::assertSame('<b>probe</b>|GRAV', $expected);
        self::assertSame($expected, $this->env($templates)->render('@Page:inc', $context));
    }

    public function testPageIncludingDiskPartial_PageOwnCodeIsStillChecked(): void
    {
        $env = $this->env(['@Page:inc' => "{% include 'partials/plain.html.twig' %}{{ probe }}"]);

        $this->expectException(SecurityNotAllowedMethodError::class);
        $env->render('@Page:inc', ['probe' => new SourceSandboxProbe(), 'name' => 'grav']);
    }

    // =========================================================================
    // The sandbox switched on for everything still checks disk templates
    // =========================================================================

    public function testSandboxTagInPage_ChecksTheIncludedDiskPartial(): void
    {
        $templates = ['@Page:sb' => "{% sandbox %}{% include 'partials/probe.html.twig' %}{% endsandbox %}"];

        foreach ([false, true] as $sourceSandbox) {
            try {
                @$this->env($templates, $sourceSandbox)->render('@Page:sb', ['probe' => new SourceSandboxProbe()]);
                self::fail('The sandboxed include should have been blocked');
            } catch (SecurityNotAllowedMethodError $e) {
                self::assertStringContainsString('__tostring', strtolower($e->getMessage()));
            }
        }
    }

    public function testSandboxTagInDiskTemplate_ChecksTheIncludedDiskPartial(): void
    {
        $this->writeTemplate('outer.html.twig', "{% sandbox %}{% include 'partials/child.html.twig' %}{% endsandbox %}");

        $env = $this->env();
        $this->expectException(SecurityNotAllowedMethodError::class);
        @$env->render('outer.html.twig', ['probe' => new SourceSandboxProbe()]);
    }

    public function testSandboxedIncludeFunction_ChecksTheDiskPartialAndItsParent(): void
    {
        $this->writeTemplate('outer.html.twig', "{{ include('partials/child.html.twig', sandboxed = true) }}");

        // A compliant value renders the same as before, through the parent.
        self::assertSame(
            @$this->env([], false)->render('outer.html.twig', ['probe' => 'ok']),
            @$this->env()->render('outer.html.twig', ['probe' => 'ok'])
        );

        $this->writeTemplate('partials/base2.html.twig', '<main>{{ probe }}{% block body %}{% endblock %}</main>');
        $this->writeTemplate('partials/child2.html.twig', "{% extends 'partials/base2.html.twig' %}{% block body %}x{% endblock %}");
        $this->writeTemplate('outer2.html.twig', "{{ include('partials/child2.html.twig', sandboxed = true) }}");

        $this->expectException(SecurityNotAllowedMethodError::class);
        @$this->env()->render('outer2.html.twig', ['probe' => new SourceSandboxProbe()]);
    }

    public function testSandboxedRender_DoesNotLeakIntoLaterTrustedRenders(): void
    {
        $this->writeTemplate('outer.html.twig', "{{ include('partials/probe.html.twig', sandboxed = true) }}");
        $env = $this->env();
        $context = ['probe' => new SourceSandboxProbe()];

        self::assertSame('<b>probe</b>', $env->render('partials/probe.html.twig', $context));

        try {
            @$env->render('outer.html.twig', $context);
            self::fail('The sandboxed include should have been blocked');
        } catch (SecurityNotAllowedMethodError $e) {
            $this->addToAssertionCount(1);
        }

        self::assertSame('<b>probe</b>', $env->render('partials/probe.html.twig', $context));
    }

    public function testSandboxedInclude_OfStringTemplateIsChecked(): void
    {
        $this->writeTemplate('outer.html.twig', "{{ include(template_from_string(code), sandboxed = true) }}");
        $env = $this->env();

        self::assertSame('OK', @$env->render('outer.html.twig', ['code' => '{{ "ok"|upper }}']));

        $this->expectException(SecurityNotAllowedMethodError::class);
        @$env->render('outer.html.twig', ['code' => '{{ probe }}', 'probe' => new SourceSandboxProbe()]);
    }

    /**
     * A closure is the one piece of a trusted template that can run inside a
     * sandboxed render without passing the guard, so an arrow function body in
     * a disk template keeps its checks, the same as before.
     */
    public function testClosureFromDiskTemplate_IsCheckedInsideTheSandbox(): void
    {
        $this->writeTemplate('partials/apply.html.twig', '{{ items|map(f)|join }}');
        $this->writeTemplate('outer.html.twig', "{{ include('partials/apply.html.twig', {f: (x) => probe.secret ~ x, items: ['!']}, sandboxed = true) }}");

        foreach ([false, true] as $sourceSandbox) {
            try {
                @$this->env([], $sourceSandbox)->render('outer.html.twig', ['probe' => new SourceSandboxProbe()]);
                self::fail('The closure should have been checked');
            } catch (SecurityNotAllowedPropertyError $e) {
                $this->addToAssertionCount(1);
            }
        }
    }

    /**
     * A trusted template loaded before the sandbox was switched on has no checks
     * of its own, so it refuses to render inside the sandbox instead.
     */
    public function testPreloadedTrustedTemplate_RefusesToRenderInsideTheSandbox(): void
    {
        $this->writeTemplate('outer.html.twig', '{{ include(wrapper, sandboxed = true) }}');
        $env = $this->env();
        $wrapper = $env->load('partials/plain.html.twig');

        $this->expectException(SecurityError::class);
        $this->expectExceptionMessage('cannot be rendered while the sandbox is enabled');
        @$env->render('outer.html.twig', ['wrapper' => $wrapper, 'name' => 'grav']);
    }

    // =========================================================================
    // Wiring
    // =========================================================================

    public function testGravSourcePolicy_TrustsOnlyTemplatesWithAFilePath(): void
    {
        $policy = new GravSourcePolicy();
        self::assertInstanceOf(CompileTimeSourcePolicyInterface::class, $policy);

        self::assertTrue($policy->isTrusted(new Source('', 'partials/probe.html.twig', $this->dir . '/partials/probe.html.twig')));
        self::assertFalse($policy->isTrusted(new Source('', '__string_template__abc')));
        self::assertFalse($policy->isTrusted(new Source('', '@Page:x')));
    }

    public function testGravTwig_CompilesDiskTemplatesWithoutChecks(): void
    {
        $grav = Fixtures::get('grav')();
        $grav['config']->set('security.twig_sandbox.enabled', true);
        // No theme is installed for unit tests; an empty one is enough here.
        $grav['locator']->addPath('theme', '', 'user/themes');
        $twig = $grav['twig'];
        $twig->init();

        $env = $twig->twig();
        self::assertInstanceOf(TwigEnvironment::class, $env);

        $twig->setTemplate('@Page:wiring', '{{ x }}');
        $page = $env->compileSource($env->getLoader()->getSourceContext('@Page:wiring'));
        self::assertStringContainsString('ensureToStringAllowed', $page);

        $twig->addPath($this->dir);
        $disk = $env->compileSource($env->getLoader()->getSourceContext('partials/probe.html.twig'));
        self::assertStringNotContainsString('ensureToStringAllowed', $disk);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * @param array<string,string> $strings String templates, name => code.
     * @param bool $sourceSandbox Whether to compile trusted templates without checks.
     * @param CompileTimeSourcePolicyInterface|null $policy
     * @return Environment
     */
    private function env(array $strings = [], bool $sourceSandbox = true, $policy = null): Environment
    {
        $loader = new ChainLoader([new ArrayLoader($strings), new FilesystemLoader($this->dir)]);
        $env = new TwigEnvironment($loader, ['cache' => false, 'autoescape' => 'html']);
        $env->addExtension(new StringLoaderExtension());
        $policy = $policy ?? new GravSourcePolicy();
        $env->addExtension(@new SandboxExtension(Security::buildTwigSandboxPolicy(), false, $sourceSandbox ? $policy : new LegacySourcePolicy($policy)));

        return $env;
    }

    private function writeTemplate(string $name, string $code): void
    {
        file_put_contents($this->dir . '/' . $name, $code);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($dir);
    }
}

/**
 * An object no sandbox policy allows: printing it, calling a method or reading
 * a property must all be refused in a sandboxed template.
 */
class SourceSandboxProbe
{
    public $secret = 'secret';

    public function __toString(): string
    {
        return 'probe';
    }
}

/**
 * An object the sandbox policy lets templates read, so trusted and legacy
 * renders produce the same output.
 */
class SourceSandboxItem
{
    public $title = 'item';

    public function getTitle(): string
    {
        return $this->title;
    }
}

/**
 * GravSourcePolicy with a call counter, to prove trusted templates skip it.
 */
final class CountingSourcePolicy implements CompileTimeSourcePolicyInterface
{
    /** @var int */
    public $calls = 0;

    public function enableSandbox(Source $source): bool
    {
        $this->calls++;

        return (new GravSourcePolicy())->enableSandbox($source);
    }

    public function isTrusted(Source $source): bool
    {
        return (new GravSourcePolicy())->isTrusted($source);
    }
}

/**
 * The same decisions through a plain SourcePolicyInterface, which Twig only
 * applies at runtime: how every template compiled before this change.
 */
final class LegacySourcePolicy implements SourcePolicyInterface
{
    public function __construct(private SourcePolicyInterface $policy)
    {
    }

    public function enableSandbox(Source $source): bool
    {
        return $this->policy->enableSandbox($source);
    }
}
