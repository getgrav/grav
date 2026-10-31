<?php

/**
 * @package    Grav\Common\Twig
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Twig;

use Closure;
use Grav\Common\Security;
use Grav\Common\Twig\Sandbox\SandboxDefaults;
use ReflectionClass;
use SplStack;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Extension\CoreExtension;
use Twig\Extension\EscaperExtension;
use Twig\Extension\ExtensionInterface;
use Twig\Extension\SandboxExtension;
use Twig\Loader\LoaderInterface;
use Twig\Markup;
use Twig\Runtime\EscaperRuntime;
use Twig\Sandbox\SecurityChecker;
use Twig\Sandbox\SecurityError;
use Twig\Source;
use Twig\Template;
use Twig\TemplateWrapper;

/**
 * Class TwigEnvironment
 * @package Grav\Common\Twig
 */
class TwigEnvironment extends Environment
{
    /**
     * One entry per render in progress: for a sandboxed (editor-authored)
     * render, the variables PHP handed it, which trusted partials it includes
     * render with, and whether its output is text (a `@Var:` string, such as
     * the Form plugin's save body) rather than page or email HTML; null for a
     * trusted render. An object, so the clone Twig::processPage() renders with
     * shares it with the environment that loaded any template instance it
     * reuses.
     *
     * @var SplStack<array{vars: array, text: bool}|null>
     */
    private SplStack $renders;

    /**
     * @param LoaderInterface $loader
     * @param array $options
     */
    public function __construct(LoaderInterface $loader, array $options = [])
    {
        parent::__construct($loader, $options);

        $this->renders = new SplStack();
    }

    /**
     * @inheritDoc
     */
    public function getExtension(string $name): ExtensionInterface
    {
        $extension = parent::getExtension($name);

        // Provide setEscaper() compatibility shim for older code calling it on the extension.
        // In Twig 3.9+, setEscaper() moved to EscaperRuntime.
        // In Twig 3.10+, EscaperExtension is final and cannot be extended.
        if ($name === EscaperExtension::class && class_exists(EscaperRuntime::class)) {
            $reflection = new ReflectionClass(EscaperExtension::class);
            if (!$reflection->isFinal()) {
                return new class($extension, $this) extends EscaperExtension {
                    private $original;
                    private $env;

                    public function __construct($original, $env)
                    {
                        $this->original = $original;
                        $this->env = $env;
                    }

                    public function setEscaper($strategy, callable $callable): void
                    {
                        $this->env->getRuntime(EscaperRuntime::class)->setEscaper($strategy, $callable);
                    }

                    public function getDefaultStrategy($filename)
                    {
                        return $this->original->getDefaultStrategy($filename);
                    }
                };
            }
            // When EscaperExtension is final (Twig 3.10+), setEscaper() must be called
            // directly on the runtime: $twig->getRuntime(EscaperRuntime::class)->setEscaper(...)
        }

        return $extension;
    }

    /**
     * @inheritDoc
     *
     * Upstream already skips missing names in a list without throwing (the
     * reason Grav first overrode this method, back on Twig 1) and checks that a
     * Template or TemplateWrapper belongs to this environment. The one thing
     * left here is a single Template passed on its own: upstream sends it
     * through load(), which hands the bare Template back against its
     * TemplateWrapper return type and fails with a TypeError. Wrapping every
     * name in a list sends it through the ownership check and back as a
     * TemplateWrapper instead.
     */
    public function resolveTemplate($names): TemplateWrapper
    {
        if (!\is_array($names)) {
            $names = [$names];
        }

        return parent::resolveTemplate($names);
    }

    /**
     * @inheritDoc
     */
    public function render($name, array $context = []): string
    {
        return $this->withRenderSandbox($name, $context, fn () => parent::render($name, $context));
    }

    /**
     * @inheritDoc
     */
    public function display($name, array $context = []): void
    {
        $this->withRenderSandbox($name, $context, function () use ($name, $context): void {
            parent::display($name, $context);
        });
    }

