<?php

use Grav\Common\Twig\TwigEnvironment;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Loader\ArrayLoader;
use Twig\Loader\LoaderInterface;
use Twig\Source;
use Twig\Template;
use Twig\TemplateWrapper;

/**
 * TwigEnvironment::resolveTemplate() is what include(), {% include %},
 * {% extends %} with a list and friends call to turn a name, a list of names or
 * an already loaded template into something renderable.
 *
 * Grav overrides it only to put a single name into a list before handing it to
 * upstream, so a lone Template is ownership-checked and wrapped instead of
 * failing on upstream's TemplateWrapper return type. Everything else, including
 * rejecting a Template or TemplateWrapper created by another environment, must
 * behave exactly as upstream Twig does.
 */
class TwigResolveTemplateTest extends \PHPUnit\Framework\TestCase
{
    /** @var string[] */
    private $deprecations = [];

    /**
     * @param array<string, string> $templates
     */
    private function grav(array $templates = ['a' => 'A', 'b' => 'B'], ?LoaderInterface $loader = null): TwigEnvironment
    {
        return new TwigEnvironment($loader ?? new ArrayLoader($templates), ['cache' => false, 'autoescape' => 'html']);
    }

    /**
     * @param array<string, string> $templates
     */
    private function foreign(array $templates = ['a' => 'foreign A']): Environment
    {
        return new Environment(new ArrayLoader($templates), ['cache' => false]);
    }

    /**
     * Runs $fn with Twig's "Passing a Template is deprecated" notices captured,
     * so passing a legacy Template does not trip the test runner.
     *
     * @return mixed
     */
    private function capturingDeprecations(callable $fn)
    {
        set_error_handler(function (int $errno, string $message): bool {
            $this->deprecations[] = $message;

            return true;
        }, E_USER_DEPRECATED);

        try {
            return $fn();
        } finally {
            restore_error_handler();
        }
    }

    private function expectForeignRejected(string $class): void
    {
        $this->expectException(RuntimeError::class);
        $this->expectExceptionMessage(sprintf('A "%s" can only be used with the "%s" that created it.', $class, Environment::class));
    }

    // ------------------------------------------------------------------
    // Templates from the same environment
    // ------------------------------------------------------------------

    public function testSameEnvironmentWrapperIsReturnedAsIs(): void
    {
        $env = $this->grav();
        $wrapper = $env->load('a');

        self::assertSame($wrapper, $env->resolveTemplate($wrapper));
        self::assertSame($wrapper, $env->resolveTemplate([$wrapper]));
        self::assertSame($wrapper, $env->resolveTemplate(['missing.twig', $wrapper]));
    }

    public function testSameEnvironmentTemplateComesBackWrapped(): void
    {
        $env = $this->grav();
        $template = $env->load('a')->unwrap($env);

        foreach ([$template, [$template], ['missing.twig', $template]] as $names) {
            $resolved = $this->capturingDeprecations(static function () use ($env, $names) {
                return $env->resolveTemplate($names);
            });

            self::assertInstanceOf(TemplateWrapper::class, $resolved);
            self::assertSame($template, $resolved->unwrap($env));
            self::assertSame('A', $resolved->render());
        }

        // The same deprecation upstream raises for a legacy Template.
        self::assertContains(
            sprintf('Since twig/twig 3.9: Passing a "%s" instance to "%s::resolveTemplate" is deprecated.', Template::class, Environment::class),
            $this->deprecations
        );
    }

    /**
     * Upstream Twig alone fails a lone Template with a TypeError, because its
     * load() hands the bare Template back as a TemplateWrapper. This is the
     * reason Grav keeps the override; if upstream fixes it, the override can go.
     */
    public function testUpstreamStillFailsOnALoneTemplate(): void
    {
        $env = $this->foreign(['a' => 'A']);
        $template = $env->load('a')->unwrap($env);

        $this->expectException(\TypeError::class);
        $this->capturingDeprecations(static function () use ($env, $template) {
            return $env->resolveTemplate($template);
        });
    }

    // ------------------------------------------------------------------
    // Templates from another environment
    // ------------------------------------------------------------------

    public function testForeignWrapperIsRejected(): void
    {
        $foreign = $this->foreign();
        $this->expectForeignRejected(TemplateWrapper::class);

        $this->grav()->resolveTemplate($foreign->load('a'));
    }

    public function testForeignWrapperInAListIsRejected(): void
    {
        $foreign = $this->foreign();
        $this->expectForeignRejected(TemplateWrapper::class);

        $this->grav()->resolveTemplate(['missing.twig', $foreign->load('a')]);
    }

    public function testForeignTemplateIsRejected(): void
    {
        $foreign = $this->foreign();
        $template = $foreign->load('a')->unwrap($foreign);
        $this->expectForeignRejected(Template::class);

        $env = $this->grav();
        $this->capturingDeprecations(static function () use ($env, $template) {
            return $env->resolveTemplate($template);
        });
    }

