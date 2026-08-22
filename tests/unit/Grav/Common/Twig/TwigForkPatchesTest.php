<?php

use Grav\Common\Twig\TwigEnvironment;
use Twig\Environment;
use Twig\Error\SyntaxError;
use Twig\Extension\EscaperExtension;
use Twig\Loader\ArrayLoader;

/**
 * Canaries for every patch Grav carries in the bundled getgrav/Twig fork.
 *
 * The fork tracks upstream twigphp/Twig closely and is re-synced wholesale, so
 * a patch can be dropped or re-homed onto rewritten upstream code without
 * anyone noticing until a site breaks — which is how getgrav/grav#4256 shipped.
 * Every divergence in the fork's src/ gets a test here, and the set of tests
 * should be re-checked against `git diff upstream/3.x -- src/` after each sync.
 *
 * The current divergences are:
 *
 * 1. Twig\Extension\EscaperExtension is not final, so Grav's TwigEnvironment
 *    can return a subclass that keeps the pre-3.9 setEscaper() call site
 *    working.
 * 2. CorrectnessNodeVisitor treats `if` as a transparent tag, so a `block`
 *    definition is allowed to sit under one.
 * 3. Parser strips those nested block references from a child template's body,
 *    so the definition does not also render where it was declared.
 *
 * The behaviour that hangs off (2) and (3) is covered in depth by
 * TwigConditionalBlockTest and DeferredExtensionTest; what is pinned here is
 * that the patches exist at all, and that they stay as narrow as intended.
 */
class TwigForkPatchesTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @param array<string, string> $templates
     */
    protected function env(array $templates): Environment
    {
        return new Environment(new ArrayLoader($templates), [
            'autoescape' => 'html',
            'cache' => false,
        ]);
    }

    /**
     * Divergence 1. Upstream marked EscaperExtension final in 3.10.
     * TwigEnvironment::getExtension() checks isFinal() and quietly stops
     * shimming when it is, so a lost patch does not fatal — it silently breaks
     * every caller that still does getExtension(EscaperExtension::class)
     * ->setEscaper(). Assert the patch directly rather than its symptom.
     */
    public function testEscaperExtensionIsNotFinal(): void
    {
        $reflection = new ReflectionClass(EscaperExtension::class);

        self::assertFalse(
            $reflection->isFinal(),
            'The getgrav/Twig fork must keep EscaperExtension non-final; see TwigEnvironment::getExtension().'
        );
    }

    /**
     * Divergence 1, end to end: the shim TwigEnvironment hands back must
     * register a working escaper and still answer getDefaultStrategy().
     */
    public function testEscaperExtensionShimRegistersAWorkingEscaper(): void
    {
        $env = new TwigEnvironment(
            new ArrayLoader(['t.twig' => "{{ v|e('grav_fork_test')|raw }}"]),
            ['autoescape' => 'html', 'cache' => false]
        );

        $extension = $env->getExtension(EscaperExtension::class);
        $extension->setEscaper('grav_fork_test', fn ($string, $charset) => '<<' . $string . '>>');

        self::assertSame('<<x>>', $env->render('t.twig', ['v' => 'x']));
        // The shim does not call parent::__construct(), so anything it forwards
        // has to be forwarded explicitly. This is the one Grav relies on.
        self::assertSame('html', $extension->getDefaultStrategy('page.html.twig'));
    }

    /**
     * Divergence 2. Without the transparent-tag patch this is a SyntaxError at
     * parse time: "A block definition cannot be nested under non-capturing
     * nodes."
     */
    public function testBlockDefinitionUnderAnIfIsAccepted(): void
    {
        $output = $this->env([
            'layout.twig' => '[{% block content %}{% endblock %}]',
            'child.twig' => <<<TWIG
{% extends "layout.twig" %}
{% if true %}
{% block content %}FOO{% endblock %}
{% endif %}
TWIG,
        ])->render('child.twig');

        self::assertSame('[FOO]', trim($output));
    }

    /**
     * Divergence 2 must stay narrow: only `if` is transparent. A block under
     * any other wrapping tag is still a parse error, because nesting it there
     * genuinely does not work.
     */
    public function testBlockDefinitionUnderAForIsStillRejected(): void
    {
        $this->expectException(SyntaxError::class);
        $this->expectExceptionMessage('A "block" tag cannot be under a "for" tag');

        $this->env([
            'layout.twig' => '[{% block content %}{% endblock %}]',
            'child.twig' => <<<TWIG
{% extends "layout.twig" %}
{% for i in 1..2 %}
{% block content %}FOO{% endblock %}
{% endfor %}
TWIG,
        ])->render('child.twig');
    }

    /**
     * Divergence 3 must stay narrow the other way: a capturing tag such as
     * `set` is left alone, where a block legitimately does render in place.
     * The form plugin's field template builds its outer classes exactly like
     * this, so stripping the reference here would empty them out.
     */
    public function testBlockDefinitionInsideASetCaptureStillRendersInPlace(): void
    {
        $output = $this->env([
            'layout.twig' => '[{% block content %}{% endblock %}]',
            'child.twig' => <<<TWIG
{% extends "layout.twig" %}
{% set captured %}{% block content %}FOO{% endblock %}{% endset %}
TWIG,
        ])->render('child.twig');

        self::assertSame('[FOO]', trim($output));
    }
}
