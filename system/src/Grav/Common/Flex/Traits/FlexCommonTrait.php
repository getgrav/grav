<?php

declare(strict_types=1);

/**
 * @package    Grav\Common\Flex
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Flex\Traits;

use Grav\Common\Debugger;
use Grav\Common\Grav;
use Grav\Common\Twig\Twig;
use Twig\Error\LoaderError;
use Twig\Error\SyntaxError;
use Twig\Template;
use Twig\TemplateWrapper;

/**
 * Trait FlexCommonTrait
 * @package Grav\Common\Flex\Traits
 */
trait FlexCommonTrait
{
    /**
     * @param string $layout
     * @return Template|TemplateWrapper
     * @throws LoaderError
     * @throws SyntaxError
     */
    protected function getTemplate($layout)
    {
        $container = $this->getContainer();

        /** @var Twig $twig */
        $twig = $container['twig'];

        try {
            return $twig->twig()->resolveTemplate($this->getTemplatePaths($layout));
        } catch (LoaderError $e) {
            /** @var Debugger $debugger */
            $debugger = Grav::instance()['debugger'];
            $debugger->addException($e);

            return $twig->twig()->resolveTemplate(['flex/404.html.twig']);
        }
    }

    abstract protected function getTemplatePaths(string $layout): array;
    abstract protected function getContainer(): Grav;
}
