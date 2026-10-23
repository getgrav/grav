<?php

/**
 * @package    Grav\Common\Page
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Page\Medium;

use Grav\Common\Grav;
use Grav\Common\Media\Interfaces\MediaObjectInterface;
use Grav\Common\Utils;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;
use function dirname;

/**
 * Class GlobalMedia
 * @package Grav\Common\Page\Medium
 *
 * NOTE: GlobalMedia resolves media lazily, one stream at a time via
 * {@see offsetGet()}/{@see addMedium()}; it never enumerates `user://media`
 * into `$items`. Because of that, the collection-level query methods it inherits
 * from {@see AbstractMedia} — filterBy()/where()/findBy()/sortBy()/groupBy()/
 * withMeta() — all operate on an empty item set and silently return nothing.
 * To filter or sort site media as a collection, build a concrete
 * {@see \Grav\Common\Page\Media} over the folder instead, e.g.
 * `media_directory('user://media')`, and query that.
 */
class GlobalMedia extends AbstractMedia
{
    /**
     * Folders page content may take media from by stream URI, as stream URIs.
     * The default for `security.media.allowed_roots`.
     */
    public const ALLOWED_ROOTS = [
        'page://', 'image://', 'media://', 'theme://', 'themes://', 'plugins://',
        'asset://', 'system://images', 'system://assets',
    ];

    /** @var self */
    protected static $instance;

    public static function getInstance(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Return media path.
     *
     * @return string|null
     */
    public function getPath(): ?string
    {
        return null;
    }

    /**
     * @param string $offset
     * @return bool
     */
    #[\ReturnTypeWillChange]
    public function offsetExists($offset)
    {
        return parent::offsetExists($offset) ?: !empty($this->resolveStream($offset));
    }

    /**
     * @param string $offset
     * @return MediaObjectInterface|null
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return parent::offsetGet($offset) ?: $this->addMedium($offset);
    }

    /**
     * @param string $filename
     * @return string|null
     */
    protected function resolveStream($filename)
    {
        return is_string($filename) ? static::locate($filename) : null;
    }

    /**
     * Resolve a stream URI written in page content (a markdown image,
     * `media['...']`, the watermark action's image) to a file in one of the
     * media folders, or null.
     *
     * Every stream resolves inside a root of its own, but that says nothing
     * about whether the file is media: the `user://` and `config://` roots also
     * hold accounts, configuration and site data, and `tmp://`, `cache://`,
     * `log://` and `backup://` are never content. So the resolved file must lie
     * inside a folder listed in `security.media.allowed_roots`, compared by
     * real path so `..` and symlinks cannot step out of it.
     *
     * @param string $uri
     * @return string|null
     */
    public static function locate(string $uri): ?string
    {
        /** @var UniformResourceLocator $locator */
        $locator = Grav::instance()['locator'];
        if (!$locator->isStream($uri)) {
            return null;
        }

        $filename = $locator->findResource($uri);

        return $filename && static::isMediaPath($filename) ? $filename : null;
    }

    /**
     * Whether an existing file or folder lies inside one of the media folders
     * in `security.media.allowed_roots`.
     *
     * @param string $path
     * @return bool
     */
    public static function isMediaPath(string $path): bool
    {
        $realPath = realpath($path);
        if ($realPath === false) {
            return false;
        }

        $grav = Grav::instance();
        /** @var UniformResourceLocator $locator */
        $locator = $grav['locator'];
        $roots = $grav['config']->get('security.media.allowed_roots') ?? self::ALLOWED_ROOTS;

        foreach ((array) $roots as $root) {
            if (!is_string($root) || !$locator->isStream($root)) {
                continue;
            }
            foreach ($locator->findResources($root, true, true) as $folder) {
                $realRoot = realpath($folder);
                if ($realRoot === false) {
                    continue;
                }
                // The trailing separator is essential. Without it `user/pages`
                // would prefix-match `user/pages-private`.
                $realRoot = rtrim($realRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
                if (strncmp($realPath . DIRECTORY_SEPARATOR, $realRoot, strlen($realRoot)) === 0) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param string $stream
     * @return MediaObjectInterface|null
     */
    protected function addMedium($stream)
    {
        $filename = $this->resolveStream($stream);
        if (!$filename) {
            return null;
        }

        $path = dirname($filename);
        [$basename, $ext,, $extra] = $this->getFileParts(Utils::basename($filename));
        $medium = MediumFactory::fromFile($filename);

        if (null === $medium) {
            return null;
        }

        $medium->set('size', filesize($filename));
        $scale = (int) ($extra ?: 1);

        if ($scale !== 1) {
            $altMedium = $medium;

            // Create scaled down regular sized image.
            $medium = MediumFactory::scaledFromMedium($altMedium, $scale, 1)['file'];

            if (empty($medium)) {
                return null;
            }

            // Add original sized image as alternative.
            $medium->addAlternative($scale, $altMedium['file']);

            // Locate or generate smaller retina images.
            for ($i = $scale-1; $i > 1; $i--) {
                $altFilename = "{$path}/{$basename}@{$i}x.{$ext}";

                if (file_exists($altFilename)) {
                    $scaled = MediumFactory::fromFile($altFilename);
                } else {
                    $scaled = MediumFactory::scaledFromMedium($altMedium, $scale, $i)['file'];
                }

                if ($scaled) {
                    $medium->addAlternative($i, $scaled);
                }
            }
        }

        $meta = "{$path}/{$basename}.{$ext}.yaml";
        if (file_exists($meta)) {
            $medium->addMetaFile($meta);
        }
        $meta = "{$path}/{$basename}.{$ext}.meta.yaml";
        if (file_exists($meta)) {
            $medium->addMetaFile($meta);
        }

        $thumb = "{$path}/{$basename}.thumb.{$ext}";
        if (file_exists($thumb)) {
            $medium->set('thumbnails.page', $thumb);
        }

        $this->add($stream, $medium);

        return $medium;
    }
}
