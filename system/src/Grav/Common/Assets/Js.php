<?php

/**
 * @package    Grav\Common\Assets
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Assets;

use Grav\Common\Utils;

class Js extends BaseAsset
{
    public function __construct(array $elements = [], $key = null)
    {
        $base_options = [
            'asset_type' => 'js',
        ];

        $merged_attributes = Utils::arrayMergeRecursiveUnique($base_options, $elements);

        parent::__construct($merged_attributes, $key);
    }

    public function render()
    {
        if (isset($this->attributes['loading']) && $this->attributes['loading'] === 'inline') {
            $buffer = $this->gatherLinks( [$this], self::JS_ASSET);
            return '<script' . $this->renderAttributes() . ">\n" . trim($buffer) . "\n</script>\n";
        }

        return '<script src="' . trim($this->asset) . $this->renderQueryString() . '"' . $this->renderAttributes() . "></script>\n";
    }
}
