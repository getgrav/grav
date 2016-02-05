<?php
namespace Grav\Common\Backup;

use Grav\Common\GravTrait;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Inflector;

/**
 * The ZipBackup class lets you create simple zip-backups of a grav site
 *
 * @author RocketTheme
 * @license MIT
 */
class ZipBackup
{
    use GravTrait;

    protected static $ignorePaths = [
        'backup',
        'cache',
        'images',
        'logs'
    ];

    protected static $ignoreFolders = [
        '.git',
        '.svn',
        '.hg',
        '.idea'
    ];

    /**
     * Backup
     *
     * @param null          $destination
     * @param callable|null $messager
     *
     * @return null|string
     */
    public static function backup($destination = null, callable $messager = null)
    {
        if (!$destination) {
            $destination = self::getGrav()['locator']->findResource('backup://', true);

            if (!$destination)
                throw new \RuntimeException('The backup folder is missing.');

            Folder::mkdir($destination);
        }

        $name = self::getGrav()['config']->get('site.title', basename(GRAV_ROOT));

        $inflector = new Inflector();

        if (is_dir($destination)) {
            $date = date('YmdHis', time());
            $filename = trim($inflector->hyphenize($name), '-') . '-' . $date . '.zip';
            $destination = rtrim($destination, DS) . DS . $filename;
        }

        $messager && $messager([
            'type' => 'message',
            'level' => 'info',
            'message' => 'Creating new Backup "' . $destination . '"'
        ]);
        $messager && $messager([
            'type' => 'message',
            'level' => 'info',
            'message' => ''
        ]);

        $zip = new \ZipArchive();
        $zip->open($destination, \ZipArchive::CREATE);

        $max_execution_time = ini_set('max_execution_time', 600);

        static::folderToZip(GRAV_ROOT, $zip, strlen(rtrim(GRAV_ROOT, DS) . DS), $messager);

        $messager && $messager([
            'type' => 'progress',
            'percentage' => false,
            'complete' => true
        ]);

        $messager && $messager([
            'type' => 'message',
            'level' => 'info',
            'message' => ''
        ]);
        $messager && $messager([
            'type' => 'message',
            'level' => 'info',
            'message' => 'Saving and compressing archive...'
        ]);

        $zip->close();

        if ($max_execution_time !== false) {
            ini_set('max_execution_time', $max_execution_time);
        }

        return $destination;
    }

    /**
     * @param $folder
     * @param $zipFile
     * @param $exclusiveLength
     * @param $messager
     */
    private static function folderToZip($folder, \ZipArchive &$zipFile, $exclusiveLength, callable $messager = null)
    {
        $handle = opendir($folder);
        while (false !== $f = readdir($handle)) {
            if ($f != '.' && $f != '..') {
                $filePath = "$folder/$f";
                // Remove prefix from file path before add to zip.
                $localPath = substr($filePath, $exclusiveLength);

                if (in_array($f, static::$ignoreFolders)) {
                    continue;
                } elseif (in_array($localPath, static::$ignorePaths)) {
                    $zipFile->addEmptyDir($f);
                    continue;
                }

                if (is_file($filePath)) {
                    $zipFile->addFile($filePath, $localPath);

                    $messager && $messager([
                        'type' => 'progress',
                        'percentage' => false,
                        'complete' => false
                    ]);
                } elseif (is_dir($filePath)) {
                    // Add sub-directory.
                    $zipFile->addEmptyDir($localPath);
                    static::folderToZip($filePath, $zipFile, $exclusiveLength, $messager);
                }
            }
        }
        closedir($handle);
    }
}
