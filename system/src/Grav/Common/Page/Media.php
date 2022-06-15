<?php

/**
 * @package    Grav\Common\Page
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Page;

use FilesystemIterator;
use Grav\Common\Config\Config;
use Grav\Common\Grav;
use Grav\Common\Media\Interfaces\MediaObjectInterface;
use Grav\Common\Yaml;
use Grav\Common\Page\Medium\AbstractMedia;
use Grav\Common\Page\Medium\GlobalMedia;
use Grav\Common\Page\Medium\MediumFactory;
use RocketTheme\Toolbox\File\File;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;
use function in_array;

/**
 * Class Media
 * @package Grav\Common\Page
 */
class Media extends AbstractMedia
{
    /** @var GlobalMedia */
    protected static $global;

    /** @var array */
    protected $standard_exif = ['FileSize', 'MimeType', 'height', 'width'];

    /**
     * @param string $path
     * @param array|null $media_order
     * @param bool   $load
     */
    public function __construct($path, array $media_order = null, $load = true)
    {
        $this->setPath($path);
        $this->media_order = $media_order;

        $this->__wakeup();
        if ($load) {
            $this->init();
        }
    }

    /**
     * Initialize static variables on unserialize.
     */
    public function __wakeup()
    {
        if (null === static::$global) {
            // Add fallback to global media.
            static::$global = GlobalMedia::getInstance();
        }
    }

    /**
     * Return raw route to the page.
     *
     * @return string|null Route to the page or null if media isn't for a page.
     */
    public function getRawRoute(): ?string
    {
        $path = $this->getPath();
        if ($path) {
            /** @var Pages $pages */
            $pages = $this->getGrav()['pages'];
            $page = $pages->get($path);
            if ($page) {
                return $page->rawRoute();
            }
        }

        return null;
    }

    /**
     * Return page route.
     *
     * @return string|null Route to the page or null if media isn't for a page.
     */
    public function getRoute(): ?string
    {
        $path = $this->getPath();
        if ($path) {
            /** @var Pages $pages */
            $pages = $this->getGrav()['pages'];
            $page = $pages->get($path);
            if ($page) {
                return $page->route();
            }
        }

        return null;
    }

    /**
     * @param string $offset
     * @return bool
     */
    #[\ReturnTypeWillChange]
    public function offsetExists($offset)
    {
        return parent::offsetExists($offset) ?: isset(static::$global[$offset]);
    }

    /**
     * @param string $offset
     * @return MediaObjectInterface|null
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return parent::offsetGet($offset) ?: static::$global[$offset];
    }

    /**
     * Initialize class.
     *
     * @return void
     */
    protected function init()
    {
        $path = $this->getPath();

        // Handle special cases where page doesn't exist in filesystem.
        if (!$path || !is_dir($path)) {
            return;
        }

        $grav = Grav::instance();

        /** @var UniformResourceLocator $locator */
        $locator = $grav['locator'];

        /** @var Config $config */
        $config = $grav['config'];

        $exif_reader = isset($grav['exif']) ? $grav['exif']->getReader() : null;
        $media_types = array_keys($config->get('media.types', []));

        $iterator = new FilesystemIterator($path, FilesystemIterator::UNIX_PATHS | FilesystemIterator::SKIP_DOTS);

        $media = [];

        foreach ($iterator as $file => $info) {
            // Ignore folders and Markdown files.
            $filename = $info->getFilename();
            if (!$info->isFile() || $info->getExtension() === 'md' || $filename === 'frontmatter.yaml' || $filename === 'media.json' || strpos($filename, '.') === 0) {
                continue;
            }

            // Find out what type we're dealing with
            [$basename, $ext, $type, $extra] = $this->getFileParts($filename);

            if (!in_array(strtolower($ext), $media_types, true)) {
                continue;
            }

            if ($type === 'alternative') {
                $media["{$basename}.{$ext}"][$type][$extra] = ['file' => $file, 'size' => $info->getSize()];
            } else {
                $media["{$basename}.{$ext}"][$type] = ['file' => $file, 'size' => $info->getSize()];
            }
        }

        foreach ($media as $name => $types) {
            // First prepare the alternatives in case there is no base medium
            if (!empty($types['alternative'])) {
                /**
                 * @var string|int $ratio
                 * @var array $alt
                 */
                foreach ($types['alternative'] as $ratio => &$alt) {
                    $alt['file'] = $this->createFromFile($alt['file']);

                    if (empty($alt['file'])) {
                        unset($types['alternative'][$ratio]);
                    } else {
                        $alt['file']->set('size', $alt['size']);
                    }
                }
                unset($alt);
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
                $medium = $this->createFromFile($types['base']['file']);
                if ($medium) {
                    $medium->set('size', $types['base']['size']);
                    $file_path = $medium->path();
                }
            }

            if (empty($medium)) {
                continue;
            }

            // metadata file
            $meta_path = $file_path . '.meta.yaml';

            if (file_exists($meta_path)) {
                $types['meta']['file'] = $meta_path;
            } elseif ($file_path && $exif_reader && $medium->get('mime') === 'image/jpeg' && empty($types['meta']) && $config->get('system.media.auto_metadata_exif')) {
                $meta = $exif_reader->read($file_path);

                if ($meta) {
                    $meta_data = $meta->getData();
                    $meta_trimmed = array_diff_key($meta_data, array_flip($this->standard_exif));
                    if ($meta_trimmed) {
                        if ($locator->isStream($meta_path)) {
                            $file = File::instance($locator->findResource($meta_path, true, true));
                        } else {
                            $file = File::instance($meta_path);
                        }
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
     * @return string|null
     * @deprecated 1.6 Use $this->getPath() instead.
     */
    public function path(): ?string
    {
        return $this->getPath();
    }
}
