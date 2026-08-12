<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Common\Twig\Twig;
use Twig\Extension\EscaperExtension;

/**
 * Regression coverage for getgrav/grav#4235.
 *
 * With `system.strict_mode.twig2_compat: true`, Twig::init() used to hand the
 * raw value of `system.twig.autoescape` to the Twig Environment. The stock
 * config default is the legacy boolean `true`, but Twig 3 only accepts a
 * strategy name, `false`, or a callable — anything else is treated as a
 * callable, so the first template COMPILE fataled with
 * "call_user_func(): Argument #1 ($callback) must be a valid callback".
 *
 * The failure was masked by the compiled-template cache: sites ran fine until
 * an upgrade cleared cache://twig, then every page died. These tests pin the
 * normalization for every autoescape shape a config can produce.
 */
class TwigAutoescapeCompatTest extends \PHPUnit\Framework\TestCase
{
    /** @var Grav */
    protected $grav;

    /** @var array<string, mixed> */
    protected $previous = [];

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $this->grav = $grav();

        // Twig::init() resolves theme:// template paths; the fixture Grav has
        // no theme stream, so point one at any existing directory.
        if (!$this->grav['locator']->schemeExists('theme')) {
            $this->grav['locator']->addPath('theme', '', 'tests/fake/simple-site', false);
        }

        foreach (['system.strict_mode.twig2_compat', 'system.twig.autoescape'] as $key) {
            $this->previous[$key] = $this->grav['config']->get($key);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->previous as $key => $value) {
            $this->grav['config']->set($key, $value);
        }
        parent::tearDown();
    }

    /**
     * Boot a fresh Twig service under the given config and return the default
     * escaping strategy its environment resolves for a template name.
     *
     * @param bool $compat
     * @param mixed $autoescape
     * @return string|false
     */
    protected function strategyFor(bool $compat, $autoescape)
    {
        $this->grav['config']->set('system.strict_mode.twig2_compat', $compat);
        $this->grav['config']->set('system.twig.autoescape', $autoescape);

        $twig = new Twig($this->grav);
        // The deprecation for autoescape-off compat mode is expected noise here.
        $errorReporting = error_reporting(E_ALL & ~E_USER_DEPRECATED);
        try {
            $twig->init();
        } finally {
            error_reporting($errorReporting);
        }

        return $twig->twig()->getExtension(EscaperExtension::class)->getDefaultStrategy('page.html.twig');
    }

    public function testInit_Issue4235_BooleanTrueAutoescapeIsNormalizedInCompatMode(): void
    {
        self::assertSame('html', $this->strategyFor(true, true));
    }

    public function testInit_Issue4235_CompatModeCompilesTemplatesWithBooleanAutoescape(): void
    {
        $this->grav['config']->set('system.strict_mode.twig2_compat', true);
        $this->grav['config']->set('system.twig.autoescape', true);

        $twig = new Twig($this->grav);
        $twig->init();

        // Compiling runs EscaperNodeVisitor, the exact spot that fataled. The
        // unique marker defeats the compiled-template cache (which persists in
        // the fixture site between runs and is exactly what masked this bug on
        // live sites) so the template is compiled fresh every time.
        $template = '{{ payload }}{# ' . uniqid('4235-', true) . ' #}';
        $html = $twig->twig()->createTemplate($template)->render(['payload' => '<b>x</b>']);
        self::assertSame('&lt;b&gt;x&lt;/b&gt;', $html);
    }

    public function testInit_Issue4235_FalseAutoescapeStaysOffInCompatMode(): void
    {
        self::assertFalse($this->strategyFor(true, false));
    }

    public function testInit_Issue4235_StringStrategyPassesThroughInCompatMode(): void
    {
        self::assertSame('js', $this->strategyFor(true, 'js'));
    }

    public function testInit_Issue4235_StrictModeForcesHtmlRegardlessOfConfig(): void
    {
        self::assertSame('html', $this->strategyFor(false, true));
    }
}
