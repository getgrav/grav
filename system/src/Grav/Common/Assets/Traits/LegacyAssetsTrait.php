<?php
/**
 * @package    Grav.Common.Assets.Traits
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Assets\Traits;

trait LegacyAssetsTrait
{

    protected function unifyLegacyArguments($args)
    {
        $arguments = [];

        // First argument is always the asset
        array_shift($args);

        if (count($args) === 0) {
            return [];
        } elseif (count($args) === 1 && is_array($args[0])) {
            return $args[0];
        }

        // $asset, $priority = null, $pipeline = true, $group = null, $loading = null

        foreach ($args as $index => $arg) {
            switch ($index) {
                case 0:
                    $arguments['priority'] = $args[0] ?? null;
                    break;
                case 1:
                    $arguments['pipeline'] = $args[1] ?? null;
                    break;
                case 2:
                    $arguments['group'] = $args[2] ?? null;
                    break;
                case 3:
                    $arguments['loading'] = $args[3] ?? null;
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
    public function addDeferJs($asset, $priority = null, $pipeline = true, $group = null)
    {
        return $this->addJs($asset, $priority, $pipeline, 'defer', $group);
    }


    /**
     * TODO: Needed??
     *
     * @param $asset
     * @return string
     */
    public function getQuerystring($asset)
    {
        $querystring = '';

        if (!empty($asset['query'])) {
            if (Utils::contains($asset['asset'], '?')) {
                $querystring .=  '&' . $asset['query'];
            } else {
                $querystring .= '?' . $asset['query'];
            }
        }

        if ($this->timestamp) {
            if (Utils::contains($asset['asset'], '?') || $querystring) {
                $querystring .=  '&' . $this->timestamp;
            } else {
                $querystring .= '?' . $this->timestamp;
            }
        }

        return $querystring;
    }


    /**
     * TODO: needed?
     *
     * @return string
     */
    public function __toString()
    {
        return '';
    }
}
