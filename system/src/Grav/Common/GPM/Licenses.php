<?php

/**
 * @package    Grav\Common\GPM
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\GPM;

use Grav\Common\File\CompiledYamlFile;
use Grav\Common\Grav;

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

    protected static $file;


    /**
     * Returns the license for a Premium package
     *
     * @param string $slug
     * @param string $license
     *
     * @return bool
     */
    public static function set($slug, $license)
    {
        $licenses = self::getLicenseFile();
        $data = (array)$licenses->content();
        $slug = strtolower($slug);

        if ($license && !self::validate($license)) {
            return false;
        }

        if (!\is_string($license)) {
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
     * @param string $slug
     *
     * @return array|string
     */
    public static function get($slug = null)
    {
        $licenses = self::getLicenseFile();
        $data = (array)$licenses->content();
        $licenses->free();
        $slug = strtolower($slug);

        if (!$slug) {
            return $data['licenses'] ?? [];
        }

        return $data['licenses'][$slug] ?? '';
    }


    /**
     * Validates the License format
     *
     * @param string $license
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
     * Get the License File object
     *
     * @return \RocketTheme\Toolbox\File\FileInterface
     */
    public static function getLicenseFile()

    {
        if (!isset(self::$file)) {
            $path = Grav::instance()['locator']->findResource('user-data://') . '/licenses.yaml';
            if (!file_exists($path)) {
                touch($path);
            }
            self::$file = CompiledYamlFile::instance($path);
        }

        return self::$file;
    }
}
