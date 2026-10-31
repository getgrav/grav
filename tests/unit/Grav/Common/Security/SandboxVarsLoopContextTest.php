<?php

use Codeception\Util\Fixtures;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;

/**
 * Class SandboxVarsLoopContextTest
 *
 * evaluate_twig() and evaluate() forward the calling template's context to the
 * sandboxed render. Called from inside a `{% for %}` loop, that context carries
 * `_parent` and `loop.parent`, Twig's own copies of the enclosing context. They
 * hold the raw Config and the unfiltered `system`/`site`/`theme` arrays, so they
 * have to be filtered like the context itself.
 */
class SandboxVarsLoopContextTest extends \PHPUnit\Framework\TestCase
{
    /** @var Grav */
    protected $grav;

    /** @var string */
    protected $dir;

    /** @var array */
    protected $previousVars;

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $this->grav = $grav();
        $this->grav['locator']->addPath('theme', '', 'tests/fake/twig-first-site/user/themes/testing', false);
        $this->grav['config']->set('system.cache.redis.password', 'REDIS_SECRET_7');

        // The base variables are captured once, by the first init() of the run.
        $twig = $this->grav['twig']->init();
        $this->previousVars = $twig->twig_vars;
        $twig->twig_vars['system'] = $this->grav['config']->get('system');

        // What a theme or plugin does with editor-authored strings (the Form
        // plugin's field loop, for one), here two loops deep.
        $this->dir = sys_get_temp_dir() . '/grav-sandbox-loop-context-' . uniqid('', true);
        Folder::create($this->dir);
        file_put_contents(
            $this->dir . '/sandbox-loop-context.html.twig',
            '{{ system.cache.redis.password }}'
            . '{% for group in groups %}{{ loop.index }}'
            . '{% for field in group %}{{ loop.first ? ":" }}[{{ evaluate_twig(field) }}]{% endfor %}'
            . '{% endfor %}'
        );
        $twig->addPath($this->dir);
    }

    protected function tearDown(): void
    {
        Folder::delete($this->dir);
        $this->grav['twig']->twig_vars = $this->previousVars;
        $this->grav['config']->set('system.cache.redis.password', null);
        parent::tearDown();
    }

    protected function render(array $fields): string
    {
        return $this->grav['twig']->processTemplate('sandbox-loop-context.html.twig', ['groups' => [$fields]]);
    }

    public function testDeniedPathsAreFilteredFromTheLoopCopiesOfTheContext(): void
    {
        $output = $this->render([
            '{{ system.cache.redis.password }}',
            '{{ _parent.system.cache.redis.password }}',
            '{{ _parent._parent.system.cache.redis.password }}',
            '{{ loop.parent.system.cache.redis.password }}',
            '{{ loop.parent.loop.parent.system.cache.redis.password }}',
            '{{ _parent.loop.parent.system.cache.redis.password }}',
            '{{ loop.parent._parent.system.cache.redis.password }}',
        ]);

        // The trusted template prints the secret itself: the fixture holds one.
        self::assertSame('REDIS_SECRET_71:[][][][][][][]', $output);
    }

    public function testRawConfigIsReplacedInTheLoopCopiesOfTheContext(): void
    {
        $isRaw = "of_type(%s, 'class', 'Grav\\\\Common\\\\Config\\\\Config') ? 'raw' : 'facade'";

        $output = $this->render([
            '{{ ' . sprintf($isRaw, '_parent.config') . ' }}',
            '{{ ' . sprintf($isRaw, '_parent._parent.config') . ' }}',
            '{{ ' . sprintf($isRaw, 'loop.parent.config') . ' }}',
            '{{ ' . sprintf($isRaw, 'loop.parent.loop.parent.config') . ' }}',
        ]);

        self::assertSame('REDIS_SECRET_71:[facade][facade][facade][facade]', $output);
    }

    public function testLoopVariablesAndAllowedPathsStayReadable(): void
    {
        $output = $this->render([
            '{{ loop.index }}/{{ loop.length }}',
            '{{ loop.parent.loop.index }}',
            '{{ _parent.group|length }}',
            '{{ _parent.system.cache.driver }}',
        ]);

        self::assertSame('REDIS_SECRET_71:[1/4][1][4][auto]', $output);
    }
}
