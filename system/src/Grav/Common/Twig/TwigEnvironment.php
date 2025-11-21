<?php

/**
 * @package    Grav\Common\Twig
 *
 * @copyright  Copyright (c) 2015 - 2025 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Twig;

use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Extension\EscaperExtension;
use Twig\Extension\ExtensionInterface;
use Twig\Loader\ExistsLoaderInterface;
use Twig\Loader\LoaderInterface;
use Twig\Runtime\EscaperRuntime;
use Twig\Template;
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

        if ($name === EscaperExtension::class && class_exists(EscaperRuntime::class)) {
            return new class($extension, $this) extends EscaperExtension {
                private $original;
                private $env;

                public function __construct($original, $env)
                {
                    $this->original = $original;
                    $this->env = $env;
                }

                public function setEscaper($strategy, $callable)
                {
                    $this->env->getRuntime(EscaperRuntime::class)->setEscaper($strategy, $callable);
                }

                public function getDefaultStrategy($filename)
                {
                    return $this->original->getDefaultStrategy($filename);
                }
            };
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
