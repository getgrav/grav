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
use RuntimeException;
use function count;
use function in_array;
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

    /** @var string[] */
    static public $ignore = ['frontmatter.yaml', 'media.json'];

    /** @var string */
    protected const VERSION = '1';

    /** @var string|null */
    protected $path;
    /** @var array */
    protected $index = [];
    /** @var array */
    protected $items = [];
    /** @var array|null */
    protected $media_order;
    /** @var array */
    protected $config = [];
    /** @var array */
    protected $standard_exif = ['FileSize', 'MimeType', 'height', 'width'];
    /** @var int */
    protected $indexTimeout = 0;
    /** @var array */
    protected $images = [];
    /** @var array */
    protected $videos = [];
    /** @var array */
    protected $audios = [];
    /** @var array */
    protected $files = [];

    /**
     * Return media path.
     *
     * @param string|null $filename
     * @return string|null
     */
    abstract public function getPath(string $filename = null): ?string;

    /**
     * @param string|null $path
     * @return void
     */
    abstract public function setPath(?string $path): void;

    /**
     * @return bool
     */
    abstract public function exists(): bool;

    /**
     * Get medium by filename.
     *
     * @param string $filename
     * @return MediaObjectInterface|null
     */
    public function get($filename): ?MediaObjectInterface
    {
        return $this->offsetGet($filename);
    }

    /**
     * Call object as function to get medium by filename.
     *
     * @param string $filename
     * @return MediaObjectInterface|null
     */
    #[\ReturnTypeWillChange]
    public function __invoke(string $filename): ?MediaObjectInterface
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
    public function all(): array
    {
        $this->items = $this->orderMedia($this->items);

        return $this->items;
    }

    /**
     * Get a list of all image media.
     *
     * @return MediaObjectInterface[]
     */
    public function images(): array
    {
        $this->images = $this->orderMedia($this->images);

        return $this->images;
    }

    /**
     * Get a list of all video media.
     *
     * @return MediaObjectInterface[]
     */
    public function videos(): array
    {
        $this->videos = $this->orderMedia($this->videos);

        return $this->videos;
    }

    /**
     * Get a list of all audio media.
     *
     * @return MediaObjectInterface[]
     */
    public function audios(): array
    {
        $this->audios = $this->orderMedia($this->audios);

        return $this->audios;
    }

    /**
     * Get a list of all file media.
     *
     * @return MediaObjectInterface[]
     */
    public function files(): array
    {
        $this->files = $this->orderMedia($this->files);

        return $this->files;
    }

    /**
     * @param string $name
     * @param MediaObjectInterface|null $file
     * @return void
     */
    public function add(string $name, ?MediaObjectInterface $file): void
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
    public function hide(string $name): void
    {
        $this->offsetUnset($name);

        unset($this->images[$name], $this->videos[$name], $this->audios[$name], $this->files[$name]);
    }

    /**
     * Create Medium from a file.
     *
     * @param  string $filename
     * @param  array  $params
     * @return Medium|null
     */
    abstract public function createFromFile($filename, array $params = []): ?MediaObjectInterface;

    /**
     * Create Medium from array of parameters
     *
     * @param  array          $items
     * @param  Blueprint|null $blueprint
     * @return Medium|null
     */
    abstract public function createFromArray(array $items = [], Blueprint $blueprint = null): ?MediaObjectInterface;

    /**
     * @param MediaObjectInterface $mediaObject
     * @return ImageFile
     */
    abstract public function getImageFileObject(MediaObjectInterface $mediaObject): ImageFile;

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
            throw new RuntimeException('Cannot unserialize: version mismatch');
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
     * @param string $filepath
     * @return string
     * @throws RuntimeException
     */
    abstract public function readFile(string $filepath): string;

    /**
     * @param string $filepath
     * @return resource
     * @throws RuntimeException
     */
    abstract public function readStream(string $filepath);

    /**
     * Order the media based on the page's media_order
     *
     * @param array $media
     * @return array
     */
    protected function orderMedia(array $media): array
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
     * @param string $filename
     * @param string $destination
     * @return bool
     */
    abstract protected function fileExists(string $filename, string $destination): bool;

    /**
     * @param string $filepath
     * @return array
     */
    abstract protected function readImageSize(string $filepath): array;

    /**
     * @param string $filepath
     * @return array
     */
    protected function readVectorSize(string $filepath): array
    {
        // Make sure that getting image size is supported.
        if (\extension_loaded('simplexml')) {
            $data = $this->readFile($filepath);
            $xml = @simplexml_load_string($data);
            $attr = $xml ? $xml->attributes() : null;
            if ($attr instanceof \SimpleXMLElement) {
                // Get the size from svg image.
                if ($attr->width > 0 && $attr->height > 0) {
                    $width = $attr->width;
                    $height = $attr->height;
                } elseif ($attr->viewBox && 4 === count($size = explode(' ', (string)$attr->viewBox))) {
                    [,$width,$height,] = $size;
                }

                if (isset($width, $height)) {
                    return ['width' => (int)$width, 'height' => (int)$height, 'mime' => 'image/svg+xml'];
                }
            }

            throw new RuntimeException(sprintf('Cannot read image size from %s', $filepath));
        }

        return [];
    }

    /**
     * Load file listing from the filesystem.
     *
     * @return array
     */
    abstract protected function loadFileInfo(): array;

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
        foreach ($files as $filename => $info) {
            // Ignore markdown, frontmatter and dot files. Also ignore all files which are not listed in media types.
            $extension = $info['extension'] ?? '';
            $params = $media_types[strtolower($extension)] ?? [];
            if (!$params || $extension === 'md' || str_starts_with($filename, '.') || in_array($filename, static::$ignore, true)) {
                continue;
            }

            $type = $params['type'] ?? 'file';

            $info['type'] = $type;
            $info['mime'] = $params['mime'];
            $info['basename'] = $info['filename'];
            unset($info['dirname'], $info['filename']);

            if (null !== $cached) {
                try {
                    $filepath = $this->getPath($filename);
                    $existing = $cached[$filename] ?? null;
                    if ($existing && $existing['size'] === $info['size'] && $existing['modified'] === $info['modified']) {
                        // Append cached data.
                        $info += $existing;
                    } else if ($type === 'image') {
                        $info += $this->readImageSize($filepath);
                    } elseif ($type === 'vector') {
                        $info += $this->readVectorSize($filepath);
                    }
                } catch (RuntimeException $e) {
                    // TODO: Maybe we want to handle this..?
                }
            }

            $list[$filename] = $info;
        }

        return $list;
    }

    /**
     * @param array|null $info
     * @return void
     */
    protected function addMediaDefaults(?array &$info): void
    {
        if (!is_array($info)) {
            $info = null;

            return;
        }

        $config = $this->getConfig();
        $ext = $info['extension'] ?? '';
        $media_params = $ext ? $config->get('media.types.' . strtolower($ext)) : null;
        if (!is_array($media_params)) {
            $info = null;

            return;
        }

        if (!isset($info['filename'])) {
            $info['filename'] = $info['basename'] . ($ext ? '.' . $ext : '');
        }
        if (!isset($info['path'])) {
            $info['path'] = $this->getPath();
        }
        if (!isset($info['filepath'])) {
            $info['filepath'] = $this->getPath($info['filename']);
        }

        // Remove empty 'image' attribute
        if (isset($media_params['image']) && empty($media_params['image'])) {
            unset($media_params['image']);
        }

        // Add default settings for undefined variables.
        $info += $media_params + (array)$config->get('media.types.defaults');
        $info += [
            'thumb' => 'media/thumb.png',
            'path' => $this->getPath(),
            'thumbnails' => []
        ];

        /** @var UniformResourceLocator $locator */
        $locator = $this->getGrav()['locator'];

        $file = $locator->findResource("image://{$info['thumb']}");
        if ($file) {
            $info['thumbnails']['default'] = $file;
        }
    }

    /**
     * Initialize class.
     *
     * @return void
     */
    protected function init(): void
    {
        // Handle special cases where page doesn't exist in filesystem.
        if (!$this->exists()) {
            return;
        }

        $config = $this->getConfig();

        // Get file media listing. Use cached version if possible to avoid I/O.
        $now = time();
        [$files, $timestamp] = $this->loadIndex();
        $timeout = $this->indexTimeout;
        if (!$timestamp || ($timeout && $timestamp < $now - $timeout)) {
            $media_types = $config->get('media.types');
            $files = $this->prepareFileInfo($this->loadFileInfo(), $media_types, $files);

            $this->saveIndex($files, $now);
        }

        $this->index = $files;

        // Group images by base name.
        $media = [];
        foreach ($files as $filename => $info) {
            // Find out what type we're dealing with
            [$basename, $extension, $type, $extra] = $this->getFileParts($filename);

            $info['file'] = $this->getPath($filename);
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
                    $alt['file'] = $this->createFromFile($alt['file']);
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
                $medium = $this->createFromFile($types['base']['file']);
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
     * Get filename, extension and meta part.
     *
     * @param  string $filename
     * @return array
     */
    protected function getFileParts(string $filename): array
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
