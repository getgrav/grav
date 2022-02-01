<?php

/**
 * @package    Grav\Common\Page
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Page\Medium;

use Grav\Common\Config\Config;
use Grav\Common\Data\Blueprint;
use Grav\Common\File\CompiledYamlFile;
use Grav\Common\Grav;
use Grav\Common\Language\Language;
use Grav\Common\Media\Interfaces\MediaCollectionInterface;
use Grav\Common\Media\Interfaces\MediaObjectInterface;
use Grav\Common\Media\Interfaces\MediaUploadInterface;
use Grav\Common\Media\Traits\MediaUploadTrait;
use Grav\Common\Page\Pages;
use Grav\Common\Utils;
use Grav\Framework\Compat\Serializable;
use PHPExif\Reader\Reader;
use RocketTheme\Toolbox\ArrayTraits\ArrayAccess;
use RocketTheme\Toolbox\ArrayTraits\Countable;
use RocketTheme\Toolbox\ArrayTraits\Export;
use RocketTheme\Toolbox\ArrayTraits\ExportInterface;
use RocketTheme\Toolbox\ArrayTraits\Iterator;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;
use function is_array;

/**
 * Class AbstractMedia
 * @package Grav\Common\Page\Medium
 */
abstract class AbstractMedia implements ExportInterface, MediaCollectionInterface, MediaUploadInterface, \Serializable
{
    use ArrayAccess;
    use Countable;
    use Iterator;
    use Export;
    use MediaUploadTrait;
    use Serializable;

    /** @var string */
    protected const VERSION = '1';

    /** @var array */
    protected $index = [];
    /** @var array */
    protected $items = [];
    /** @var string|null */
    protected $path;
    /** @var array */
    protected $images = [];
    /** @var array */
    protected $videos = [];
    /** @var array */
    protected $audios = [];
    /** @var array */
    protected $files = [];
    /** @var array|null */
    protected $media_order;
    /** @var array */
    protected $standard_exif = ['FileSize', 'MimeType', 'height', 'width'];
    /** @var int */
    protected $indexTimeout = 0;

    /**
     * Return media path.
     *
     * @return string|null
     */
    public function getPath(): ?string
    {
        return $this->path;
    }

    /**
     * @param string|null $path
     * @return void
     */
    public function setPath(?string $path): void
    {
        $this->path = $path;
    }

    /**
     * @return bool
     */
    public function exists(): bool
    {
        $path = $this->getPath();

        return $path && is_dir($path);
    }

    /**
     * Get medium by filename.
     *
     * @param string $filename
     * @return MediaObjectInterface|null
     */
    public function get($filename)
    {
        return $this->offsetGet($filename);
    }

    /**
     * Call object as function to get medium by filename.
     *
     * @param string $filename
     * @return mixed
     */
    #[\ReturnTypeWillChange]
    public function __invoke($filename)
    {
        return $this->offsetGet($filename);
    }

    /**
     * Set file modification timestamps (query params) for all the media files.
     *
     * @param string|int|null $timestamp
     * @return $this
     */
    public function setTimestamps($timestamp = null)
    {
        foreach ($this->items as $instance) {
            $instance->setTimestamp($timestamp);
        }

        return $this;
    }

    /**
     * Get a list of all media.
     *
     * @return MediaObjectInterface[]
     */
    public function all()
    {
        $this->items = $this->orderMedia($this->items);

        return $this->items;
    }

    /**
     * Get a list of all image media.
     *
     * @return MediaObjectInterface[]
     */
    public function images()
    {
        $this->images = $this->orderMedia($this->images);

        return $this->images;
    }

    /**
     * Get a list of all video media.
     *
     * @return MediaObjectInterface[]
     */
    public function videos()
    {
        $this->videos = $this->orderMedia($this->videos);

        return $this->videos;
    }

    /**
     * Get a list of all audio media.
     *
     * @return MediaObjectInterface[]
     */
    public function audios()
    {
        $this->audios = $this->orderMedia($this->audios);

        return $this->audios;
    }

