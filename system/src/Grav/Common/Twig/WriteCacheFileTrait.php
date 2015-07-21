<?php
namespace Grav\Common\Twig;

use Grav\Common\GravTrait;

/**
 * A trait to add some custom processing to the identifyLink() method in Parsedown and ParsedownExtra
 */
trait WriteCacheFileTrait
{
    use GravTrait;

    protected static $umask;
    /**
     * This exists so template cache files use the same
     * group between apache and cli
     *
     * @param $file
     * @param $content
     */
    protected function writeCacheFile($file, $content)
    {
        if (!isset(self::$umask)) {
            self::$umask = self::getGrav()['config']->get('system.twig.umask_fix', false);
        }

        if (self::$umask) {
            if (!is_dir(dirname($file))) {
                $old = umask(0002);
                mkdir(dirname($file), 0777, true);
                umask($old);
            }
            parent::writeCacheFile($file, $content);
            chmod($file, 0775);
        } else {
            parent::writeCacheFile($file, $content);
        }
    }
}
