<?php

use Codeception\Util\Fixtures;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use Grav\Common\Page\Page;
use Grav\Common\Twig\Sandbox\GravSourcePolicy;
use Twig\Extension\SandboxExtension;
use Twig\Sandbox\SecurityPolicy;
use Twig\Sandbox\SourcePolicyInterface;
use Twig\Source;

/**
 * Grav's own Twig wiring must not trigger Twig deprecations on every request
 * (getgrav/grav#4387). Twig reports them through `@trigger_error()`, which the
 * test runner ignores, so each test collects them with its own error handler.
 */
class TwigDeprecationsTest extends \PHPUnit\Framework\TestCase
{
    /** @var Grav */
    protected $grav;

    /** @var string */
    protected $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $this->grav = $grav();
        $this->grav['locator']->addPath('theme', '', 'tests/fake/twig-first-site/user/themes/testing', false);
        $this->dir = sys_get_temp_dir() . '/grav-twig-deprecations-' . uniqid('', true);
    }

    protected function tearDown(): void
    {
        Folder::delete($this->dir);
        parent::tearDown();
    }

    /**
     * @return list<string> the Twig deprecations triggered while $fn ran
     */
    private function twigDeprecations(callable $fn): array
    {
        $found = [];
        set_error_handler(static function (int $no, string $message) use (&$found): bool {
            if (str_starts_with($message, 'Since twig/twig')) {
                $found[] = $message;
            }

            return true;
        }, E_USER_DEPRECATED);
        try {
            $fn();
        } finally {
            restore_error_handler();
        }

        return $found;
    }

    /**
     * GravSourcePolicy implements the getgrav/Twig fork's
     * CompileTimeSourcePolicyInterface, which the fork supports. Only the plain
     * upstream SourcePolicyInterface is deprecated.
     */
    public function testSandboxExtensionWithGravSourcePolicyIsNotDeprecated(): void
    {
        self::assertSame([], $this->twigDeprecations(static function (): void {
            new SandboxExtension(new SecurityPolicy(), false, new GravSourcePolicy());
        }));
    }

    /**
     * The fork patch stays narrow: any other source policy still gets the
     * upstream deprecation.
     */
    public function testSandboxExtensionWithPlainSourcePolicyIsStillDeprecated(): void
    {
        $policy = new class implements SourcePolicyInterface {
            public function enableSandbox(Source $source): bool
            {
                return true;
            }
        };

        $found = $this->twigDeprecations(static function () use ($policy): void {
            new SandboxExtension(new SecurityPolicy(), false, $policy);
        });

        self::assertCount(1, $found);
        self::assertStringContainsString('SourcePolicyInterface', $found[0]);
    }

    public function testTwigInitTriggersNoTwigDeprecation(): void
    {
        $this->grav['config']->set('security.twig_sandbox.enabled', true);

        self::assertSame([], $this->twigDeprecations(function (): void {
            $this->grav['twig']->init();
        }));
    }

    /**
     * @dataProvider pageProvider
     */
    public function testProcessPageTriggersNoTwigDeprecation(string $folder): void
    {
        $twig = $this->grav['twig'];
        $twig->init();

        // A module also renders its modular template, on the same environment.
        Folder::create($this->dir . '/templates/modular');
        file_put_contents($this->dir . '/templates/modular/default.html.twig', '<section>{{ content|raw }}</section>');
        $twig->prependPath($this->dir . '/templates');

        Folder::create($this->dir . '/' . $folder);
        $file = $this->dir . '/' . $folder . '/default.md';
        file_put_contents($file, "---\ntitle: Notes\nprocess:\n    twig: true\n---\n{{ page.title|upper }}\n");
        $page = new Page();
        $page->init(new SplFileInfo($file));

        $output = '';
        $found = $this->twigDeprecations(static function () use ($twig, $page, &$output): void {
            $output = $twig->processPage($page, $page->rawMarkdown());
        });

        self::assertStringContainsString('NOTES', $output);
        self::assertSame([], $found);
    }

    public static function pageProvider(): array
    {
        return [
            'regular page' => ['notes'],
            'module' => ['_notes'],
        ];
    }
}
