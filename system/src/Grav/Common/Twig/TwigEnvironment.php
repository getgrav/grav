<?php

/**
 * @package    Grav\Common\Twig
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Twig;

use Twig\Environment;
use Twig\Error\LoaderError;
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

            // Avoid throwing an exception as it is really slow to handle.
            if (1 !== $count && !$this->getLoader()->exists($name)) {
                continue;
            }

            try {
                return $this->loadTemplate($name);
            } catch (LoaderError $e) {
                if (1 === $count) {
                    throw $e;
                }
            }
        }

        throw new LoaderError(sprintf('Unable to find one of the following templates: "%s".', implode('", "', $names)));
    }
}
