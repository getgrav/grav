<?php
/**
 * @package    Grav.Common.Page
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Page;

use Grav\Common\Grav;
use Grav\Common\Page\Medium\AbstractMedia;
use Grav\Common\Page\Medium\GlobalMedia;
use Grav\Common\Page\Medium\MediumFactory;
use RocketTheme\Toolbox\File\File;
use Symfony\Component\Yaml\Yaml;

class Media extends AbstractMedia
{
    protected static $global;

    protected $path;

    protected $standard_exif = ['FileSize', 'MimeType', 'height', 'width'];

    /**
     * @param $path
     */
    public function __construct($path)
    {
        $this->path = $path;

        $this->__wakeup();
        $this->init();
    }

    /**
     * Initialize static variables on unserialize.
     */
    public function __wakeup()
    {
        if (!isset(static::$global)) {
            // Add fallback to global media.
            static::$global = new GlobalMedia();
        }
    }

    /**
     * @param mixed $offset
     *
     * @return bool
     */
    public function offsetExists($offset)
    {
        return parent::offsetExists($offset) ?: isset(static::$global[$offset]);
    }

    /**
     * @param mixed $offset
     *
     * @return mixed
     */
    public function offsetGet($offset)
    {
        return parent::offsetGet($offset) ?: static::$global[$offset];
    }

    /**
     * Initialize class.
     */
    protected function init()
    {
        $config = Grav::instance()['config'];
        $exif_reader = isset(Grav::instance()['exif']) ? Grav::instance()['exif']->getReader() : false;
        $media_types = array_keys(Grav::instance()['config']->get('media.types'));

        // Handle special cases where page doesn't exist in filesystem.
        if (!is_dir($this->path)) {
            return;
        }

        $iterator = new \FilesystemIterator($this->path, \FilesystemIterator::UNIX_PATHS | \FilesystemIterator::SKIP_DOTS);

        $media = [];

        /** @var \DirectoryIterator $info */
        foreach ($iterator as $path => $info) {
            // Ignore folders and Markdown files.
            if (!$info->isFile() || $info->getExtension() === 'md' || $info->getFilename()[0] === '.') {
                continue;
            }

            // Find out what type we're dealing with
            list($basename, $ext, $type, $extra) = $this->getFileParts($info->getFilename());

            if (!in_array(strtolower($ext), $media_types)) {
                continue;
            }

            if ($type === 'alternative') {
                $media["{$basename}.{$ext}"][$type][$extra] = [ 'file' => $path, 'size' => $info->getSize() ];
            } else {
                $media["{$basename}.{$ext}"][$type] = [ 'file' => $path, 'size' => $info->getSize() ];
            }
        }

        foreach ($media as $name => $types) {
            // First prepare the alternatives in case there is no base medium
            if (!empty($types['alternative'])) {
                foreach ($types['alternative'] as $ratio => &$alt) {
                    $alt['file'] = MediumFactory::fromFile($alt['file']);

                    if (!$alt['file']) {
                        unset($types['alternative'][$ratio]);
                    } else {
                        $alt['file']->set('size', $alt['size']);
                    }
                }
            }

            $file_path = null;

            // Create the base medium
            if (empty($types['base'])) {
                if (!isset($types['alternative'])) {
                    continue;
                }

                $max = max(array_keys($types['alternative']));
                $medium = $types['alternative'][$max]['file'];
                $file_path = $medium->path();
                $medium = MediumFactory::scaledFromMedium($medium, $max, 1)['file'];
            } else {
                $medium = MediumFactory::fromFile($types['base']['file']);
                $medium && $medium->set('size', $types['base']['size']);
                $file_path = $medium->path();
            }

            if (empty($medium)) {
                continue;
            }

            // metadata file
            $meta_path = $file_path . '.meta.yaml';

            if (file_exists($meta_path)) {
                $types['meta']['file'] = $meta_path;
            } elseif ($file_path && $medium->get('mime') === 'image/jpeg' && empty($types['meta']) && $config->get('system.media.auto_metadata_exif') && $exif_reader) {

                $meta = $exif_reader->read($file_path);

                if ($meta) {
                    $meta_data = $meta->getData();
                    $meta_trimmed = array_diff_key($meta_data, array_flip($this->standard_exif));
                    if ($meta_trimmed) {
                        $file = File::instance($meta_path);
                        $file->save(Yaml::dump($meta_trimmed));
                        $types['meta']['file'] = $meta_path;
                    }
                }
            }

            if (!empty($types['meta'])) {
                $medium->addMetaFile($types['meta']['file']);
            }

            if (!empty($types['thumb'])) {
                // We will not turn it into medium yet because user might never request the thumbnail
                // not wasting any resources on that, maybe we should do this for medium in general?
                $medium->set('thumbnails.page', $types['thumb']['file']);
            }

            // Build missing alternatives
            if (!empty($types['alternative'])) {
                $alternatives = $types['alternative'];
                $max = max(array_keys($alternatives));

                for ($i=$max; $i > 1; $i--) {
                    if (isset($alternatives[$i])) {
                        continue;
                    }

                    $types['alternative'][$i] = MediumFactory::scaledFromMedium($alternatives[$max]['file'], $max, $i);
                }

                foreach ($types['alternative'] as $altMedium) {
                    if ($altMedium['file'] != $medium) {
                        $altWidth = $altMedium['file']->get('width');
                        $medWidth = $medium->get('width');
                        if ($altWidth && $medWidth) {
                            $ratio = $altWidth / $medWidth;
                            $medium->addAlternative($ratio, $altMedium['file']);
                        }
                    }
                }
            }

            $this->add($name, $medium);
        }
    }

    /**
     * Enable accessing the media path
     *
     * @return mixed
     */
    public function path()
    {
        return $this->path;
    }
}
