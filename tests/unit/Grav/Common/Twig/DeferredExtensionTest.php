<?php

use Twig\DeferredExtension\DeferredExtension;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
/**
 * Behavioural tests for the bundled Twig DeferredExtension.
 *
 * The extension lets a template declare `{% block foo deferred %}` so that
 * its body is rendered AFTER the rest of the template has finished. This is
 * how Grav can place `{{ assets.css() }}` in `<head>` while letting the
 * `<body>` add to that asset registry as it renders.
 *
 * These tests run against a real Twig Environment and exercise the
 * DeferredTokenParser, the DeferredNodeVisitor's ModuleNode rewrites, and the
 * defer/resolve buffering in DeferredExtension itself. They model the Grav
 * "assets registry" pattern (mutate a global service object during render,
 * read it from a deferred block) rather than `{% set %}`, which mutates only
 * the local compiled $context and would not survive across the deferred
 * resolve boundary.
 */
class DeferredExtensionTest extends \PHPUnit\Framework\TestCase
{
    private function env(array $templates, array $globals = []): Environment
    {
        $env = new Environment(new ArrayLoader($templates), [
            'cache' => false,
            'strict_variables' => false,
            'autoescape' => false,
        ]);
        $env->addExtension(new DeferredExtension());

        foreach ($globals as $name => $value) {
            $env->addGlobal($name, $value);
        }

        return $env;
    }

    /**
     * Stand-in for Grav's Assets service — a stateful registry that templates
     * can push to during render and read from later.
     */
    private function assets(): object
    {
        return new class {
            /** @var string[] */
            public array $css = [];
            public function add(string $href): string { $this->css[] = $href; return ''; }
            public function render(): string
            {
                return implode("\n", array_map(
                    fn(string $h) => '<link rel="stylesheet" href="' . $h . '">',
                    $this->css
                ));
            }
        };
    }

    public function testDeferredBlockSeesMutationsMadeAfterParentBegins(): void
    {
        // Parent emits a deferred head block, then the body mutates the
        // shared assets object. At resolve time, the head block sees the
        // final assets state — exactly the pattern Grav uses for CSS/JS.
        $parent = <<<'TWIG'
        <head>{% block head deferred %}{{ assets.render() }}{% endblock %}</head>
        <body>
        {{ assets.add('/a.css') }}{{ assets.add('/b.css') }}
        {% block body %}body{% endblock %}
        </body>
        TWIG;

        $env = $this->env(['parent.twig' => $parent], ['assets' => $this->assets()]);
        $out = $env->render('parent.twig');

        self::assertStringContainsString('<link rel="stylesheet" href="/a.css">', $out);
        self::assertStringContainsString('<link rel="stylesheet" href="/b.css">', $out);
        self::assertLessThan(
            strpos($out, '<body>'),
            strpos($out, '<link rel="stylesheet" href="/a.css">'),
            'Deferred head output must land inside <head>, before <body>'
        );
    }

    public function testNonDeferredBlockOnlySeesMutationsMadeBeforeIt(): void
    {
        // Without `deferred`, the head block runs eagerly and never sees
        // assets added by the body that follows.
        $parent = <<<'TWIG'
        <head>{% block head %}{{ assets.render() }}{% endblock %}</head>
        <body>{{ assets.add('/late.css') }}{% block body %}body{% endblock %}</body>
        TWIG;

        $env = $this->env(['parent.twig' => $parent], ['assets' => $this->assets()]);
        $out = $env->render('parent.twig');

        self::assertStringNotContainsString('/late.css', substr($out, 0, strpos($out, '<body>')));
    }

    public function testMultipleDeferredBlocksAllResolve(): void
    {
        $parent = <<<'TWIG'
        <head>
        {% block title deferred %}<title>{{ meta.title }}</title>{% endblock %}
        {% block links deferred %}{{ assets.render() }}{% endblock %}
        </head>
        {{ meta.set('T') }}{{ assets.add('/x.css') }}
        {% block body %}b{% endblock %}
        TWIG;

        $meta = new class {
            public string $title = '';
            public function set(string $t): string { $this->title = $t; return ''; }
        };

        $env = $this->env(['parent.twig' => $parent], [
            'assets' => $this->assets(),
            'meta'   => $meta,
        ]);
        $out = $env->render('parent.twig');

        self::assertStringContainsString('<title>T</title>', $out);
        self::assertStringContainsString('<link rel="stylesheet" href="/x.css">', $out);
    }

    public function testDeferredBlockInsideConditionalChildOverrideRenders(): void
    {
        // Exercises the Grav-specific Parser::filterBodyNodes() patch that
        // treats IfNode as "transparent" for block-definition nesting,
        // so that a child can re-declare a parent's block from inside {% if %}.
        $parent = <<<'TWIG'
        <head>{% block head deferred %}default{% endblock %}</head>
        <body>{{ assets.add('/x.css') }}{% block body %}b{% endblock %}</body>
        TWIG;

        $child = <<<'TWIG'
        {% extends 'parent.twig' %}
        {% if true %}
            {% block head deferred %}<links>{{ assets.render() }}</links>{% endblock %}
        {% endif %}
        TWIG;

        $env = $this->env(
            ['parent.twig' => $parent, 'child.twig' => $child],
            ['assets' => $this->assets()]
        );
        $out = $env->render('child.twig');

        self::assertStringContainsString('<links><link rel="stylesheet" href="/x.css"></links>', $out);
    }

    public function testChildOverridesDeferredParentBlock(): void
    {
        // Standard inheritance: child block replaces the deferred parent
        // block, and still benefits from late resolution.
        $parent = <<<'TWIG'
        <head>{% block head deferred %}DEFAULT{% endblock %}</head>
        <body>{{ assets.add('/x.css') }}{% block body %}b{% endblock %}</body>
        TWIG;

        $child = <<<'TWIG'
        {% extends 'parent.twig' %}
        {% block head deferred %}CHILD:{{ assets.render() }}{% endblock %}
        TWIG;

        $env = $this->env(
            ['parent.twig' => $parent, 'child.twig' => $child],
            ['assets' => $this->assets()]
        );
        $out = $env->render('child.twig');

        self::assertStringContainsString('CHILD:<link rel="stylesheet" href="/x.css">', $out);
        self::assertStringNotContainsString('DEFAULT', $out);
    }

    public function testRegularBlockTagStillWorksUnchanged(): void
    {
        // DeferredTokenParser overrides the stock `block` tag — a vanilla
        // `{% block %}` (no `deferred` keyword) must keep normal semantics.
        $env = $this->env(['t.twig' => "{% block g %}hi{% endblock %}"]);
        self::assertSame('hi', trim($env->render('t.twig')));
    }

    public function testDeferredBlockWithoutOverrideRendersInPlaceholderSlot(): void
    {
        $parent = "[{% block head deferred %}H{% endblock %}]end";
        $env = $this->env(['parent.twig' => $parent]);
        $out = $env->render('parent.twig');

        self::assertStringContainsString('[H]', $out);
        self::assertStringContainsString('end', $out);
    }
}
