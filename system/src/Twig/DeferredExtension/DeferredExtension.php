<?php

/**
 * This file is part of the rybakit/twig-deferred-extension package.
 *
 * (c) Eugene Leonovich <gen.work@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Twig\DeferredExtension;

use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\Template;

final class DeferredExtension extends AbstractExtension
{
    private $blocks = [];

    public function getTokenParsers() : array
    {
        return [new DeferredTokenParser()];
    }

    public function getNodeVisitors() : array
    {
        if (Environment::VERSION_ID < 20000) {
            // Twig 1.x support
            return [new DeferredNodeVisitorCompat()];
        }

        return [new DeferredNodeVisitor()];
    }

    public function defer(Template $template, string $blockName) : void
    {
        $templateName = $template->getTemplateName();
        $this->blocks[$templateName][] = $blockName;
        $index = \count($this->blocks[$templateName]) - 1;

        \ob_start(function (string $buffer) use ($index, $templateName) {
            unset($this->blocks[$templateName][$index]);

            return $buffer;
        });
    }

    public function resolve(Template $template, array $context, array $blocks) : void
    {
        $templateName = $template->getTemplateName();
        if (empty($this->blocks[$templateName])) {
            return;
        }

        while ($blockName = \array_pop($this->blocks[$templateName])) {
            $buffer = \ob_get_clean();

            $blocks[$blockName] = [$template, 'block_'.$blockName.'_deferred'];
            $template->displayBlock($blockName, $context, $blocks);

            echo $buffer;
        }

        if ($parent = $template->getParent($context)) {
            $this->resolve($parent, $context, $blocks);
        }
    }
}
