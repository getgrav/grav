<?php

/**
 * @package    Grav\Common\GPM
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\GPM;

use Grav\Common\File\CompiledYamlFile;
use Grav\Common\Grav;
use RocketTheme\Toolbox\File\FileInterface;
use function is_string;

/**
 * Class Licenses
 *
 * @package Grav\Common\GPM
 */
class Licenses
{
    /** @var string Regex to validate the format of a License */
    protected static $regex = '^(?:[A-F0-9]{8}-){3}(?:[A-F0-9]{8}){1}$';
    /** @var FileInterface */
    protected static $file;

    /**
     * Returns the license for a Premium package
     *
     * @param string $slug
     * @param string $license
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
     * @param string|null $slug
     * @return string[]|string
     */
    public static function get($slug = null)
    {
        $licenses = self::getLicenseFile();
        $data = (array)$licenses->content();
        $licenses->free();

        if (null === $slug) {
            return $data['licenses'] ?? [];
        }

        $slug = strtolower($slug);

        return $data['licenses'][$slug] ?? '';
    }


    /**
     * Validates the License format
     *
     * @param string|null $license
     * @return bool
     */
    public static function validate($license = null)
    {
        if (!is_string($license)) {
            return false;
        }

        return (bool)preg_match('#' . self::$regex. '#', $license);
    }

    /**
     * Get the License File object
     *
     * @return FileInterface
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