    /**
     * Get a list of all file media.
     *
     * @return MediaObjectInterface[]
     */
    public function files()
    {
        $this->files = $this->orderMedia($this->files);

        return $this->files;
    }

    /**
     * @param string $name
     * @param MediaObjectInterface|null $file
     * @return void
     */
    public function add($name, $file)
    {
        if (null === $file) {
            return;
        }

        $this->offsetSet($name, $file);

        switch ($file->type) {
            case 'image':
                $this->images[$name] = $file;
                break;
            case 'video':
                $this->videos[$name] = $file;
                break;
            case 'audio':
                $this->audios[$name] = $file;
                break;
            default:
                $this->files[$name] = $file;
        }
    }

    /**
     * @param string $name
     * @return void
     */
    public function hide($name)
    {
        $this->offsetUnset($name);

        unset($this->images[$name], $this->videos[$name], $this->audios[$name], $this->files[$name]);
    }

    /**
     * Create Medium from a file.
     *
     * @param  string $file
     * @param  array  $params
     * @return Medium|null
     */
    public function createFromFile($file, array $params = [])
    {
        return MediumFactory::fromFile($file, $params);
    }

        /**
     * Create Medium from array of parameters
     *
     * @param  array          $items
     * @param  Blueprint|null $blueprint
     * @return Medium|null
     */
    public function createFromArray(array $items = [], Blueprint $blueprint = null)
    {
        return MediumFactory::fromArray($items, $blueprint);
    }

    /**
     * @param string $path
     * @return array
     */
    public function readImageSize(string $path): array
    {
        return getimagesize($path);
    }

    /**
     * @param MediaObjectInterface $mediaObject
     * @return ImageFile
     */
    public function getImageFileObject(MediaObjectInterface $mediaObject): ImageFile
    {
        $path = $mediaObject->get('filepath');

        return ImageFile::open($path);
    }

    /**
     * @return array
     */
    public function __serialize(): array
    {
        return [
            'version' => static::VERSION,
            'index' => $this->index,
            'items' => $this->items,
            'path' => $this->path,
            'media_order' => $this->media_order,
            'standard_exif' => $this->standard_exif,
            'indexTimeout' => $this->indexTimeout
        ];
    }

    /**
     * @param array $data
     * @return void
     */
    public function __unserialize(array $data): void
    {
        $version = $data['version'] ?? null;
        if ($version !== static::VERSION) {
            throw new \RuntimeException('Cannot unserialize: version mismatch');
        }

        $this->index = $data['index'];
        $this->path = $data['path'];
        $this->media_order = $data['media_order'];
        $this->standard_exif = $data['standard_exif'];
        $this->indexTimeout = $data['indexTimeout'];
        $items = $data['items'];
        foreach ($items as $name => $item) {
            $this->add($name, $item);
        }
    }

    /**
     * Order the media based on the page's media_order
     *
     * @param array $media
     * @return array
     */
    protected function orderMedia($media)
    {
        if (null === $this->media_order) {
            $path = $this->getPath();
            if (null !== $path) {
                /** @var Pages $pages */
                $pages = Grav::instance()['pages'];
                $page = $pages->get($path);
                if ($page && isset($page->header()->media_order)) {
                    $this->media_order = array_map('trim', explode(',', $page->header()->media_order));
                }
            }
        }

        if (!empty($this->media_order) && is_array($this->media_order)) {
            $media = Utils::sortArrayByArray($media, $this->media_order);
        } else {
            ksort($media, SORT_NATURAL | SORT_FLAG_CASE);
        }

        return $media;
    }

