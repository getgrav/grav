<?php

declare(strict_types=1);

/**
 * @package    Grav\Framework\Interfaces
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Interfaces;

use Grav\Framework\ContentBlock\ContentBlockInterface;
use Grav\Framework\ContentBlock\HtmlBlock;

/**
 * Defines common interface to render any object.
 *
 * @used-by \Grav\Framework\Flex\FlexObject
 * @since 1.6
 */
interface RenderInterface
{
    /**
     * Renders the object.
     *
     * @example $block = $object->render('custom', ['variable' => 'value']);
     * @example {% render object layout 'custom' with { variable: 'value' } %}
     *
     * @param string|null $layout  Layout to be used.
     * @param array       $context Extra context given to the renderer.
     *
     * @return ContentBlockInterface|HtmlBlock Returns `HtmlBlock` containing the rendered output.
     * @api
     */
    public function render(string $layout = null, array $context = []);
}
