<?php

/**
 * @package    Grav\Common\Page
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Page\Medium;

use Grav\Common\Grav;
use Grav\Common\Media\Interfaces\MediaObjectInterface;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;
use function dirname;

/**
 * Class GlobalMedia
 * @package Grav\Common\Page\Medium
 */
class GlobalMedia extends AbstractMedia
{
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
        /** @var UniformResourceLocator $locator */
        $locator = Grav::instance()['locator'];
        if (!$locator->isStream($filename)) {
            return null;
        }

        return $locator->findResource($filename) ?: null;
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
        [$basename, $ext,, $extra] = $this->getFileParts(basename($filename));
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
