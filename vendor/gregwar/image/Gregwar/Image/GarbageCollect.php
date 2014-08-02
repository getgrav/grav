<?php

namespace Gregwar\Image;

/**
 * Garbage collect a directory, this will crawl a directory, lookng
 * for files older than X days and destroy them
 *
 * @author Gregwar <g.passault@gmail.com>
 */
class GarbageCollect
{
    /**
     * Drops old files of a directory
     *
     * @param string $directory the name of the target directory
     * @param int $days the number of days to consider a file old
     * @param bool $verbose enable verbose output
     *
     * @return true if all the files/directories of a directory was wiped
     */
    public static function dropOldFiles($directory, $days = 30, $verbose = false)
    {
        $allDropped = true;
        $now = time();

        $dir = opendir($directory);

        if (!$dir) {
            if ($verbose) {
                echo "! Unable to open $directory\n";
            }

            return false;
        }

        while ($file = readdir($dir)) {
            if ($file == '.' || $file == '..') {
                continue;
            }

            $fullName = $directory.'/'.$file;

            $old = $now-filemtime($fullName);

            if (is_dir($fullName)) {
                // Directories are recursively crawled
                if (static::dropOldFiles($fullName, $days, $verbose)) {
                    self::drop($fullName, $verbose);
                } else {
                    $allDropped = false;
                }
            } else {
                if ($old > (24*60*60*$days)) {
                    self::drop($fullName, $verbose);
                } else {
                    $allDropped = false;
                }
            }
        }

        closedir($dir);

        return $allDropped;
    }

    /**
     * Drops a file or an empty directory
     */
    public static function drop($file, $verbose = false)
    {
        if (is_dir($file)) {
            @rmdir($file);
        } else {
            @unlink($file);
        }

        if ($verbose) {
            echo "> Dropping $file...\n";
        }
    }

}
