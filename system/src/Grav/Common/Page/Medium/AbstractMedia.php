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
use Grav\Common\Debugger;
use Grav\Common\File\CompiledYamlFile;
use Grav\Common\Grav;
use Grav\Common\Language\Language;
use Grav\Common\Media\Interfaces\MediaCollectionInterface;
use Grav\Common\Media\Interfaces\MediaObjectInterface;
use Grav\Common\Media\Interfaces\MediaUploadInterface;
use Grav\Common\Media\MediaIndex;
use Grav\Common\Media\Traits\MediaUploadTrait;
use Grav\Common\Page\Pages;
use Grav\Common\Utils;
use Grav\Framework\Compat\Serializable;
use InvalidArgumentException;
use PHPExif\Reader\Reader;
use RocketTheme\Toolbox\ArrayTraits\Export;
use RocketTheme\Toolbox\ArrayTraits\ExportInterface;
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
    use Export;
    use MediaUploadTrait;
    use Serializable;

    /** @var string[] */
    static public $ignore = ['frontmatter.yaml', 'media.json'];

    /** @var string */
    protected const VERSION = '1';

    /** @var string|null */
    protected $path;
    /** @var string|null */
    protected $url;
    /** @var bool */
    protected $exists = false;
    /** @var array|null */
    protected $index;
    /** @var array|null */
    protected $grouped;
    /** @var array<string,array|MediaObjectInterface> */
    protected $items = [];
    /** @var array|null */
    protected $media_order;
    /** @var array */
    protected $config = [];
    /** @var array */
    protected $standard_exif = ['FileSize', 'MimeType', 'height', 'width'];
    /** @var string|null */
    protected $indexFolder;
    /** @var string|null */
    protected $indexFile = 'media.json';
    /** @var int */
    protected $indexTimeout = 0;
    /** @var string|int|null */
    protected $timestamp;
    /** @var bool Hack to make Iterator work together with unset(). */
    private $iteratorUnset = false;

    /**
     * @return string
     */
    public function getId(): string
    {
        return md5($this->getType() . ':' . $this->path);
    }

    /**
     * @return string
     */
    abstract public function getType(): string;

    /**
     * @return string
     */
    abstract public function getName(): string;

    /**
     * Return media path.
     *
     * @param string|null $filename
     * @return string|null
     */
    abstract public function getPath(string $filename = null): ?string;

    /**
     * @param string $filename
     * @return string
     */
    abstract public function getUrl(string $filename): string;

    /**
     * @param string|null $path
     * @return void
     */
    abstract public function setPath(?string $path): void;

    /**
     * @return bool
     */
    public function exists(): bool
    {
        return $this->exists;
    }

    /**
     * @return int
     */
    public function count(): int
    {
        return count($this->items);
    }

    /**
     * @param string $offset
     * @return bool
     */
    public function offsetExists($offset): bool
    {
        return isset($this->items[$offset]);
    }

    /**
     * @param string $offset
     * @return MediaObjectInterface|null
     */
    public function offsetGet($offset): ?MediaObjectInterface
    {
        $instance = $this->items[$offset] ?? null;
        if ($instance && !$instance instanceof MediaObjectInterface) {
            // Initialize media object.
            $this->items[$offset] = $instance = $this->initMedium($offset);
        }

        return $instance ? $instance->setTimestamp($this->timestamp) : null;
    }

    /**
     * @param string|null $offset
     * @param MediaObjectInterface $value
     * @return void
     */
    public function offsetSet($offset, $value): void
    {
        if (!$value instanceof MediaObjectInterface) {
            throw new InvalidArgumentException('Parameter $value needs to be instance of MediaObjectInterface');
        }

        if (null === $offset) {
            $this->items[$value->filename] = $value;
        } else {
            $this->items[$offset] = $value;
        }
    }

    /**
     * @param string $offset
     * @return void
     */
    public function offsetUnset($offset): void
    {
        // Hack to make Iterator trait work together with unset.
        if ((string)$offset === (string)key($this->items)) {
            $this->iteratorUnset = true;
        }

        unset($this->items[$offset]);
    }

    /**
     * @return MediaObjectInterface|null
     */
    public function current(): ?MediaObjectInterface
    {
        $instance = current($this->items);
        if ($instance && !$instance instanceof MediaObjectInterface) {
            // Initialize media object.
            $key = $this->key();
            $this->items[$key] = $instance = $this->initMedium($key);
        }

        return $instance ? $instance->setTimestamp($this->timestamp) : null;
    }

    /**
     * @return string|null
     */
    public function key(): ?string
    {
        $key = key($this->items);

        return $key !== null ? (string)$key : null;
    }

    /**
     * @return void
     */
    public function next(): void
    {
        if ($this->iteratorUnset) {
            // If current item was unset, position is already in the next element (do nothing).
            $this->iteratorUnset = false;
        } else {
            next($this->items);
        }
    }

    /**
     * @return void
     */
    public function rewind(): void
    {
        $this->iteratorUnset = false;
        reset($this->items);
    }

    /**
     * @return bool
     */
    public function valid(): bool
    {
        return key($this->items) !== null;
    }

    /**
     * Set file modification timestamps (query params) for all the media files.
     *
     * @param string|int|null $timestamp
     * @return $this
     */
    public function setTimestamps($timestamp = null)
    {
        $this->timestamp = $timestamp;

        return $this;
    }

    /**
     * Get a list of all media.
     *
     * @return array<string,MediaObjectInterface>
     */
    public function all(): array
    {
        // Reorder.
        $this->items = $this->orderMedia($this->items);

        $list = [];
        foreach ($this as $filename => $instance) {
            $list[$filename] = $instance;
        }

        return $list;
    }

    /**
     * Get a list of all image media.
     *
     * @return array<string,MediaObjectInterface>
     */
    public function images(): array
    {
        $list = [];
        foreach ($this->all() as $filename => $file) {
            if ($file->type === 'image') {
                $list[$filename] = $file;
            }
        }

        return $list;
    }

    /**
     * Get a list of all video media.
     *
     * @return array<string,MediaObjectInterface>
     */
    public function videos(): array
    {
        $list = [];
        foreach ($this->all() as $filename => $file) {
            if ($file->type === 'video') {
                $list[$filename] = $file;
            }
        }

        return $list;
    }

    /**
     * Get a list of all audio media.
     *
     * @return array<string,MediaObjectInterface>
     */
    public function audios(): array
    {
        $list = [];
        foreach ($this->all() as $filename => $file) {
            if ($file->type === 'audio') {
                $list[$filename] = $file;
            }
        }

        return $list;
    }

    /**
     * Get a list of all file media.
     *
     * @return array<string,MediaObjectInterface>
     */
    public function files(): array
    {
        $list = [];
        foreach ($this->all() as $filename => $file) {
            if (!in_array($file->type, ['image', 'video', 'audio'])) {
                $list[$filename] = $file;
            }
        }

        return $list;
    }

    /**
     * Get medium by filename.
     *
     * @param string $filename
     * @return MediaObjectInterface|null
     */
    public function get(string $filename): ?MediaObjectInterface
    {
        return $this->offsetGet($filename);
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
    }

    /**
     * @param string $name
     * @return void
     */
    public function hide(string $name): void
    {
        $this->offsetUnset($name);
    }

    /**
     * @param array|null $files
     * @return void
     */
    public function updateIndex(array $files = null): void
    {
        $mediaIndex = $this->getIndex();
        if (!$mediaIndex) {
            return;
        }

        $mediaIndex->lock();

        $id = $this->getId();
        [$index,] = $mediaIndex->get($id, true);

        if ($files === null) {
            $files = $index['files'] ?? [];
            $timestamp = 0;
        } else {
            // Add new files and remove the old ones.
            $files += $index['files'] ?? [];
            $files = array_filter($files, static function($val) { return $val !== null; } );
            $timestamp = time();
        }

        $index = $this->generateIndex($files, null, $timestamp);

        $mediaIndex->save($id, $index);
    }

    /**
     * Create Medium from a file.
     *
     * @param  string $filename
     * @param  array  $params
     * @return Medium|null
     */
    abstract public function createFromFile(string $filename, array $params = []): ?MediaObjectInterface;

    /**
     * Create a new ImageMedium by scaling another ImageMedium object.
     *
     * @param  MediaObjectInterface $medium
     * @param  int $from
     * @param  int $to
     * @return MediaObjectInterface|null
     */
    abstract public function scaledFromMedium(MediaObjectInterface $medium, int $from, int $to = 1): ?MediaObjectInterface;

    /**
     * Create Medium from array of parameters
     *
     * @param  array          $items
     * @param  Blueprint|null $blueprint
     * @return Medium|null
     */
    abstract public function createFromArray(array $items = [], Blueprint $blueprint = null): ?MediaObjectInterface;

    /**
     * @return array
     */
    public function __serialize(): array
    {
        return [
            'version' => static::VERSION,
            'index' => $this->index ?? [],
            'grouped' => $this->grouped,
            'path' => $this->path,
            'url' => $this->url,
            'exists' => $this->exists,
            'media_order' => $this->media_order,
            'standard_exif' => $this->standard_exif,
            'indexFolder' => $this->indexFolder,
            'indexFile' => $this->indexFile,
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
        $this->grouped = $data['grouped'];
        $this->path = $data['path'];
        $this->url = $data['url'];
        $this->exists = $data['exists'];
        $this->media_order = $data['media_order'];
        $this->standard_exif = $data['standard_exif'];
        $this->indexFolder = $data['indexFolder'];
        $this->indexFile = $data['indexFile'];
        $this->indexTimeout = $data['indexTimeout'];

        // Initialize items.
        $this->items = $this->grouped;
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
                if ($attr->width && $attr->height) {
                    $width = (string)$attr->width;
                    $height = (string)$attr->height;
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
        $list = [];
        foreach ($files as $filename => $info) {
            // Ignore markdown, frontmatter and dot files. Also ignore all files which are not listed in media types.
            $extension = Utils::pathinfo($filename, PATHINFO_EXTENSION);
            $params = $media_types[strtolower($extension)] ?? [];
            if (!$params || $extension === 'md' || str_starts_with($filename, '.') || in_array($filename, static::$ignore, true)) {
                continue;
            }

            $info['mime'] = null;
            if (null !== $cached) {
                try {
                    $type = $params['type'] ?? 'file';
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
            if (!isset($info['mime'])) {
                $info['mime'] = $params['mime'];
            }

            $list[$filename] = $info;
        }

        ksort($list, SORT_NATURAL);

        return $list;
    }

    /**
     * @param string $filename
     * @param array|null $info
     * @return void
     */
    protected function addMediaDefaults(string $filename, ?array &$info): void
    {
        if (null === $info) {
            return;
        }

        $pathInfo = Utils::pathinfo($filename);
        $info['basename'] = $pathInfo['filename'];
        $info['filename'] = $pathInfo['basename'];
        if (!isset($info['path'])) {
            $info['path'] = $pathInfo['dirname'] === '.' ? $this->getPath() : $pathInfo['dirname'];
        }
        unset($pathInfo['dirname'], $pathInfo['basename']);
        $info += $pathInfo;

        $config = $this->getConfig();
        $ext = $info['extension'] ?? '';
        $media_params = $ext ? $config->get('media.types.' . strtolower($ext)) : null;
        if (!is_array($media_params)) {
            $info = null;

            return;
        }

        if (!isset($info['filepath'])) {
            $info['filepath'] = $info['path'] . '/' . $info['filename'];
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
        if (null === $this->index) {
            $now = time();
            [$data, $timestamp] = $this->loadIndex();
            $timeout = $this->indexTimeout;
            if (!$timestamp || ($timeout && $timestamp < $now - $timeout)) {
                $media_types = $config->get('media.types');
                $files = $this->prepareFileInfo($this->loadFileInfo(), $media_types, $data['files'] ?? []);
                $oldChecksum = $data['checksum'] ?? null;
                $newChecksum = md5(serialize($files));
                if ($oldChecksum !== $newChecksum) {
                    $this->saveIndex($files, $newChecksum, $now);
                } else {
                    $this->touchIndex($now);
                }
            } else {
                $files = $data['files'] ?? [];
            }

            $this->index = $files;
        }

        // Group images by base name.
        $media = [];
        foreach ($this->index as $filename => $info) {
            // Find out what type we're dealing with
            [$basename, $extension, $type, $extra] = $this->getFileParts($filename);

            $info['filename'] = $filename;
            $info['file'] = $filename;
            if ($this->url) {
                $info['url'] = "{$this->url}/{$filename}";
            }
            $filename = "{$basename}.{$extension}";
            if ($type === 'alternative') {
                $media[$filename][$type][$extra] = $info;
            } elseif (isset($media[$filename][$type])) {
                $media[$filename][$type] += $info;
            } else {
                $media[$filename][$type] = $info;
            }
        }

        $media = $this->orderMedia($media);

        $this->grouped = $media;
        $this->items = $media;
    }

    /**
     * @param string $name
     * @return MediaObjectInterface|null
     */
    protected function initMedium(string $name): ?MediaObjectInterface
    {
        $types = $this->grouped[$name];

        // Prepare the alternatives in case there is no base medium.
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
                return null;
            }

            $max = max(array_keys($types['alternative']));
            $medium = $types['alternative'][$max]['file'];
            $file_path = $medium->path();
            $medium = $this->scaledFromMedium($medium, $max);
        } else {
            $medium = $this->createFromFile($types['base']['file']);
            if ($medium) {
                $medium->set('size', $types['base']['size']);
                $file_path = $medium->path();
            }
        }

        if (!$medium) {
            return null;
        }

        if ($file_path) {
            $meta_path = $file_path . '.meta.yaml';
            if (file_exists($meta_path)) {
                $types['meta']['file'] = $meta_path;
            } elseif ($exifReader = $this->getExifReader()) {
                try {
                    $meta = $exifReader->read($file_path);
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
                } catch (RuntimeException $e) {
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
                if (!isset($alternatives[$i])) {
                    $scaled = $this->scaledFromMedium($alternatives[$max]['file'], $max, $i);
                    if ($scaled) {
                        $types['alternative'][$i] = $scaled;
                    } else {
                        /** @var Debugger $debugger */
                        $debugger = Grav::instance()['debugger'];
                        $debugger->addMessage(sprintf('Could not create alternative image for %s: %s', $medium->filename, $e->getMessage()), 'warning');
                    }
                }
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

        return $medium;
    }

    /**
     * @param array $files
     * @param string|null $checksum
     * @param int|null $timestamp
     * @return array
     */
    protected function generateIndex(array $files, string $checksum = null, ?int $timestamp = null): array
    {
        ksort($files, SORT_NATURAL);

        return [
            'type' => $this->getType(),
            'version' => static::VERSION,
            'checksum' => $checksum ?? md5(serialize($files)),
            'timestamp' => $timestamp ?? time(),
            'folder' => $this->path,
            'url' => $this->url,
            'files' => $files,
        ];
    }

    /**
     * Get index file, which stores media file index.
     *
     * @return MediaIndex|null
     */
    protected function getIndex(): ?MediaIndex
    {
        if (null === $this->indexFolder || null === $this->indexFile) {
            return null;
        }

        $indexFolder = $this->indexFolder;
        if (!str_starts_with($indexFolder, '/') && !str_contains($indexFolder, '://')) {
            $indexFolder = GRAV_WEBROOT . '/' . $indexFolder;
        }

        return MediaIndex::getInstance($indexFolder . '/' . $this->indexFile);
    }

    /**
     * @return array
     */
    protected function loadIndex(): array
    {
        // Read media index file.
        $mediaIndex = $this->getIndex();
        if (!$mediaIndex || !$this->exists) {
            return [[], 0];
        }

        $id = $this->getId();
        [$index, $modified] = $mediaIndex->get($id);

        $version = $index['version'] ?? null;
        $folder = $index['folder'] ?? null;
        $type = $index['type'] ?? null;
        if ($version !== static::VERSION || $folder !== $this->path || $type !== ($this->config['type'] ?? 'local')) {
            return [[], 0];
        }

        return [$index, $modified];
    }

    /**
     * @param int|null $timestamp
     * @return void
     */
    protected function touchIndex(?int $timestamp = null): void
    {
        $mediaIndex = $this->getIndex();
        if (!$mediaIndex || !$this->exists) {
            return;
        }
        $id = $this->getId();

        $mediaIndex->touch($id, $timestamp);
    }

    /**
     * @param array $files
     * @param string|null $checksum
     * @param int|null $timestamp
     * @return void
     */
    protected function saveIndex(array $files, string $checksum = null, ?int $timestamp = null): void
    {
        $mediaIndex = $this->getIndex();
        if (!$mediaIndex || !$this->exists) {
            return;
        }

        $id = $this->getId();
        $index = $this->generateIndex($files, $checksum, $timestamp);

        $mediaIndex->save($id, $index);
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

    /**
     * Call object as function to get medium by filename.
     *
     * @param string $filename
     * @return MediaObjectInterface|null
     * @deprecated 1.8 Use $media[$filename] instead
     */
    public function __invoke(string $filename): ?MediaObjectInterface
    {
        user_error(__METHOD__ . '() is deprecated since Grav 1.8, use $media[$filename] instead', E_USER_DEPRECATED);

        return $this->offsetGet($filename);
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
