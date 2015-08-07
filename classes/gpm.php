<?php
namespace Grav\Plugin\Admin;

use Grav\Common\GravTrait;
use Grav\Common\GPM\GPM as GravGPM;
use Grav\Common\GPM\Installer;
use Grav\Common\GPM\Response;
use Grav\Common\GPM\Upgrader;
use Grav\Common\Filesystem\Folder;
use Grav\Common\GPM\Common\Package;

class Gpm
{
    use GravTrait;

    // Probably should move this to Grav DI container?
    protected static $GPM;
    public static function GPM()
    {
        if (!static::$GPM) {
            static::$GPM = new GravGPM();
        }
        return static::$GPM;
    }

    /**
     * Default options for the install
     * @var array
     */
    protected static $options = [
        'destination'     => GRAV_ROOT,
        'overwrite'       => true,
        'ignore_symlinks' => true,
        'skip_invalid'    => true,
        'install_deps'    => true
    ];

    public static function install($packages, $options)
    {
        $options = array_merge(self::$options, $options);

        if (
            !Installer::isGravInstance($options['destination']) ||
            !Installer::isValidDestination($options['destination'], [Installer::EXISTS, Installer::IS_LINK])
        ) {
            return false;
        }

        $packages = is_array($packages) ? $packages : [ $packages ];
        $count = count($packages);

        $packages = array_filter(array_map(function ($p) {
            return !is_string($p) ? $p instanceof Package ? $p : false : self::GPM()->findPackage($p);
        }, $packages));

        if (!$options['skip_invalid'] && $count !== count($packages)) {
            return false;
        }

        foreach ($packages as $package) {
            if (isset($package->dependencies) && $options['install_deps']) {
                $result = static::install($package->dependencies, $options);

                if (!$result) {
                    return false;
                }
            }

            // Check destination
            Installer::isValidDestination($options['destination'] . DS . $package->install_path);

            if (Installer::lastErrorCode() === Installer::EXISTS && !$options['overwrite']) {
                return false;
            }

            if (Installer::lastErrorCode() === Installer::IS_LINK && !$options['ignore_symlinks']) {
                return false;
            }

            $local = static::download($package);

            Installer::install($local, $options['destination'], ['install_path' => $package->install_path]);
            Folder::delete(dirname($local));

            $errorCode = Installer::lastErrorCode();

            if (Installer::lastErrorCode() & (Installer::ZIP_OPEN_ERROR | Installer::ZIP_EXTRACT_ERROR)) {
                return false;
            }
        }

        return true;
    }

    public static function update($packages, $options)
    {
        $options['overwrite'] = true;
        return static::install($packages, $options);
    }

    public static function uninstall($packages, $options)
    {
        $options = array_merge(self::$options, $options);

        $packages = is_array($packages) ? $packages : [ $packages ];
        $count = count($packages);

        $packages = array_filter(array_map(function ($p) {

            if (is_string($p)) {
                $p = strtolower($p);
                $plugin = static::GPM()->getInstalledPlugin($p);
                $p = $plugin ?: static::GPM()->getInstalledTheme($p);
            }

            return $p instanceof Package ? $p : false;

        }, $packages));

        if (!$options['skip_invalid'] && $count !== count($packages)) {
            return false;
        }

        foreach ($packages as $package) {

            $location = self::getGrav()['locator']->findResource($package->package_type . '://' . $package->slug);

            // Check destination
            Installer::isValidDestination($location);

            if (Installer::lastErrorCode() === Installer::IS_LINK && !$options['ignore_symlinks']) {
                return false;
            }

            Installer::uninstall($location);

            $errorCode = Installer::lastErrorCode();
            if ($errorCode && $errorCode !== Installer::IS_LINK && $errorCode !== Installer::EXISTS) {
                return false;
            }
        }

        return true;
    }

    private static function download($package)
    {
        $contents = Response::get($package->zipball_url, []);

        $cache_dir = self::getGrav()['locator']->findResource('cache://', true);
        $cache_dir = $cache_dir . DS . 'tmp/Grav-' . uniqid();
        Folder::mkdir($cache_dir);

        $filename = $package->slug . basename($package->zipball_url);

        file_put_contents($cache_dir . DS . $filename, $contents);

        return $cache_dir . DS . $filename;
    }

    private static function _downloadSelfupgrade($package, $tmp)
    {
        $output = Response::get($package['download'], []);
        Folder::mkdir($tmp);
        file_put_contents($tmp . DS . $package['name'], $output);
        return $tmp . DS . $package['name'];
    }

    public static function selfupgrade() {
        $upgrader = new Upgrader();

        if (!Installer::isGravInstance(GRAV_ROOT)) {
            return false;
        }

        if (is_link(GRAV_ROOT . DS . 'index.php')) {
            Installer::setError(Installer::IS_LINK);
            return false;
        }

        $update = $upgrader->getAssets()['grav-update'];
        $tmp = CACHE_DIR . 'tmp/Grav-' . uniqid();
        $file = self::_downloadSelfupgrade($update, $tmp);

        Installer::install($file, GRAV_ROOT,
            ['sophisticated' => true, 'overwrite' => true, 'ignore_symlinks' => true]);

        $errorCode = Installer::lastErrorCode();

        Folder::delete($tmp);

        if ($errorCode & (Installer::ZIP_OPEN_ERROR | Installer::ZIP_EXTRACT_ERROR)) {
            return false;
        }

        return true;
    }
}