    /**
     * Render an editor-authored template on filtered variables, giving the
     * trusted partials it includes the unfiltered ones PHP chose.
     *
     * Twig::processPage() and processString() hand content a `config` facade
     * and redacted `system`/`site`/`theme` arrays. A trusted partial (the Form
     * plugin's form template) renders the way the theme renders it, on the
     * real ones.
     *
     * @param string|TemplateWrapper $name
     * @param array $context Variables for the content itself.
     * @param array $partialContext Variables for the trusted partials it includes.
     * @return string
     */
    public function renderContent($name, array $context, array $partialContext): string
    {
        return $this->withRenderSandbox($name, $partialContext, fn () => parent::render($name, $context));
    }

    /**
     * Runs an `{% include %}` written in editor-authored content.
     *
     * ContentIncludeExtension compiles the tag in sandboxed sources to a call
     * here. A template listed under `security.twig_sandbox.trusted_partials`
     * renders as a trusted render on the variables PHP handed the content
     * render, plus plain values from the content (see trustedPartialVariables()).
     * A text partial (SandboxDefaults::textPartials(), templates that switch
     * escaping off to write literal text) is inserted as is only into a `@Var:`
     * render, and escaped in page content and email bodies, which are HTML.
     * Anything else renders with the sandbox on, like every other template
     * content loads.
     *
     * @param array $context The including template's context.
     * @param string|TemplateWrapper|array $template
     * @param mixed $variables The `with` value, or null.
     * @param bool $only
     * @param bool $ignoreMissing
     * @param Closure(string|TemplateWrapper|array): Template $load The including template's own loader.
     * @return iterable<string>
     * @internal
     */
    public function includeFromContent(array $context, $template, $variables, bool $only, bool $ignoreMissing, Closure $load): iterable
    {
        $partial = $this->findTrustedPartial($template);
        if (null !== $partial) {
            if ($ignoreMissing && !$this->getLoader()->exists($partial)) {
                return;
            }

            $output = $this->render($partial, $this->trustedPartialVariables($partial, $context, $variables, $only));
            if (\in_array($partial, SandboxDefaults::textPartials(), true) && !$this->rendersText()) {
                $output = htmlspecialchars($output, ENT_QUOTES | ENT_SUBSTITUTE, $this->getCharset());
            }

            yield $output;

            return;
        }

        if (null === $variables) {
            $vars = $only ? [] : $context;
        } else {
            $vars = $only ? CoreExtension::toArray($variables) : CoreExtension::merge($context, $variables);
        }

        // The flag is already on inside a sandboxed render. Switching it on here
        // as well covers content reached some other way, from a trusted template.
        $checker = $this->getSandboxChecker();
        $previous = $checker?->isSandboxed();
        $checker?->setSandboxed(true);
        try {
            try {
                $loaded = $load($template);
            } catch (LoaderError $e) {
                if ($ignoreMissing) {
                    return;
                }
                throw $e;
            }

            $output = '';
            foreach ($loaded->unwrap()->yield($vars) as $chunk) {
                $output .= $chunk;
            }
        } finally {
            $checker?->setSandboxed((bool) $previous);
        }

        yield $output;
    }

