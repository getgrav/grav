<?php
/**
 * @package    Grav.Common.Assets
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Assets;

use Grav\Common\Utils;

class Css extends BaseAsset
{

    public function __construct(array $elements = [], $key = null)
    {
        $base_options = [
            'asset_type' => 'css',
            'attributes' => [
                'type' => 'text/css',
                'rel' => 'stylesheet'
            ]
        ];

        $merged_attributes = Utils::arrayMergeRecursiveUnique($base_options, $elements);
        parent::__construct($merged_attributes, $key);
    }


    public function render() {
        return "<link href=\"" . trim($this->asset) . $this->renderQueryString() . "\"" . $this->renderAttributes() . ">\n";
    }
}