    public function testForeignTemplateInAListIsRejected(): void
    {
        $foreign = $this->foreign();
        $template = $foreign->load('a')->unwrap($foreign);
        $this->expectForeignRejected(Template::class);

        $env = $this->grav();
        $this->capturingDeprecations(static function () use ($env, $template) {
            return $env->resolveTemplate(['missing.twig', $template]);
        });
    }

    /**
     * A second TwigEnvironment is just as foreign as a plain Twig one.
     */
    public function testWrapperFromAnotherGravEnvironmentIsRejected(): void
    {
        $other = $this->grav();
        $this->expectForeignRejected(TemplateWrapper::class);

        $this->grav()->resolveTemplate($other->load('a'));
    }

    public function testForeignWrapperIsRejectedByInclude(): void
    {
        $env = $this->grav(['page' => '{{ include(tpl) }}']);
        $foreign = $this->foreign();
        $this->expectException(RuntimeError::class);
        // Twig appends the template and line: ... that created it in "page" at line 1.
        $this->expectExceptionMessage(sprintf('A "%s" can only be used with the "%s" that created it in "page"', TemplateWrapper::class, Environment::class));

        $env->render('page', ['tpl' => $foreign->load('a')]);
    }

    public function testSameEnvironmentWrapperWorksWithInclude(): void
    {
        $env = $this->grav(['page' => '[{{ include(tpl) }}]', 'a' => 'A']);

        self::assertSame('[A]', $env->render('page', ['tpl' => $env->load('a')]));
    }

    // ------------------------------------------------------------------
    // Names
    // ------------------------------------------------------------------

    public function testStringNameResolves(): void
    {
        $env = $this->grav();
        $resolved = $env->resolveTemplate('a');

        self::assertInstanceOf(TemplateWrapper::class, $resolved);
        self::assertSame('A', $resolved->render());
        self::assertSame($env->load('a'), $resolved);
    }

    public function testMissingStringNameThrowsTheLoaderError(): void
    {
        $this->expectException(LoaderError::class);
        $this->expectExceptionMessage('Template "missing.twig" is not defined.');

        $this->grav()->resolveTemplate('missing.twig');
    }

    public function testArrayOfNamesResolvesTheFirstThatExists(): void
    {
        $env = $this->grav();

        self::assertSame('B', $env->resolveTemplate(['missing.twig', 'b', 'a'])->render());
        self::assertSame('A', $env->resolveTemplate(['a', 'b'])->render());
        self::assertSame('A', $env->resolveTemplate(['a'])->render());
    }

    public function testArrayWithNoExistingNameThrows(): void
    {
        $this->expectException(LoaderError::class);
        $this->expectExceptionMessage('Unable to find one of the following templates: "one.twig", "two.twig".');

        $this->grav()->resolveTemplate(['one.twig', 'two.twig']);
    }

    public function testSingleMissingNameInAnArrayThrowsTheLoaderError(): void
    {
        $this->expectException(LoaderError::class);
        $this->expectExceptionMessage('Template "missing.twig" is not defined.');

        $this->grav()->resolveTemplate(['missing.twig']);
    }

    /**
     * Grav's original reason for the override: in a list, a missing name is
     * checked with exists() and skipped, never loaded and caught, because a
     * thrown LoaderError per missing theme or plugin template was very slow.
     */
    public function testMissingNamesInAListAreSkippedWithoutLoading(): void
    {
        $loader = new class(new ArrayLoader(['found.twig' => 'found'])) implements LoaderInterface {
            /** @var ArrayLoader */
            private $inner;
            /** @var string[] */
            public $loaded = [];

            public function __construct(ArrayLoader $inner)
            {
                $this->inner = $inner;
            }

            public function getSourceContext(string $name): Source
            {
                $this->loaded[] = $name;

                return $this->inner->getSourceContext($name);
            }

            public function getCacheKey(string $name): string
            {
                return $this->inner->getCacheKey($name);
            }

            public function isFresh(string $name, int $time): bool
            {
                return $this->inner->isFresh($name, $time);
            }

            public function exists(string $name): bool
            {
                return $this->inner->exists($name);
            }
        };

        $env = $this->grav([], $loader);

        self::assertSame('found', $env->resolveTemplate(['a.twig', 'b.twig', 'found.twig'])->render());
        self::assertSame(['found.twig'], $loader->loaded);
    }

    public function testIncludeWithAListOfNamesStillWorks(): void
    {
        $env = $this->grav([
            'page' => '{% include ["partials/missing.html.twig", "partials/found.html.twig"] %}|{{ include(["nope", "partials/found.html.twig"]) }}',
            'partials/found.html.twig' => 'found',
        ]);

        self::assertSame('found|found', $env->render('page'));
    }

    public function testIncludeIgnoreMissingWithAListStillWorks(): void
    {
        $env = $this->grav(['page' => '[{% include ["one", "two"] ignore missing %}]']);

        self::assertSame('[]', $env->render('page'));
    }
}
