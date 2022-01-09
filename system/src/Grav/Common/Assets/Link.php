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
 * Class Link
 * @package Grav\Common\Assets
 */
class Link extends BaseAsset
{
    /**
     * Css constructor.
     * @param array $elements
     * @param string|null $key
     */
    public function __construct(array $elements = [], ?string $key = null)
    {
        $base_options = [
            'asset_type' => 'link',
        ];

        $merged_attributes = Utils::arrayMergeRecursiveUnique($base_options, $elements);

        parent::__construct($merged_attributes, $key);
    }

    /**
     * @return string
     */
    public function render()
    {
        return '<link href="' . trim($this->asset) . $this->renderQueryString() . '"' . $this->renderAttributes() . $this->integrityHash($this->asset) . ">\n";
    }
}
