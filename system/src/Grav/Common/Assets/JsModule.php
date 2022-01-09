<?php

/**
 * @package    Grav\Common\Assets
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Assets;

use Grav\Common\Utils;

/**
 * Class Js
 * @package Grav\Common\Assets
 */
class JsModule extends Js
{
    /**
     * Js constructor.
     * @param array $elements
     * @param string|null $key
     */
    public function __construct(array $elements = [], ?string $key = null)
    {
        $base_options = [
            'asset_type' => 'js_module',
            'attributes' => ['type' => 'module']
        ];

        $merged_attributes = Utils::arrayMergeRecursiveUnique($base_options, $elements);

        parent::__construct($merged_attributes, $key);
    }
}
