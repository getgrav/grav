<?php
/**
 * @package    Grav.Common.GPM
 *
 * @copyright  Copyright (C) 2014 - 2016 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\GPM;

use RocketTheme\Toolbox\File\YamlFile;

class Licenses
{
    protected static $licenses = 'user://data/licenses.yaml';

    /**
     * Returns the license for a Premium package
     *
     * @param $slug
     * @param $license
     *
     * @return boolean
     */
    public static function set($slug, $license)
    {
        // TODO: this fails to save due to read-only stream apparently
        $licenses = YamlFile::instance(self::$licenses);
        $data = $licenses->content();

        if (!is_string($license)) {
            unset($data['licenses'][$slug]);
        } else {
            $data['licenses'][$slug] = $license;
        }

        $licenses->save($data);
        $licenses->free();

        return true;
    }

    /**
     * Returns the license for a Premium package
     *
     * @param $slug
     *
     * @return string
     */
    public static function get($slug)
    {
        $licenses = YamlFile::instance(self::$licenses);
        $data = $licenses->content();
        $licenses->free();

        if (!isset($data['licenses']) || !isset($data['licenses'][$slug])) {
            return '';
        }

        return $data['licenses'][$slug];
    }
}
