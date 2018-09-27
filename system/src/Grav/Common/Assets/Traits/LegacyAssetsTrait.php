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
        $arguments = [];

        // First argument is always the asset
        array_shift($args);

        if (\count($args) === 0) {
            return [];
        }
        if (\count($args) === 1 && \is_array($args[0])) {
            return $args[0];
        }

        // $asset, $priority = null, $pipeline = true, $group = null, $loading = null

        foreach ($args as $index => $arg) {
            switch ($index) {
                case 0:
                    if (isset($args[0])) { $arguments['priority'] = $args[0]; }
                    break;
                case 1:
                    if (isset($args[1])) { $arguments['pipeline'] = $args[1]; }
                    break;
                case 2:
                    if ($type === Assets::CSS_TYPE) {
                        if (isset($args[2])) { $arguments['group'] = $args[2]; }
                    } else {
                        if (isset($args[2])) { $arguments['loading'] = $args[2]; }
                    }
                    break;
                case 3:
                    if ($type === Assets::CSS_TYPE) {
                        if (isset($args[3])) { $arguments['loading'] = $args[3]; }
                    } else {
                        if (isset($args[3])) { $arguments['group'] = $args[3]; }
                    }
                    break;
            }
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
    public function addAsyncJs($asset, $priority = null, $pipeline = true, $group = null)
    {
        return $this->addJs($asset, $priority, $pipeline, $group, 'async');
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
    public function addDeferJs($asset, $priority = null, $pipeline = true, $group = null)
    {
        return $this->addJs($asset, $priority, $pipeline, $group, 'defer');
    }

}
