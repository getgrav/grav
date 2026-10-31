<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Common\Security;
use Grav\Common\Twig\Sandbox\ContentIncludeExtension;
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

    /** @var mixed */
    private $trustedPartials;

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

        $this->trustedPartials = Grav::instance()['config']->get('security.twig_sandbox.trusted_partials');

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
        Grav::instance()['config']->set('security.twig_sandbox.trusted_partials', $this->trustedPartials);
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
        // Only the guard remains: it hands a sandboxed render over to the fully
        // checked variant of the template, and throws on any path that did not.
        self::assertStringContainsString('public function ensureSecurityCheckedOrHandOver(): ?\\Twig\\Template', $code);
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

        // TwigEnvironment::render() asks once per render, for the template it
        // was given; nothing inside the render asks again.
        self::assertSame(2, $counter->calls, 'A trusted template should not consult the source policy while it renders.');

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
    // (c) A disk template that editor content loads is sandboxed with it
    // =========================================================================

    public function testPageIncludingDiskPartial_PartialInsideThePolicyRenders(): void
    {
        $templates = ['@Page:inc' => "{% include 'partials/plain.html.twig' %}|{{ name|upper }}"];

        foreach ([false, true] as $sourceSandbox) {
            self::assertSame('<i>GRAV</i>|GRAV', $this->env($templates, $sourceSandbox)->render('@Page:inc', ['name' => 'grav']));
        }
    }

    /**
     * The partial runs on variables the content chose, so it must not do for the
     * content what the content may not do itself.
     *
     * @dataProvider sandboxedPrefixProvider
     */
    public function testPageIncludingDiskPartial_PartialIsChecked(string $prefix): void
    {
        $env = $this->env([$prefix . 'inc' => "{% include 'partials/probe.html.twig' %}"]);

        $this->expectException(SecurityNotAllowedMethodError::class);
        $env->render($prefix . 'inc', ['probe' => new SourceSandboxProbe()]);
    }

    /**
     * `partials/gadget.html.twig` stays inside the tag, filter and function
     * allowlists, and reads one of its variables with another, as theme and plugin
     * templates do. Each way of loading it hands it an object the content cannot read.
     *
     * @dataProvider loadedFromContentProvider
     */
    public function testDiskTemplateLoadedFromContent_DoesNotReadForTheContent(string $code): void
    {
        $this->writeGadgets();
        $context = ['probe' => new SourceSandboxProbe(), 'a' => new SourceSandboxProbe(), 'b' => 'secret'];

        // Rendered by Grav on its own variables, the same file is trusted.
        self::assertSame('secret', $this->env()->render('partials/gadget.html.twig', $context));

        foreach ([false, true] as $preloaded) {
            $env = $this->env(['@Page:load' => $code]);
            if ($preloaded) {
                $env->render('partials/gadget.html.twig', $context);
                $env->load('partials/gadget_blocks.html.twig');
                $env->load('partials/gadget_macro.html.twig');
            }

            try {
                $output = $env->render('@Page:load', $context);
                self::fail('The read should have been blocked, got: ' . $output);
            } catch (SecurityNotAllowedPropertyError $e) {
                self::assertSame('secret', $e->getPropertyName());
            }
            self::assertFalse($env->getExtension(SandboxExtension::class)->isSandboxed(), 'The sandbox is switched off again after the render.');
        }
    }

    public static function loadedFromContentProvider(): array
    {
        return [
            'include' => ["{% include 'partials/gadget.html.twig' with {a: probe, b: 'secret'} %}"],
            'include, inherited context' => ["{% set a = probe %}{% include 'partials/gadget.html.twig' %}"],
            'include, only' => ["{% include 'partials/gadget.html.twig' with {a: probe, b: 'secret'} only %}"],
            'include, list of names' => ["{% include ['partials/missing.html.twig', 'partials/gadget.html.twig'] with {a: probe} %}"],
            'embed' => ["{% embed 'partials/gadget.html.twig' with {a: probe, b: 'secret'} %}{% block out %}[{{ result }}]{% endblock %}{% endembed %}"],
            'extends' => ["{% extends 'partials/gadget.html.twig' %}{% block out %}[{{ result }}]{% endblock %}"],
            'use' => ["{% use 'partials/gadget_blocks.html.twig' %}{{ block('read') }}"],
            'block()' => ["{{ block('read', 'partials/gadget_blocks.html.twig') }}"],
            'import' => ["{% import 'partials/gadget_macro.html.twig' as m %}{{ m.read(probe, 'secret') }}"],
            'from' => ["{% from 'partials/gadget_macro.html.twig' import read %}{{ read(probe, 'secret') }}"],
            'nested include' => ["{% include 'partials/gadget_outer.html.twig' %}"],
        ];
    }

    /**
     * A render PHP starts on its own variables while content is rendering (a
     * module's template behind `page.content`, a plugin's processTemplate()) is
     * trusted, and the content is sandboxed again once it returns.
     */
    public function testTrustedRenderStartedInsideContent_StaysTrusted(): void
    {
        $env = $this->env([
            '@Page:nested' => '{{ item.title }}',
            '@Page:after' => "{{ item.title }}{% include 'partials/probe.html.twig' %}",
        ]);
        // The policy allows any member of a stdClass.
        $item = new class($env) extends stdClass {
            /** @var Environment */
            private $env;

            public function __construct(Environment $env)
            {
                $this->env = $env;
            }

            public function title(): string
            {
                return $this->env->render('partials/probe.html.twig', ['probe' => new SourceSandboxProbe()]);
            }
        };
        $context = ['item' => $item, 'probe' => new SourceSandboxProbe()];

        self::assertSame('&lt;b&gt;probe&lt;/b&gt;', $env->render('@Page:nested', $context));

        $this->expectException(SecurityNotAllowedMethodError::class);
        $env->render('@Page:after', $context);
    }

    // =========================================================================
    // (d) Trusted partials: a trusted render on Grav's own variables
    // =========================================================================

    public function testTrustedPartials_DefaultsAndSiteAdditions(): void
    {
        $config = Grav::instance()['config'];
        $config->set('security.twig_sandbox.trusted_partials', ['partials/card.html.twig', 'forms/form.html.twig', 42]);

        $partials = Security::twigTrustedPartials();
        self::assertContains('forms/form.html.twig', $partials);
        self::assertContains('forms/data.html.twig', $partials);
        self::assertContains('partials/card.html.twig', $partials);
        self::assertSame(array_values(array_unique($partials)), $partials);
        self::assertNotContains(42, $partials);
    }

    /**
     * A listed partial reads what Grav handed the content render, as it would
     * in a theme template, and takes plain values from the content.
     */
    public function testTrustedPartial_RendersOnGravVariablesAndPlainContentValues(): void
    {
        $env = $this->trustedPartialEnv([
            '@Page:t' => "{% set local = 'L' %}{% include 'partials/trusted.html.twig' with {title: 'Hi', list: ['a', 'b']} %}",
            '@Page:only' => "{% set local = 'L' %}{% include 'partials/trusted.html.twig' with {title: 'Hi'} only %}",
        ]);

        self::assertSame('secret|Hi|a,b|L|no', $env->renderContent('@Page:t', $this->contentVars(), $this->gravVars()));
        self::assertSame('secret|Hi||-|no', $env->renderContent('@Page:only', $this->contentVars(), $this->gravVars()));

        // A direct render of a content source hands its own variables to the partial.
        self::assertSame('secret|Hi|a,b|L|no', $env->render('@Page:t', $this->gravVars()));
        self::assertFalse($env->getExtension(SandboxExtension::class)->isSandboxed());
    }

    /**
     * Content cannot replace a variable Grav set, and an object it holds never
     * reaches the partial.
     */
    public function testTrustedPartial_GravVariablesWinAndContentObjectsStayOut(): void
    {
        $env = $this->trustedPartialEnv([
            '@Page:override' => "{% set obj = 'x' %}{% include 'partials/trusted.html.twig' with {obj: {secret: 'forged'}, title: 'T'} %}",
            '@Page:inherited' => "{% set probe2 = probe %}{% include 'partials/trusted.html.twig' with {title: 'T'} %}",
        ]);

        self::assertSame('secret|T||-|no', $env->renderContent('@Page:override', $this->contentVars(), $this->gravVars()));
        self::assertSame('secret|T||-|no', $env->renderContent('@Page:inherited', $this->contentVars(), $this->gravVars()));
    }

    public function testTrustedPartial_RefusesObjectsPassedByContent(): void
    {
        $env = $this->trustedPartialEnv(['@Page:obj' => "{% include 'partials/trusted.html.twig' with {title: probe} %}"]);

        $this->expectException(SecurityError::class);
        $this->expectExceptionMessage('Only plain values can be passed to the trusted partial "partials/trusted.html.twig"');
        $env->renderContent('@Page:obj', $this->contentVars(), $this->gravVars());
    }

    /**
     * Only `include` of the exact listed name gets the trusted render. Any other
     * way of loading the same file, or another spelling of its name, runs it
     * under the sandbox.
     *
     * @dataProvider trustedPartialSandboxedProvider
     */
    public function testTrustedPartial_OtherLoadsStaySandboxed(string $code): void
    {
        $env = $this->trustedPartialEnv(['@Page:other' => $code]);

        $this->expectException(SecurityNotAllowedPropertyError::class);
        $env->renderContent('@Page:other', ['obj' => new SourceSandboxProbe()] + $this->contentVars(), $this->gravVars());
    }

    public static function trustedPartialSandboxedProvider(): array
    {
        return [
            'embed' => ["{% embed 'partials/trusted.html.twig' %}{% endembed %}"],
            'extends' => ["{% extends 'partials/trusted.html.twig' %}"],
            'another spelling' => ["{% include 'partials//trusted.html.twig' %}"],
            'from a partial content included' => ["{% include 'partials/includes_trusted.html.twig' %}"],
        ];
    }

    public function testTrustedPartial_IgnoreMissing(): void
    {
        Grav::instance()['config']->set('security.twig_sandbox.trusted_partials', ['partials/not-there.html.twig']);
        $env = $this->env(['@Page:missing' => "[{% include 'partials/not-there.html.twig' ignore missing %}]"]);

        self::assertSame('[]', $env->renderContent('@Page:missing', [], []));
    }

    /**
     * A Twig global (Grav's `grav`) reaches a trusted partial as the real value,
     * whether content passes a value under the same name or sets one before the
     * include.
     *
     * @dataProvider globalNameProvider
     */
    public function testTrustedPartial_TwigGlobalsCannotBeReplaced(string $code): void
    {
        $this->writeTemplate('partials/member.html.twig', "{{ gate.user.authorized ? 'member' : 'guest' }}|{{ title ?? '-' }}");
        Grav::instance()['config']->set('security.twig_sandbox.trusted_partials', ['partials/member.html.twig']);
        $env = $this->env(['@Page:g' => $code]);
        $env->addGlobal('gate', ['user' => ['authorized' => false]]);

        self::assertSame('guest|T', $env->renderContent('@Page:g', [], []));
        self::assertSame('guest|T', $env->render('@Page:g'));
    }

    public static function globalNameProvider(): array
    {
        return [
            'passed with' => ["{% include 'partials/member.html.twig' with {gate: {user: {authorized: true}}, title: 'T'} %}"],
            'set before the include' => ["{% set gate = {user: {authorized: true}} %}{% set title = 'T' %}{% include 'partials/member.html.twig' %}"],
        ];
    }

    /**
     * Through Grav's own Twig, the `grav` global from GravExtension is the one a
     * trusted partial sees, for both ways content could name it.
     */
    public function testGravTwig_TrustedPartialSeesTheRealGravGlobal(): void
    {
        $grav = Fixtures::get('grav')();
        $grav['config']->set('security.twig_sandbox.enabled', true);
        $grav['locator']->addPath('theme', '', 'user/themes');
        $twig = $grav['twig'];
        $twig->init();
        $twig->addPath($this->dir);
        $this->writeTemplate('partials/marker.html.twig', "{{ grav.marker ?? 'real' }}");
        $grav['config']->set('security.twig_sandbox.trusted_partials', ['partials/marker.html.twig']);

        self::assertSame('real', $twig->processString("{% include 'partials/marker.html.twig' %}"));
        self::assertSame('real', $twig->processString("{% include 'partials/marker.html.twig' with {grav: {marker: 'other'}} %}"));
        self::assertSame('real', $twig->processString("{% set grav = {marker: 'other'} %}{% include 'partials/marker.html.twig' %}"));
    }

    /**
     * `forms/data.save.txt.twig` writes literal text (escaping off) for the Form
     * plugin's save action, which renders its body through processString()
     * (a `@Var:` source). Included from page content or an email body, which
     * are HTML, its output is escaped.
     *
     * @dataProvider textPartialProvider
     */
    public function testTextPartial_LiteralInVarRendersEscapedInHtmlRenders(string $prefix, string $expected): void
    {
        $this->writeTextPartial();
        $env = $this->env([$prefix . 'save' => '{% include "forms/data.save.txt.twig" %}']);
        $form = new SourceSandboxForm('<img src=x onerror=alert(1)> & "q"');

        self::assertSame($expected, $env->renderContent($prefix . 'save', [], ['form' => $form]));
        self::assertSame($expected, $env->render($prefix . 'save', ['form' => $form]));
    }

    public static function textPartialProvider(): array
    {
        $escaped = "Message: &lt;img src=x onerror=alert(1)&gt; &amp; &quot;q&quot;\n";

        return [
            '@Var: (form save body)' => ['@Var:', "Message: <img src=x onerror=alert(1)> & \"q\"\n"],
            '@Page: (page content)' => ['@Page:', $escaped],
            '@EmailVar: (email body)' => ['@EmailVar:', $escaped],
        ];
    }

    /**
     * Through Grav's own Twig: the Form plugin's default save body keeps writing
     * the submitted text as is, and page content that includes the same
     * template gets it escaped.
     */
    public function testGravTwig_TextPartialLiteralForSaveBodyEscapedInPageContent(): void
    {
        $grav = Fixtures::get('grav')();
        $grav['config']->set('security.twig_sandbox.enabled', true);
        $grav['locator']->addPath('theme', '', 'user/themes');
        $twig = $grav['twig'];
        $twig->init();
        $twig->addPath($this->dir);
        $this->writeTextPartial();
        $form = new SourceSandboxForm('<img src=x onerror=alert(1)>');

        // form.php: $twig->processString($params['body'] ?? '{% include "forms/data.save.txt.twig" %}', $vars)
        self::assertSame("Message: <img src=x onerror=alert(1)>\n", $twig->processString('{% include "forms/data.save.txt.twig" %}', ['form' => $form]));

        $env = $twig->twig();
        $twig->setTemplate('@Page:form-save', 'Before {% include "forms/data.save.txt.twig" %}');
        self::assertSame("Before Message: &lt;img src=x onerror=alert(1)&gt;\n", $env->renderContent('@Page:form-save', [], ['form' => $form]));
    }

    /**
     * Through Grav's own Twig: the page content render hands the trusted partial
     * the unfiltered variables, and an unlisted partial that steps outside the
     * policy soft-fails like the content itself.
     */
    public function testGravTwig_ProcessStringUsesTrustedPartials(): void
    {
        $grav = Fixtures::get('grav')();
        $grav['config']->set('security.twig_sandbox.enabled', true);
        $grav['locator']->addPath('theme', '', 'user/themes');
        $twig = $grav['twig'];
        $twig->init();
        $twig->addPath($this->dir);
        $this->writeTemplate('partials/trusted.html.twig', '{{ obj.secret }}|{{ title }}');
        $grav['config']->set('security.twig_sandbox.trusted_partials', ['partials/trusted.html.twig']);

        self::assertSame('secret|Hi', $twig->processString("{% include 'partials/trusted.html.twig' with {title: 'Hi'} %}", ['obj' => new SourceSandboxProbe()]));

        $code = "{% include 'partials/probe.html.twig' %}";
        self::assertStringStartsWith($code, $twig->processString($code, ['probe' => new SourceSandboxProbe()]));
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
     * A trusted template loaded before the sandbox was switched on (here handed to
     * a sandboxed include as a TemplateWrapper) has no checks of its own, so it
     * renders as its fully checked variant instead: the same output as before the
     * change, where every template carried the runtime checks.
     */
    public function testPreloadedTrustedTemplate_RendersCheckedInsideTheSandbox(): void
    {
        $this->writeTemplate('outer.html.twig', '{{ include(wrapper, sandboxed = true) }}');
        $this->writeTemplate('outer_tag.html.twig', '{% sandbox %}{% include wrapper %}{% endsandbox %}');

        foreach (['outer.html.twig', 'outer_tag.html.twig'] as $outer) {
            foreach (['partials/plain.html.twig', 'partials/probe.html.twig', 'partials/child.html.twig'] as $name) {
                $render = static fn (Environment $env) => @$env->render($outer, ['wrapper' => $env->load($name), 'name' => 'grav', 'probe' => 'ok']);
                $expected = $this->assertSameAsBefore("$outer $name", $render);
                self::assertNotSame('', $expected);
            }
        }

        $env = $this->env();
        self::assertSame('<main>[ok]</main>', @$env->render('outer.html.twig', ['wrapper' => $env->load('partials/child.html.twig'), 'probe' => 'ok']));
    }

    /**
     * @dataProvider preloadedDisallowedProvider
     */
    public function testPreloadedTrustedTemplate_KeepsDisallowedAccessBlocked(string $code, array $context, string $error): void
    {
        $this->writeTemplate('outer.html.twig', '{{ include(wrapper, sandboxed = true) }}');
        $this->writeTemplate('partials/disallowed.html.twig', $code);
        $context = array_map(static fn ($value) => \is_callable($value) ? $value() : $value, $context);

        // Outside the sandbox the trusted template runs unchecked, as always.
        $this->env()->render('partials/disallowed.html.twig', $context);

        $this->assertSameAsBefore($code, static fn (Environment $env) => @$env->render('outer.html.twig', ['wrapper' => $env->load('partials/disallowed.html.twig')] + $context), $error);
    }

    public static function preloadedDisallowedProvider(): array
    {
        return [
            'method' => ["{{ cfg.set('a', 'b') ? 'y' : 'n' }}", ['cfg' => static fn () => new \Grav\Common\Config\Config([])], SecurityNotAllowedMethodError::class],
            'property' => ['{{ probe.secret }}', ['probe' => static fn () => new SourceSandboxProbe()], SecurityNotAllowedPropertyError::class],
            'filter' => ['{{ "<b>x</b>"|raw }}', [], SecurityNotAllowedFilterError::class],
            'function' => ["{{ constant('PHP_VERSION') }}", [], SecurityNotAllowedFunctionError::class],
            '__toString' => ['{{ probe }}', ['probe' => static fn () => new SourceSandboxProbe()], SecurityNotAllowedMethodError::class],
            'through the parent' => ["{% extends 'partials/base.html.twig' %}{% block body %}{{ probe.secret }}{% endblock %}", ['probe' => static fn () => new SourceSandboxProbe()], SecurityNotAllowedPropertyError::class],
        ];
    }

    /**
     * Macros and blocks of a preloaded trusted template, reached from inside a
     * sandboxed include, run from the checked variant too.
     */
    public function testPreloadedTrustedMacrosAndBlocks_RunCheckedInsideTheSandbox(): void
    {
        $this->writeTemplate('partials/macros.html.twig', '{% macro show(v) %}<{{ v }}>{% endmacro %}');
        $this->writeTemplate('partials/macro_child.html.twig', "{% extends 'partials/macros.html.twig' %}");
        $this->writeTemplate('partials/call_macro.html.twig', '{% import wrapper as m %}{{ m.show(probe) }}');
        $this->writeTemplate('partials/call_block.html.twig', "{{ block('body', wrapper) }}");
        $this->writeTemplate('outer_macro.html.twig', "{{ include('partials/call_macro.html.twig', sandboxed = true) }}");
        $this->writeTemplate('outer_block.html.twig', "{{ include('partials/call_block.html.twig', sandboxed = true) }}");

        foreach (['outer_macro.html.twig' => ['partials/macros.html.twig', 'partials/macro_child.html.twig'], 'outer_block.html.twig' => ['partials/child.html.twig']] as $outer => $names) {
            foreach ($names as $name) {
                $this->assertSameAsBefore("$outer $name", static fn (Environment $env) => @$env->render($outer, ['wrapper' => $env->load($name), 'probe' => 'ok']));
                $this->assertSameAsBefore("$outer $name", static fn (Environment $env) => @$env->render($outer, ['wrapper' => $env->load($name), 'probe' => new SourceSandboxProbe()]), SecurityNotAllowedMethodError::class);
            }
        }

        $env = $this->env();
        self::assertSame('<ok>', @$env->render('outer_macro.html.twig', ['wrapper' => $env->load('partials/macro_child.html.twig'), 'probe' => 'ok']));
        self::assertSame('[ok]', @$env->render('outer_block.html.twig', ['wrapper' => $env->load('partials/child.html.twig'), 'probe' => 'ok']));
    }

    /**
     * With the sandbox off, a trusted template never loads its checked variant.
     */
    public function testTrustedTemplate_NeverLoadsTheCheckedVariantWithTheSandboxOff(): void
    {
        $env = $this->env();
        $template = $env->load('partials/child.html.twig');
        self::assertNull($template->unwrap($env)->ensureSecurityCheckedOrHandOver());
        self::assertSame('<main>[x]</main>', $template->render(['probe' => 'x']));
        self::assertSame('[x]', $template->renderBlock('body', ['probe' => 'x']));

        $checker = $env->getExtension(SandboxExtension::class)->getChecker();
        $checker->setSandboxed(true);
        try {
            $class = $env->getTemplateClass('partials/child.html.twig');
        } finally {
            $checker->setSandboxed(false);
        }
        self::assertStringEndsWith('_sandboxed', $class);
        self::assertFalse(class_exists($class, false));
    }

    /**
     * The guard still throws for any path that does not hand over to the checked
     * variant, so a path Twig adds later fails closed instead of running unchecked.
     */
    public function testPreloadedTrustedTemplate_GuardStillThrowsAsTheBackstop(): void
    {
        $env = $this->env();
        $template = $env->load('partials/plain.html.twig')->unwrap($env);
        $checker = $env->getExtension(SandboxExtension::class)->getChecker();

        $template->ensureSecurityChecked();
        $checker->setSandboxed(true);
        try {
            $this->expectException(SecurityError::class);
            $this->expectExceptionMessage('cannot be rendered while the sandbox is enabled');
            $template->ensureSecurityChecked();
        } finally {
            $checker->setSandboxed(false);
        }
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
     * @return TwigEnvironment
     */
    private function env(array $strings = [], bool $sourceSandbox = true, $policy = null): TwigEnvironment
    {
        $loader = new ChainLoader([new ArrayLoader($strings), new FilesystemLoader($this->dir)]);
        $env = new TwigEnvironment($loader, ['cache' => false, 'autoescape' => 'html']);
        $env->addExtension(new StringLoaderExtension());
        $policy = $policy ?? new GravSourcePolicy();
        $env->addExtension(@new SandboxExtension(Security::buildTwigSandboxPolicy(), false, $sourceSandbox ? $policy : new LegacySourcePolicy($policy)));
        $env->addExtension(new ContentIncludeExtension());

        return $env;
    }

    /**
     * An environment where `partials/trusted.html.twig` is a trusted partial. It
     * prints `obj.secret` (an object only Grav hands in), `title`, `list`,
     * `local`, and whether `probe2` (an object content holds) reached it.
     *
     * @param array<string,string> $strings
     * @return TwigEnvironment
     */
    private function trustedPartialEnv(array $strings): TwigEnvironment
    {
        $this->writeTemplate('partials/trusted.html.twig', "{{ obj.secret }}|{{ title }}|{{ list|default([])|join(',') }}|{{ local ?? '-' }}|{{ probe2 is defined ? 'yes' : 'no' }}");
        $this->writeTemplate('partials/includes_trusted.html.twig', "{% include 'partials/trusted.html.twig' %}");
        Grav::instance()['config']->set('security.twig_sandbox.trusted_partials', ['partials/trusted.html.twig']);

        return $this->env($strings);
    }

    /**
     * What PHP hands a content render's trusted partials.
     */
    private function gravVars(): array
    {
        return ['obj' => new SourceSandboxProbe(), 'probe' => new SourceSandboxProbe()];
    }

    /**
     * What the content itself sees (Twig::processPage() filters its variables).
     */
    private function contentVars(): array
    {
        return ['probe' => new SourceSandboxProbe()];
    }

    /**
     * A stand-in for the Form plugin's `forms/data.save.txt.twig`: escaping off,
     * reading the submitted value through a method on the form object.
     */
    private function writeTextPartial(): void
    {
        if (!is_dir($this->dir . '/forms')) {
            mkdir($this->dir . '/forms', 0777, true);
        }
        $this->writeTemplate('forms/data.save.txt.twig', "{%- autoescape false %}{% for field in form.fields %}{{ field.label }}: {{ form.value(field.name) ~ \"\\n\" }}{% endfor %}{%- endautoescape %}");
    }

    private function writeGadgets(): void
    {
        $this->writeTemplate('partials/gadget.html.twig', "{% set result = attribute(a, b) %}{% block out %}{{ result }}{% endblock %}");
        $this->writeTemplate('partials/gadget_blocks.html.twig', "{% block read %}{{ attribute(a, b) }}{% endblock %}");
        $this->writeTemplate('partials/gadget_macro.html.twig', "{% macro read(a, b) %}{{ attribute(a, b) }}{% endmacro %}");
        $this->writeTemplate('partials/gadget_outer.html.twig', "{% include 'partials/gadget.html.twig' with {a: probe, b: 'secret'} %}");
    }

    /**
     * Runs $render against the source-sandboxed environment and the legacy one
     * (every template carries the runtime checks, as before this change) and
     * asserts both give the same output, or both fail with $error.
     *
     * @param callable(Environment): string $render
     * @param class-string<SecurityError>|null $error
     * @return string|null The output, when there is one.
     */
    private function assertSameAsBefore(string $message, callable $render, ?string $error = null): ?string
    {
        $results = [];
        foreach ([false, true] as $sourceSandbox) {
            try {
                $results[] = ['output', $render($this->env([], $sourceSandbox))];
            } catch (SecurityError $e) {
                $results[] = ['error', get_class($e)];
            }
        }

        self::assertSame($results[0], $results[1], $message);
        self::assertSame($error === null ? ['output', $results[1][1]] : ['error', $error], $results[1], $message);

        return $error === null ? $results[1][1] : null;
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
 * A form with one submitted value, the way the Form plugin's data templates
 * read it.
 */
class SourceSandboxForm
{
    /** @var array<int,array<string,string>> */
    public $fields = [['name' => 'message', 'label' => 'Message']];

    public function __construct(private string $message)
    {
    }

    public function value(string $name): ?string
    {
        return $name === 'message' ? $this->message : null;
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
