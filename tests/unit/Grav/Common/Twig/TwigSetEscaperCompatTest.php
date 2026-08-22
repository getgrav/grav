<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Common\Twig\Twig;

/**
 * Coverage for Grav\Common\Twig\Twig::setEscaper().
 *
 * Grav has always documented the escaper callable as
 * function($twig, $string, $charset), and plugins were written against that —
 * the form plugin registers its `yaml` escaper that way. Twig 3.9 moved the
 * registry to EscaperRuntime, which invokes escapers with ($string, $charset),
 * so every one of those callables became an ArgumentCountError the first time
 * a template actually used it.
 *
 * setEscaper() now adapts the three-argument form and passes the two-argument
 * one straight through. These tests pin both, so the next Twig sync cannot
 * quietly break plugins again.
 */
class TwigSetEscaperCompatTest extends \PHPUnit\Framework\TestCase
{
    /** @var Grav */
    protected $grav;

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $this->grav = $grav();

        if (!$this->grav['locator']->schemeExists('theme')) {
            $this->grav['locator']->addPath('theme', '', 'tests/fake/simple-site', false);
        }
    }

    /**
     * Register an escaper and run it through a real template render.
     *
     * @param callable $escaper
     * @return string
     */
    protected function escapeWith(callable $escaper): string
    {
        $twig = new Twig($this->grav);
        $twig->init();

        $strategy = 'grav_test_escaper';
        $twig->setEscaper($strategy, $escaper);

        // The unique marker defeats the compiled-template cache, which persists
        // in the fixture site between runs.
        $template = "{{ payload|e('{$strategy}')|raw }}{# " . uniqid('escaper-', true) . ' #}';

        return $twig->twig()->createTemplate($template)->render(['payload' => 'x']);
    }

    public function testThreeArgumentEscaperStillReceivesTheEnvironment(): void
    {
        $seen = null;
        $output = $this->escapeWith(function ($twig, $string, $charset) use (&$seen) {
            $seen = $twig;

            return '<<' . $string . '|' . $charset . '>>';
        });

        self::assertSame('<<x|UTF-8>>', $output);
        self::assertInstanceOf(\Twig\Environment::class, $seen);
    }

    public function testTwoArgumentEscaperIsPassedThroughUnchanged(): void
    {
        $output = $this->escapeWith(fn ($string, $charset) => '[[' . $string . '|' . $charset . ']]');

        self::assertSame('[[x|UTF-8]]', $output);
    }

    /**
     * A variadic callable declares one parameter, so it must not be treated as
     * the three-argument form.
     */
    public function testVariadicEscaperIsPassedThroughUnchanged(): void
    {
        $output = $this->escapeWith(fn (...$args) => '(' . count($args) . ')');

        self::assertSame('(2)', $output);
    }
}
