<?php

/**
 * @package    Grav\Common\Twig
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Twig;

use Grav\Common\Twig\Sandbox\SourceSandboxNodeVisitor;
use ReflectionClass;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Extension\EscaperExtension;
use Twig\Extension\ExtensionInterface;
use Twig\Extension\SandboxExtension;
use Twig\Loader\ExistsLoaderInterface;
use Twig\Loader\LoaderInterface;
use Twig\NodeVisitor\SandboxNodeVisitor;
use Twig\Runtime\EscaperRuntime;
use Twig\Template;
use Twig\TemplateWrapper;

/**
 * Class TwigEnvironment
 * @package Grav\Common\Twig
 */
class TwigEnvironment extends Environment
{
    /** @var SandboxExtension|null */
    protected ?SandboxExtension $sourceSandbox = null;

    /**
     * Compile trusted templates without the sandbox's runtime checks.
     *
     * Only call this for a SandboxExtension whose source policy decides from the
     * template Source alone (GravSourcePolicy), because the decision is baked
     * into the compiled template. Call it before the first template compiles.
     * See SourceSandboxNodeVisitor for which templates keep the checks.
     *
     * @param SandboxExtension $sandbox The extension registered on this environment
     * @return void
     */
    public function setSourceSandbox(SandboxExtension $sandbox): void
    {
        if (!$this->hasExtension(SandboxExtension::class) || $this->getExtension(SandboxExtension::class) !== $sandbox) {
            throw new \LogicException('The SandboxExtension must be registered on this environment first.');
        }

        $this->sourceSandbox = $sandbox;
    }

    /**
     * @inheritDoc
     */
    public function getNodeVisitors(): array
    {
        $visitors = parent::getNodeVisitors();
        if (null === $this->sourceSandbox) {
            return $visitors;
        }

        foreach ($visitors as $i => $visitor) {
            if ($visitor instanceof SandboxNodeVisitor) {
                $visitors[$i] = new SourceSandboxNodeVisitor($visitor, $this->sourceSandbox);
            }
        }

        return $visitors;
    }

    /**
     * @inheritDoc
     *
     * With a source sandbox set, a template loaded while the sandbox is switched
     * on for everything (`{% sandbox %}`, `include(..., sandboxed = true)`) gets
     * its own class, compiled with the full checks. Every class name also gets a
     * suffix, so templates compiled before trusted templates lost their checks
     * are never mixed with the new ones.
     */
    public function getTemplateClass(string $name, ?int $index = null): string
    {
        if (null === $this->sourceSandbox) {
            return parent::getTemplateClass($name, $index);
        }

        $class = parent::getTemplateClass($name) . ($this->sourceSandbox->isSandboxed() ? '_sandboxed' : '_sourced');

        return null === $index ? $class : $class . '___' . $index;
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
     */
    public function resolveTemplate($names): TemplateWrapper
    {
        if (!\is_array($names)) {
            $names = [$names];
        }

        $count = \count($names);
        foreach ($names as $name) {
            if ($name instanceof Template) {
                return $name;
            }
            if ($name instanceof TemplateWrapper) {
                return $name;
            }

            // Optimization: Avoid throwing an exception when it would be ignored anyway.
            if (1 !== $count) {
                /** @var LoaderInterface|ExistsLoaderInterface $loader */
                $loader = $this->getLoader();
                if (!$loader->exists($name)) {
                    continue;
                }
            }

            // Throws LoaderError: Unable to find template "%s".
            return $this->load($name);
        }

        throw new LoaderError(sprintf('Unable to find one of the following templates: "%s".', implode('", "', $names)));
    }
}