    /**
     * Make the sandbox follow the render instead of stopping at the file.
     *
     * GravSourcePolicy answers per source. On its own that left every theme,
     * plugin and core file that editor content pulls in (include, embed,
     * extends, use, import, from, block()) with no policy at all, on variables
     * the content chose.
     *
     * So while a source the policy sandboxes is the one being rendered, Twig's
     * runtime sandbox flag is on and whatever it loads is checked as well (the
     * getgrav/Twig fork swaps a trusted template for its fully checked variant).
     * A render of a trusted template that PHP starts inside a sandboxed one, on
     * variables PHP chose (a module's template behind `page.content`, a plugin's
     * processTemplate()), turns the flag off again for as long as it runs.
     *
     * @param string|TemplateWrapper $name
     * @param array $partialContext
     * @param callable $render
     * @return mixed
     */
    private function withRenderSandbox($name, array $partialContext, callable $render)
    {
        $checker = $this->getSandboxChecker();
        if (null === $checker) {
            return $render();
        }

        $source = $name instanceof TemplateWrapper ? $name->getSourceContext() : new Source('', (string) $name);

        $previous = $checker->isSandboxed();
        // With the flag off, isSandboxed($source) is the source policy's answer.
        $checker->setSandboxed(false);
        $sandboxed = $checker->isSandboxed($source);
        $checker->setSandboxed($sandboxed);
        $this->renders->push($sandboxed ? ['vars' => $partialContext, 'text' => str_starts_with($source->getName(), '@Var:')] : null);
        try {
            return $render();
        } finally {
            $this->renders->pop();
            $checker->setSandboxed($previous);
        }
    }

    /**
     * The name an include from content resolves to, when it is a trusted
     * partial. A list of names resolves to the first one that exists, as Twig
     * does; only that one is checked against the list.
     *
     * @param mixed $template
     * @return string|null
     */
    private function findTrustedPartial($template): ?string
    {
        $names = \is_array($template) ? $template : [$template];
        $trusted = Security::twigTrustedPartials();
        if (!$trusted) {
            return null;
        }

        $loader = $this->getLoader();
        foreach ($names as $name) {
            if (!\is_string($name)) {
                return null;
            }
            if (\count($names) === 1 || $loader->exists($name)) {
                return \in_array($name, $trusted, true) ? $name : null;
            }
        }

        return null;
    }

    /**
     * Variables for a trusted partial included from content: the ones PHP
     * handed the content render, plus plain values from the content. Content
     * values never take a name Grav supplies, either one of the render's own
     * variables (`page`, `form`, `config`) or a Twig global (`grav`), whether
     * passed with `with` or set in the content before the include. An object
     * the content holds never reaches the partial.
     *
     * @param string $name
     * @param array $context
     * @param mixed $variables
     * @param bool $only
     * @return array
     */
    private function trustedPartialVariables(string $name, array $context, $variables, bool $only): array
    {
        $explicit = null === $variables ? [] : CoreExtension::toArray($variables);
        foreach ($explicit as $key => $value) {
            if (!self::isPlainValue($value)) {
                throw new SecurityError(sprintf('Only plain values can be passed to the trusted partial "%s" ("%s" is not one).', $name, $key));
            }
        }

        $inherited = $only ? [] : array_filter($context, [self::class, 'isPlainValue']);
        $frame = $this->renders->isEmpty() ? null : $this->renders->top();
        $base = $frame['vars'] ?? [];

        // render() adds the globals back underneath, so they reach the partial
        // as the real values.
        return $base + array_diff_key($explicit + $inherited, $base, $this->getGlobals());
    }

    /**
     * Whether the content render in progress produces text (a `@Var:` string)
     * rather than page or email HTML. False outside a content render.
     *
     * @return bool
     */
    private function rendersText(): bool
    {
        $frame = $this->renders->isEmpty() ? null : $this->renders->top();

        return (bool) ($frame['text'] ?? false);
    }

    /**
     * @param mixed $value
     * @param int $depth
     * @return bool
     */
    private static function isPlainValue($value, int $depth = 0): bool
    {
        if (null === $value || \is_scalar($value) || $value instanceof Markup) {
            return true;
        }
        if (!\is_array($value) || $depth >= 32) {
            return false;
        }
        foreach ($value as $item) {
            if (!self::isPlainValue($item, $depth + 1)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return SecurityChecker|null
     */
    private function getSandboxChecker(): ?SecurityChecker
    {
        if (!$this->hasExtension(SandboxExtension::class)) {
            return null;
        }

        /** @var SandboxExtension $sandbox */
        $sandbox = $this->getExtension(SandboxExtension::class);

        return $sandbox->getChecker();
    }
}
