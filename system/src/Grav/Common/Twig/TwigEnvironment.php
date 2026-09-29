<?php

/**
 * @package    Grav\Common\Twig
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Twig;

use ReflectionClass;
use Twig\Environment;
use Twig\Extension\EscaperExtension;
use Twig\Extension\ExtensionInterface;
use Twig\Runtime\EscaperRuntime;
use Twig\TemplateWrapper;

/**
 * Class TwigEnvironment
 * @package Grav\Common\Twig
 */
class TwigEnvironment extends Environment
{
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
}