    /**
     * Prepare file information for media.
     *
     * Removes all non-media files and adds some additional metadata.
     *
     * @param iterable $files
     * @param array $media_types
     * @param array|null $cached
     * @return array
     */
    protected function prepareFileInfo(iterable $files, array $media_types, ?array $cached): array
    {
        //$exifReader = $this->getExifReader();

        $list = [];
        foreach ($files as $filepath => $info) {
            // Ignore markdown, frontmatter and dot files. Also ignore all files which are not listed in media types.
            $basename = $info['basename'];
            $extension = $info['extension'] ?? '';
            $params = $media_types[strtolower($extension)] ?? [];
            if (!$params || $extension === 'md' || $basename === 'frontmatter.yaml' || str_starts_with($basename, '.')) {
                continue;
            }

            $filename = $info['filename'];

            $type = $params['type'] ?? 'file';
            $info['type'] = $type;
            $info['mime'] = $params['mime'];
            if ($info['dirname'] === '.') {
                $info['dirname'] = '';
            }
            if (!isset($info['filepath'])) {
                $info['filepath'] = $filepath;
            }
            $info['basename'] = $filename;
            $info['filename'] = $basename;

            if (null !== $cached) {
                $existing = $cached[$filepath] ?? null;
                if ($existing && $existing['size'] === $info['size'] && $existing['modified'] === $info['modified']) {
                    // Append cached data.
                    $info += $existing;
                } elseif ($type === 'image') {
                    // Cached data cannot be used, load the image from the filesystem and read the image size.
                    $image_info = $this->readImageSize($filepath);
                    if ($image_info) {
                        [$width, $height] = $image_info;
                        $info += [
                            'width' => $width,
                            'height' => $height
                        ];
                    }

                    // TODO: This is going to be slow without any indexing!
                    /*
                    // Add missing jpeg exif data.
                    if (null !== $exifReader && !isset($info['exif']) && $info['mime'] === 'image/jpeg') {
                        $exif = $exifReader->read($filepath);
                        if ($exif) {
                            $info['exif'] = array_diff_key($exif->getData(), array_flip($this->standard_exif));
                        }
                    }
                    */
                }
            }

            $list[$filepath] = $info;
        }

        return $list;
    }

