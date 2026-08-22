<?php

use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * Regression coverage for getgrav/grav#4256.
 *
 * Twig's own parser refuses a `block` definition nested under an `if`, so the
 * getgrav/Twig fork carries a patch that makes `if` transparent when a child
 * template's body is cleaned up. Grav themes and plugins rely on it: the form
 * plugin's forms/default/field.html.twig wraps its whole body — the `extends`
 * tag included — in a single `{% if not field.validate.ignore %}`.
 *
 * Upstream 3.27 replaced the recursive Parser::filterBodyNodes() with the flat
 * Parser::cleanupBodyForChildTemplates(), and re-homing the fork patch onto it
 * lost the case where that `if` IS the body rather than one node inside it. The
 * block definitions were then rendered in place as well as through the parent,
 * so every form field printed its attributes as text above the field.
 *
 * These tests pin the parser behaviour the fork has to keep providing. They are
 * deliberately free of Grav bootstrapping so they stay a reliable canary for the
 * vendored Twig itself.
 */
class TwigConditionalBlockTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @param array<string, string> $templates
     * @return string
     */
    protected function render(array $templates, string $name = 'child.twig'): string
    {
        $twig = new Environment(new ArrayLoader($templates), [
            'autoescape' => 'html',
            'cache' => false,
            'debug' => true,
        ]);

        return $twig->render($name);
    }

    /**
     * The whole child body is one `if`, with `extends` inside it. This is the
     * shape the form plugin uses, and the one #4256 broke.
     */
    public function testBlocksUnderAnIfWrappingTheWholeBodyAreNotRenderedInPlace()
    {
        $output = $this->render([
            'layout.twig' => '<div {% block outer %}{% endblock %}><input {% block inner %}{% endblock %} /></div>',
            'child.twig' => <<<TWIG
{% if true %}
{% extends "layout.twig" %}
{% block outer %}data-a="1"{% endblock %}
{% block inner %}class="c"{% endblock %}
{% endif %}
TWIG,
        ]);

        $this->assertSame('<div data-a="1"><input class="c" /></div>', trim($output));
    }

    /**
     * The same shape one level deeper: a grandchild overriding a block and
     * calling parent(), as forms/fields/text/text.html.twig does.
     */
    public function testParentResolvesThroughAConditionalChildTemplate()
    {
        $output = $this->render([
            'layout.twig' => '<input {% block attrs %}{% endblock %} />',
            'middle.twig' => <<<TWIG
{% if true %}
{% extends "layout.twig" %}
{% block attrs %}class="c"{% endblock %}
{% endif %}
TWIG,
            'child.twig' => <<<TWIG
{% extends "middle.twig" %}
{% block attrs %}type="text" {{ parent() }}{% endblock %}
TWIG,
        ]);

        $this->assertSame('<input type="text" class="c" />', trim($output));
    }

    /**
     * The condition only decides whether the definition also renders in place;
     * the override itself is registered at compile time either way. A false
     * condition must therefore still override the parent, and still not leak.
     */
    public function testAFalseConditionStillRegistersTheOverrideWithoutRenderingInPlace()
    {
        $output = $this->render([
            'layout.twig' => '[{% block content %}DEFAULT{% endblock %}]',
            'child.twig' => <<<TWIG
{% extends "layout.twig" %}
{% if false %}
{% block content %}OVERRIDE{% endblock %}
{% endif %}
TWIG,
        ]);

        $this->assertSame('[OVERRIDE]', trim($output));
    }

    /**
     * The pre-existing case: the `if` is one node among others at the root of
     * the child. Guards against a fix for the above regressing this direction.
     */
    public function testBlocksUnderAnIfAlongsideOtherRootNodesAreNotRenderedInPlace()
    {
        $output = $this->render([
            'layout.twig' => '[{% block first %}{% endblock %}][{% block second %}{% endblock %}]',
            'child.twig' => <<<TWIG
{% extends "layout.twig" %}
{% block first %}FOO{% endblock %}
{% if true %}
{% block second %}BAR{% endblock %}
{% endif %}
TWIG,
        ]);

        $this->assertSame('[FOO][BAR]', trim($output));
    }
}
