<?php

use Grav\Common\Twig\Compatibility\Twig3CompatibilityTransformer;

class Twig3CompatibilityTransformerTest extends \PHPUnit\Framework\TestCase
{
    /** @var Twig3CompatibilityTransformer $transformer */
    protected $transformer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->transformer = new Twig3CompatibilityTransformer();
    }

    public function testRewriteSameAsTests(): void
    {
        $this->assertSame(
            '{{ foo is same as(bar) }}',
            $this->transformer->transform('{{ foo is sameas(bar) }}')
        );

        $this->assertSame(
            '{{ foo is same as (bar) }}',
            $this->transformer->transform('{{ foo is sameas (bar) }}')
        );

        $this->assertSame(
            '{% if foo is same as bar %}',
            $this->transformer->transform('{% if foo is sameas bar %}')
        );

        $this->assertSame(
            '{% if foo is not same as bar %}',
            $this->transformer->transform('{% if foo is not sameas bar %}')
        );

        $this->assertSame(
            "'foo is sameas(bar)'",
            $this->transformer->transform("'foo is sameas(bar)'")
        );

        $this->assertSame(
            "{{ 'foo is sameas(bar)' }}",
            $this->transformer->transform("{{ 'foo is sameas(bar)' }}")
        );
    }

    public function testRewriteForLoopGuards(): void
    {
        $this->assertSame(
            '{% for item in (items)|filter(item => item.foo) %}',
            $this->transformer->transform('{% for item in items if item.foo %}')
        );

        $this->assertSame(
            '{% for key, value in (items)|filter((value, key) => value.foo) %}',
            $this->transformer->transform('{% for key, value in items if value.foo %}')
        );

        $this->assertSame(
            '{% for item in items %}',
            $this->transformer->transform('{% for item in items %}')
        );

        $this->assertSame(
            '{% for item in (items if item.foo)|filter(item => item.bar) %}',
            $this->transformer->transform('{% for item in items if item.foo if item.bar %}')
        );
    }

    public function testRewriteSpacelessBlocks(): void
    {
        $this->assertSame(
            '{% apply spaceless %}{% endapply %}',
            $this->transformer->transform('{% spaceless %}{% endspaceless %}')
        );

        $this->assertSame(
            '{%- apply spaceless -%}{%- endapply -%}',
            $this->transformer->transform('{%- spaceless -%}{%- endspaceless -%}')
        );
    }

    public function testRewriteFilterBlocks(): void
    {
        $this->assertSame(
            '{% apply lower %}{% endapply %}',
            $this->transformer->transform('{% filter lower %}{% endfilter %}')
        );

        $this->assertSame(
            '{%- apply upper|escape -%}{%- endapply -%}',
            $this->transformer->transform('{%- filter upper|escape -%}{%- endfilter -%}')
        );
    }

    public function testRewriteReplaceFilterSignatures(): void
    {
        $this->assertSame(
            "{{ 'hello world'|replace({'hello': 'goodbye'}) }}",
            $this->transformer->transform("{{ 'hello world'|replace('hello', 'goodbye') }}")
        );

        $this->assertSame(
            '{{ "hello world"|replace({"hello": "goodbye"}) }}',
            $this->transformer->transform('{{ "hello world"|replace("hello", "goodbye") }}')
        );
    }

    public function testRewriteRawBlocks(): void
    {
        $this->assertSame(
            '{% verbatim %}foo{% endverbatim %}',
            $this->transformer->transform('{% raw %}foo{% endraw %}')
        );

        $this->assertSame(
            '{%- verbatim -%}foo{%- endverbatim -%}',
            $this->transformer->transform('{%- raw -%}foo{%- endraw -%}')
        );
    }

    public function testRewriteDivisibleBy(): void
    {
        $this->assertSame(
            '{% if loop.index is divisible by(3) %}',
            $this->transformer->transform('{% if loop.index is divisibleby(3) %}')
        );

        $this->assertSame(
            '{% if loop.index is divisible by (3) %}',
            $this->transformer->transform('{% if loop.index is divisibleby (3) %}')
        );

        $this->assertSame(
            '{% if loop.index is not divisible by(3) %}',
            $this->transformer->transform('{% if loop.index is not divisibleby(3) %}')
        );
    }

    public function testRewriteNoneTest(): void
    {
        $this->assertSame(
            '{% if var is null %}',
            $this->transformer->transform('{% if var is none %}')
        );

        $this->assertSame(
            '{% if var is not null %}',
            $this->transformer->transform('{% if var is not none %}')
        );
    }
}