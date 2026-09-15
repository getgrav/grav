<?php

/**
 * @package    Grav\Common\GPM
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\GPM;

use Grav\Common\File\CompiledYamlFile;
use Grav\Common\GPM\Common\Package;
use Grav\Common\Grav;
use RocketTheme\Toolbox\File\FileInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Throwable;
use function in_array;
use function is_array;
use function is_object;
use function is_string;
use function str_starts_with;
use function strip_tags;
use function strlen;
use function substr;

/**
 * Class Licenses
 *
 * @package Grav\Common\GPM
 */
class Licenses
{
    /**
     * What a licence key can look like at all.
     *
     * A Grav Premium key is four groups of eight uppercase hex characters,
     * but GPM also serves packages other stores license, and each store has
     * its own format (KahunaCart's is `KC-XXXX-XXXX-XXXX-XXXX`). The store
     * that issued a key is what decides whether it is real, so this only
     * turns away what no store could have issued: fewer than 8 or more than
     * 64 characters, or anything besides letters, digits and the separators
     * keys are written with.
     *
     * @var string
     */
    protected static $regex = '^[A-Za-z0-9][A-Za-z0-9._-]{6,62}[A-Za-z0-9]$';
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
     * The license key to use for a package, wherever that package's key is
     * filed.
     *
     * A key is stored under the slug it was pasted against, but a package is
     * not always sold under its own name. KahunaCart's payment providers are
     * packages inside the core licence rather than products of their own, and
     * the repository entry says so: `premium.license_product` is the product a
     * key has to belong to for this package. The download proxy has always
     * validated against that product, so the key filed under it is the key to
     * send. Without this, a customer holding one key has to paste it once per
     * package, and every package they have not yet pasted it against reads as
     * one they do not own.
     *
     * The package's own slug wins whenever it has a key of its own, so a store
     * that sells an add-on separately and also grants it to a wider licence
     * works either way round.
     *
     * @param string $slug    the package slug
     * @param mixed  $premium the package's `premium` metadata, as an array or
     *                        object. Anything else (a bare `true` from an
     *                        installed package's blueprint, say) names no
     *                        product and is treated as naming none.
     * @return string the key, or an empty string when there is none
     */
    public static function resolve($slug, $premium = null)
    {
        $slug = is_string($slug) ? strtolower($slug) : '';

        if ($slug !== '') {
            $license = self::get($slug);
            if (is_string($license) && $license !== '') {
                return $license;
            }
        }

        $product = self::licenseProduct($premium);
        if ($product === '' || $product === $slug) {
            return '';
        }

        $license = self::get($product);

        return is_string($license) ? $license : '';
    }

    /**
     * The license key to use for a package.
     *
     * @param Package|null $package
     * @return string the key, or an empty string when there is none
     */
    public static function forPackage($package)
    {
        if (!$package instanceof Package) {
            return '';
        }

        return self::resolve($package->slug ?? '', $package->premium ?? null);
    }

    /**
     * The store product a package's licence has to belong to, from its
     * `premium` metadata. Empty when the metadata names none.
     *
     * @param mixed $premium
     * @return string
     */
    protected static function licenseProduct($premium)
    {
        if (is_object($premium)) {
            $premium = (array)$premium;
        }

        if (!is_array($premium)) {
            return '';
        }

        $product = $premium['license_product'] ?? null;

        return is_string($product) ? strtolower(trim($product)) : '';
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
     * The reason a premium download was refused, when the download proxy
     * gave one worth repeating.
     *
     * getgrav.org answers a refused premium download with a 401 and one line
     * of plain text. "Unauthorized: Invalid LICENSE" is a key it does not
     * know, and the caller already says that in its own words; but when the
     * store that issued the key refused for a reason the person can act on
     * (the updates window has ended, or the key does not cover this add-on),
     * the line carries the store's explanation and where to renew or buy,
     * and that is the message to show. Null when there is no such reason.
     *
     * @param Throwable $e The exception the download raised
     * @return string|null
     */
    public static function refusalReason(Throwable $e): ?string
    {
        if (!$e instanceof HttpExceptionInterface) {
            return null;
        }

        try {
            $response = $e->getResponse();
            if ($response->getStatusCode() !== 401) {
                return null;
            }
            $body = trim($response->getContent(false));
        } catch (Throwable $inner) {
            return null;
        }

        $prefix = 'Unauthorized: ';
        if (!str_starts_with($body, $prefix) || strlen($body) > 500 || $body !== strip_tags($body)) {
            return null;
        }

        $reason = trim(substr($body, strlen($prefix)));
        if ($reason === '' || in_array($reason, ['Invalid LICENSE', 'Invalid Payload'], true)) {
            return null;
        }

        return $reason;
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
