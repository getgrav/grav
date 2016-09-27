<?php
/**
 * @package    Grav.Common.GPM
 *
 * @copyright  Copyright (C) 2014 - 2016 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\GPM;

use Grav\Common\Grav;
use RocketTheme\Toolbox\File\YamlFile;

/**
 * Class Licenses
 *
 * @package Grav\Common\GPM
 */
class Licenses
{

    /**
     * Regex to validate the format of a License
     *
     * @var string
     */
    protected static $regex = '^(?:[A-F0-9]{8}-){3}(?:[A-F0-9]{8}){1}$';

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
        $licenses = YamlFile::instance(self::getLicensePath());
        $data = $licenses->content();

        if ($license && !self::validate($license)) {
            return false;
        }

        if (!is_string($license)) {
            if (isset($data['licenses'][$slug])) {
                unset($data['licenses'][$slug]);
            } else {
                return false;
            }
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
    public static function get($slug = null)
    {
        $licenses = YamlFile::instance(self::getLicensePath());
        $data = $licenses->content();
        $licenses->free();

        if (!$slug) {
            return $data['licenses'];
        }

        if (!isset($data['licenses']) || !isset($data['licenses'][$slug])) {
            return '';
        }

        return $data['licenses'][$slug];
    }


    /**
     * Validates the License format
     *
     * @param $license
     *
     * @return bool
     */
    public static function validate($license = null)
    {
        if (!is_string($license)) {
            return false;
        }

        return preg_match('#' . self::$regex. '#', $license);
    }

    /**
     * @return string
     */
    protected static function getLicensePath()
    {
        $path = Grav::instance()['locator']->findResource('user://data') . '/licenses.yaml';
        if (!file_exists($path)) {
            touch($path);
        }
        return $path;
    }
}
