<?php
/**
 * @package    Grav.Common.Assets.Traits
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Assets\Traits;

use Grav\Common\Assets;

trait LegacyAssetsTrait
{

    protected function unifyLegacyArguments($args, $type = Assets::CSS_TYPE)
    {
        // First argument is always the asset
        array_shift($args);

        if (\count($args) === 0) {
            return [];
        }
        if (\count($args) === 1 && \is_array($args[0])) {
            return $args[0];
        }

        switch ($type) {
            case(Assets::INLINE_CSS_TYPE):
                $keys = ['priority', 'group'];
                $arguments = array_combine(array_slice($keys, 0, count($args)), $args);
                break;

            case(Assets::JS_TYPE):
                $keys = ['priority', 'pipeline', 'loading', 'group'];
                $arguments = array_combine(array_slice($keys, 0, count($args)), $args);
                break;

            case(Assets::INLINE_JS_TYPE):
                $keys = ['priority', 'group', 'attributes'];
                $arguments = array_combine(array_slice($keys, 0, count($args)), $args);

                // special case to handle old attributes being passed in
                if (isset($arguments['attributes'])) {
                    $old_attributes = $arguments['attributes'];
                    $arguments = array_merge($arguments, $old_attributes);
                }
                unset($arguments['attributes']);

                break;

            default:
            case(Assets::CSS_TYPE):
                $keys = ['priority', 'pipeline', 'group', 'loading'];
                $arguments = array_combine(array_slice($keys, 0, count($args)), $args);
        }

        return $arguments;
    }

    /**
     * Convenience wrapper for async loading of JavaScript
     *
     * @param        $asset
     * @param int    $priority
     * @param bool   $pipeline
     * @param string $group name of the group
     *
     * @deprecated Please use dynamic method with ['loading' => 'async']
     *
     * @return \Grav\Common\Assets
     */
    public function addAsyncJs($asset, $priority = 10, $pipeline = true, $group = 'head')
    {
        return $this->addJs($asset, $priority, $pipeline, 'async', $group);
    }

    /**
     * Convenience wrapper for deferred loading of JavaScript
     *
     * @param        $asset
     * @param int    $priority
     * @param bool   $pipeline
     * @param string $group name of the group
     *
     * @deprecated Please use dynamic method with ['loading' => 'defer']
     *
     * @return \Grav\Common\Assets
     */
    public function addDeferJs($asset, $priority = 10, $pipeline = true, $group = 'head')
    {
        return $this->addJs($asset, $priority, $pipeline, 'defer', $group);
    }

}
