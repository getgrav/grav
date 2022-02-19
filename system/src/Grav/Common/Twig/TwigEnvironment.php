<?php

/**
 * @package    Grav\Common\Twig
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Twig;

use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Loader\ExistsLoaderInterface;
use Twig\Loader\LoaderInterface;
use Twig\Template;
use Twig\TemplateWrapper;

/**
 * Class TwigEnvironment
 * @package Grav\Common\Twig
 */
class TwigEnvironment extends Environment
{
    use WriteCacheFileTrait;

    /**
     * @inheritDoc
     */
    public function resolveTemplate($names)
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
            return $this->loadTemplate($name);
        }

        throw new LoaderError(sprintf('Unable to find one of the following templates: "%s".', implode('", "', $names)));
    }
}
