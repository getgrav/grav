<?php
/**
 * @package    Grav.Common.Page
 *
 * @copyright  Copyright (C) 2014 - 2016 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Page\Medium;

use Grav\Common\Grav;
use Gregwar\Image\Exceptions\GenerationError;
use Gregwar\Image\Image;
use RocketTheme\Toolbox\Event\Event;

class ImageFile extends Image
{
    /**
     * Clear previously applied operations
     */
    public function clearOperations()
    {
        $this->operations = [];
    }

    /**
     * This is the same as the Gregwar Image class except this one fires a Grav Event on creation of new cached file
     *
     * @param string $type    the image type
     * @param int    $quality the quality (for JPEG)
     * @param bool   $actual
     *
     * @return mixed|string
     */
    public function cacheFile($type = 'jpg', $quality = 80, $actual = false)
    {
        if ($type == 'guess') {
            $type = $this->guessType();
        }

        if (!count($this->operations) && $type == $this->guessType() && !$this->forceCache) {
            return $this->getFilename($this->getFilePath());
        }

        // Computes the hash
        $this->hash = $this->getHash($type, $quality);

        // Generates the cache file
        $cacheFile = '';

        if (!$this->prettyName || $this->prettyPrefix) {
            $cacheFile .= $this->hash;
        }

        if ($this->prettyPrefix) {
            $cacheFile .= '-';
        }

        if ($this->prettyName) {
            $cacheFile .= $this->prettyName;
        }


        $cacheFile .= '.'.$type;

        // If the files does not exists, save it
        $image = $this;

        // Target file should be younger than all the current image
        // dependencies
        $conditions = array(
            'younger-than' => $this->getDependencies()
        );

        // The generating function
        $generate = function ($target) use ($image, $type, $quality) {
            $result = $image->save($target, $type, $quality);

            if ($result != $target) {
                throw new GenerationError($result);
            }

            Grav::instance()->fireEvent('onImageMediumSaved', new Event(['image' => $target]));
        };

        // Asking the cache for the cacheFile
        try {
            $perms = Grav::instance()['config']->get('system.images.cache_perms', '0755');
            $perms = octdec($perms);
            $file = $this->cache->setDirectoryMode($perms)->getOrCreateFile($cacheFile, $conditions, $generate, $actual);
        } catch (GenerationError $e) {
            $file = $e->getNewFile();
        }

        if ($actual) {
            return $file;
        } else {
            return $this->getFilename($file);
        }
    }
}
