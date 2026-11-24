<?php

use Grav\Common\Twig\Sandbox\GravSourcePolicy;
use Grav\Common\Twig\TwigEnvironment;
use Twig\DeferredExtension\DeferredExtension;
use Twig\Environment;
use Twig\Extension\SandboxExtension;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;
use Twig\Sandbox\SecurityPolicy;
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
    /** @var string[] */
    private array $dirs = [];

    protected function tearDown(): void
    {
        foreach ($this->dirs as $dir) {
            array_map('unlink', glob($dir . '/*') ?: []);
            rmdir($dir);
        }
        $this->dirs = [];

        parent::tearDown();
    }

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
        // Exercises the Grav-specific Parser::cleanupTransparentBodyNodes()
        // patch that treats IfNode as "transparent" for block-definition
        // nesting, so that a child can re-declare a parent's block from
        // inside {% if %}. Here the `if` is one node among others at the root
        // of the child; see the sibling test for the whole-body shape.
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
        // The definition must not ALSO render where it was declared.
        self::assertSame(1, substr_count($out, '<links>'));
    }

    /**
     * Regression coverage for getgrav/grav#4256.
     *
     * The child wraps its whole body — the `extends` tag included — in a
     * single `{% if %}`, the shape the form plugin's field template uses. The
     * body cleanup used to miss that case, so the deferred definition was
     * rendered where it was declared as well as in the parent's slot, ahead of
     * even the `<head>` the parent opens with.
     */
    public function testDeferredBlockUnderAnIfWrappingTheWholeChildBody(): void
    {
        $parent = <<<'TWIG'
        <head>{% block head deferred %}DEFAULT{% endblock %}</head><body>{{ assets.add('/x.css') }}{% block body %}b{% endblock %}</body>
        TWIG;

        $child = <<<'TWIG'
        {% if true %}
        {% extends 'parent.twig' %}
        {% block head deferred %}<links>{{ assets.render() }}</links>{% endblock %}
        {% endif %}
        TWIG;

        $env = $this->env(
            ['parent.twig' => $parent, 'child.twig' => $child],
            ['assets' => $this->assets()]
        );
        $out = $env->render('child.twig');

        self::assertSame(
            '<head><links><link rel="stylesheet" href="/x.css"></links></head><body>b</body>',
            trim($out)
        );
        self::assertStringNotContainsString('DEFAULT', $out);
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

    /**
     * An environment wired the way Twig::init() wires Grav's: string templates
     * chained in front of templates on disk, and a sandbox that GravSourcePolicy
     * switches on for editor content (`@Page:`) and, through
     * TwigEnvironment::render(), for everything that content loads.
     *
     * @param array<string,string> $strings
     * @param array<string,string> $files
     */
    private function sandboxEnv(array $strings, array $files = []): TwigEnvironment
    {
        $dir = sys_get_temp_dir() . '/grav-deferred-sandbox-' . bin2hex(random_bytes(6));
        mkdir($dir, 0777, true);
        foreach ($files as $name => $code) {
            file_put_contents($dir . '/' . $name, $code);
        }
        $this->dirs[] = $dir;

        $env = new TwigEnvironment(new ChainLoader([new ArrayLoader($strings), new FilesystemLoader($dir)]), [
            'cache' => false,
            'autoescape' => false,
        ]);
        $env->addExtension(new DeferredExtension());
        $policy = new SecurityPolicy(['apply', 'block', 'embed', 'macro', 'set'], ['upper'], [], [], ['block']);
        $env->addExtension(new SandboxExtension($policy, false, new GravSourcePolicy()));

        return $env;
    }

    /**
     * Renders twice, as saving a page does, and fails when a render leaves an
     * output buffer open: what is in it was left out of the returned string.
     */
    private function renderBalanced(Environment $env, string $name): string
    {
        $level = ob_get_level();
        try {
            $first = $env->render($name);
            self::assertSame($level, ob_get_level(), 'The render left an output buffer open');
            self::assertSame($first, $env->render($name));
            self::assertSame($level, ob_get_level(), 'The second render left an output buffer open');
        } finally {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
        }

        return $first;
    }

    public static function capturedDeferredBlockProvider(): array
    {
        return [
            'set' => ['<i>{% set a %}{% block a deferred %}A{% endblock %}{% endset %}</i>{{ a }}', '<i></i>A'],
            'two sets' => ['<i>{% set a %}{% block a deferred %}A{% endblock %}{% endset %}{% set b %}{% block b deferred %}B{% endblock %}{% endset %}</i>{{ a }}{{ b }}', '<i></i>AB'],
            'apply' => ['<i>{% apply upper %}{% block a deferred %}a{% endblock %}{% endapply %}</i>', '<i>A</i>'],
            'macro' => ['<i>{% macro m() %}{% block a deferred %}A{% endblock %}{% endmacro %}{{ _self.m() }}</i>', '<i>A</i>'],
            'block()' => ['<i>{% block a deferred %}A{% endblock %}{% set b = block("a") %}</i>{{ b }}', '<i>A</i>A'],
        ];
    }

    /**
     * A capture closes the output buffer a deferred block opened inside it in
     * place of its own, and the render then returns only what follows the
     * capture: the rest is still in a buffer that reaches the client unseen by
     * the save-time scan of rendered content. Editor content has no deferred
     * blocks, so its output is all in the string the render returns.
     *
     * @dataProvider capturedDeferredBlockProvider
     */
    public function testDeferredBlockInSandboxedContentIsAPlainBlock(string $content, string $expected): void
    {
        $env = $this->sandboxEnv(['@Page:/p' => $content]);

        self::assertSame($expected, $this->renderBalanced($env, '@Page:/p'));
    }

    public function testDeferredBlockOfADiskTemplateIsAPlainBlockInsideASandboxedRender(): void
    {
        // The theme's block is deferred, and content reaches it inside a capture
        // of its own through block().
        $files = ['slots.html.twig' => '{% block first %}{% endblock %}[{% block late deferred %}L{% endblock %}]'];
        $content = '<i>{% embed "slots.html.twig" %}{% block first %}{% set a %}{{ block("late") }}{% endset %}{% endblock %}{% endembed %}</i>';
        $env = $this->sandboxEnv(['@Page:/p' => $content], $files);

        self::assertSame('<i>[L]</i>', $this->renderBalanced($env, '@Page:/p'));
    }

    public function testDeferredBlockOfATrustedTemplateStillDefersNextToTheSandbox(): void
    {
        $files = ['base.html.twig' => '<head>{% block head deferred %}{{ assets.render() }}{% endblock %}</head>{{ assets.add("/a.css") }}'];
        $env = $this->sandboxEnv([], $files);
        $env->addGlobal('assets', $this->assets());

        self::assertSame('<head><link rel="stylesheet" href="/a.css"></head>', $env->render('base.html.twig'));
    }

    public function testDeferredBufferClosedByAnErrorIsForgottenOnEveryRender(): void
    {
        // An error closes the buffer of a deferred block before the template
        // resolves it. The block has to be forgotten every time that happens, or
        // a later render of the template closes a buffer that is not its own.
        $boom = new class {
            public bool $armed = true;
            public function now(): string
            {
                if ($this->armed) {
                    throw new \RuntimeException('boom');
                }

                return '';
            }
        };
        $env = $this->env(['t.twig' => '[{% block a deferred %}A{% endblock %}]{{ boom.now() }}'], ['boom' => $boom]);

        $level = ob_get_level();
        ob_start();
        try {
            for ($i = 0; $i < 3; $i++) {
                try {
                    $env->render('t.twig');
                    self::fail('The render should have thrown');
                } catch (\Twig\Error\RuntimeError $e) {
                }
            }

            $boom->armed = false;
            self::assertSame('[A]', $env->render('t.twig'));
            self::assertSame($level + 1, ob_get_level(), 'The render closed an output buffer that was not its own');
        } finally {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
        }
    }
}