    /**
     * Initialize class.
     *
     * @return void
     */
    protected function init()
    {
        // Handle special cases where page doesn't exist in filesystem.
        if (!$this->exists()) {
            return;
        }

        $config = $this->getConfig();

        // Get file media listing. Use cached version if possible to avoid I/O.
        $now = time();
        [$files, $timestamp] = $this->loadIndex();
        if ($timestamp < $now - $this->indexTimeout) {
            $media_types = $config->get('media.types');
            $files = $this->prepareFileInfo($this->loadFileInfo(), $media_types, $files);

            $this->saveIndex($files, $now);
        }

        $this->index = $files;

        // Group images by base name.
        $media = [];
        foreach ($files as $filepath => $info) {
            // Find out what type we're dealing with
            [$basename, $extension, $type, $extra] = $this->getFileParts($info['filename']);

            $info['file'] = $filepath;
            $filename = "{$basename}.{$extension}";
            if ($type === 'alternative') {
                $media[$filename][$type][$extra] = $info;
            } elseif (isset($media[$filename][$type])) {
                $media[$filename][$type] += $info;
            } else {
                $media[$filename][$type] = $info;
            }
        }

        // Prepare the alternatives in case there is no base medium.
        foreach ($media as $name => $types) {
            if (!empty($types['alternative'])) {
                /**
                 * @var string|int $ratio
                 * @var array $alt
                 */
                foreach ($types['alternative'] as $ratio => &$alt) {
                    $alt['file'] = $this->createFromFile($alt['file'], $alt);
                    if (empty($alt['file'])) {
                        unset($types['alternative'][$ratio]);
                    }
                }
                unset($alt);
            }

            // Create the base medium.
            $file_path = null;
            if (empty($types['base'])) {
                if (!isset($types['alternative'])) {
                    continue;
                }

                $max = max(array_keys($types['alternative']));
                $medium = $types['alternative'][$max]['file'];
                $file_path = $medium->path();
                $medium = MediumFactory::scaledFromMedium($medium, $max, 1)['file'];
            } else {
                $medium = $this->createFromFile($types['base']['file'], $types['base']);
                if ($medium) {
                    $medium->set('size', $types['base']['size']);
                    $file_path = $medium->path();
                }
            }

            if (empty($medium)) {
                continue;
            }

            if ($file_path) {
                $meta_path = $file_path . '.meta.yaml';
                if (file_exists($meta_path)) {
                    $types['meta']['file'] = $meta_path;
                } elseif ($exifReader = $this->getExifReader()) {
                    $meta = $exifReader->read($file_path);
                    if ($meta) {
                        $meta_data = $meta->getData();
                        $meta_trimmed = array_diff_key($meta_data, array_flip($this->standard_exif));
                        if ($meta_trimmed) {
                            $locator = $this->getGrav()['locator'];
                            if ($locator->isStream($meta_path)) {
                                $file = CompiledYamlFile::instance($locator->findResource($meta_path, true, true));
                            } else {
                                $file = CompiledYamlFile::instance($meta_path);
                            }
                            $file->save($meta_trimmed);
                            $types['meta']['file'] = $meta_path;
                        }
                    }
                }
            }

            if (!empty($types['meta']['file'])) {
                $medium->addMetaFile($types['meta']['file']);
            }

            if (!empty($types['thumb']['file'])) {
                // We will not turn it into medium yet because user might never request the thumbnail
                // not wasting any resources on that, maybe we should do this for medium in general?
                $medium->set('thumbnails.page', $types['thumb']['file']);
            }

            // Build missing alternatives.
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
                            $ratio = (string)($altWidth / $medWidth);
                            $medium->addAlternative($ratio, $altMedium['file']);
                        }
                    }
                }
            }

            $this->add($name, $medium);
        }
    }

    /**
     * @return array
     */
    protected function loadIndex(): array
    {
        return [[], 0];
    }

    /**
     * @param array $files
     * @param int|null $timestamp
     * @return void
     */
    protected function saveIndex(array $files, ?int $timestamp = null): void
    {
    }

    /**
     * @param string $filename
     * @param string $destination
     * @return bool
     */
    protected function fileExists(string $filename, string $destination): bool
    {
        return file_exists("{$destination}/{$filename}");
    }

    /**
     * Get filename, extension and meta part.
     *
     * @param  string $filename
     * @return array
     */
    protected function getFileParts($filename)
    {
        if (preg_match('/(.*)@(\d+)x\.(.*)$/', $filename, $matches)) {
            $name = $matches[1];
            $extension = $matches[3];
            $extra = (int) $matches[2];
            $type = 'alternative';

            if ($extra === 1) {
                $type = 'base';
                $extra = null;
            }
        } else {
            $fileParts = explode('.', $filename);

            $name = array_shift($fileParts);
            $extension = null;
            $extra = null;
            $type = 'base';

            while (($part = array_shift($fileParts)) !== null) {
                if ($part !== 'meta' && $part !== 'thumb') {
                    if (null !== $extension) {
                        $name .= '.' . $extension;
                    }
                    $extension = $part;
                } else {
                    $type = $part;
                    $extra = '.' . $part . '.' . implode('.', $fileParts);
                    break;
                }
            }
        }

        return [$name, $extension, $type, $extra];
    }

    /**
     * @return Grav
     */
    protected function getGrav(): Grav
    {
        return Grav::instance();
    }

    /**
     * @return Config
     */
    protected function getConfig(): Config
    {
        return $this->getGrav()['config'];
    }

    /**
     * @return Language
     */
    protected function getLanguage(): Language
    {
        return $this->getGrav()['language'];
    }

    /**
     * @return Reader|null
     */
    protected function getExifReader(): ?Reader
    {
        $grav = $this->getGrav();
        $config = $this->getConfig();
        $exifEnabled = !empty($config->get('system.media.auto_metadata_exif'));

        /** @var Reader|null $exifReader */
        return $exifEnabled && isset($grav['exif']) ? $grav['exif']->getReader() : null;
    }

    /**
     * @return void
     */
    protected function clearCache(): void
    {
        /** @var UniformResourceLocator $locator */
        $locator = $this->getGrav()['locator'];
        $locator->clearCache();
    }
}
