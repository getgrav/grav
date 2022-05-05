<?php

/**
 * @package    Grav\Common\Media
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Media\Traits;

use Grav\Common\Cache;
use Grav\Common\Grav;
use Grav\Common\Media\Factories\MediaFactory;
use Grav\Common\Media\Interfaces\MediaCollectionInterface;
use Grav\Common\Page\Media;
use Grav\Common\Utils;
use Psr\SimpleCache\CacheInterface;
use RocketTheme\Toolbox\Event\Event;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;
use Throwable;
use function array_key_exists;
use function in_array;
use function strlen;

/**
 * Adds methods to fetch media from an object.
 *
 * Supports both object and field based media.
 */
trait MediaTrait
{
    /** @var MediaCollectionInterface|null */
    protected $media;
    /** @var bool */
    protected $_loadMedia = true;
    /** @var array */
    protected $_mediaSettings = [];

    /**
     * Get filesystem path to the associated media.
     *
     * @return string|null
     */
    abstract public function getMediaFolder();

    /**
     * Get display order for the associated media.
     *
     * @return array Empty array means default ordering.
     */
    public function getMediaOrder()
    {
        return [];
    }

    /**
     * Get URI ot the associated media. Method will return null if path isn't URI.
     *
     * @return string|null
     */
    public function getMediaUri()
    {
        $folder = $this->getMediaFolder();
        if (!$folder) {
            return null;
        }

        if (strpos($folder, '://')) {
            return $folder;
        }

       /** @var UniformResourceLocator $locator */
        $locator = Grav::instance()['locator'];
        $user = $locator->findResource('user://');
        if (strpos($folder, $user) === 0) {
            return 'user://' . substr($folder, strlen($user)+1);
        }

        return null;
    }

    /**
     * Gets the associated media collection.
     *
     * @return MediaCollectionInterface|Media  Representation of associated media.
     */
    public function getMedia()
    {
        $media = $this->media;
        if (null === $media) {
            $cache = $this->getMediaCache();
            $cacheKey = md5('media' . $this->getCacheKey());

            try {
                // Use cached media if possible.
                $media = $cache->get($cacheKey);
            } catch (Throwable $e) {
            }

            if (!$media instanceof MediaCollectionInterface) {
                $settings = $this->getMediaSettings();

                /** @var MediaFactory $factory */
                $factory = Grav::instance()['media_factory'];
                $media = $factory->createCollection($settings);
                if (!$media) {
                    $media = new Media('', []);
                }

                $cache->set($cacheKey, $media);
            }

            $this->media = $media;
        }

        return $media;
    }

    /**
     * @param string $field
     * @return MediaCollectionInterface|null
     */
    public function getMediaField(string $field): ?MediaCollectionInterface
    {
        if (!method_exists($this, 'getMediaFieldSettings')) {
            return null;
        }

        // Field specific media.
        $settings = $this->getMediaFieldSettings($field);
        if (!empty($settings['media_field'])) {
            $var = 'destination';
        } elseif (!empty($settings['media_picker_field'])) {
            $var = 'folder';
        }

        if (empty($var)) {
            // Not a media field.
            $media = null;
        } elseif ($settings['self']) {
            // Uses main media.
            $media = $this->getMedia();
        } else {
            /** @var MediaFactory $factory */
            $factory = Grav::instance()['media_factory'];

            $path = $settings[$var] ?? null;
            $params = $settings['media'] ?? [];
            $params += [
                'object' => $this,
                'path' => $path,
                'load' => true,
                'field' => $settings
            ];

            // Uses custom media.
            $media = $factory->createCollection($params);

            // From media upload interface.
            if ($media && method_exists($this, 'addUpdatedMedia')) {
                $this->addUpdatedMedia($media);
            }
        }

        return $media;
    }

    /**
     * @return string[]
     */
    public function getMediaFields(): array
    {
        // Load settings for the field.
        $schema = $this->getBlueprint()->schema();
        $list = [];
        if (null !== $schema) {
            foreach ($schema->getState()['items'] as $field => $settings) {
                if (isset($settings['type']) && (in_array($settings['type'], ['avatar', 'file', 'pagemedia']) || !empty($settings['destination']))) {
                    $list[] = $field;
                }
            }
        }

        return $list;
    }

    /**
     * Get media settings.
     *
     * @return array
     */
    protected function getMediaSettings(): array
    {
        return [
            'object' => $this,
            'path' => $this->getMediaFolder(),
            'order' => $this->getMediaOrder(),
            'load' => $this->_loadMedia,
        ];
    }

    /**
     * @param string $field
     * @param array|null $settings
     * @return array|null
     */
    protected function parseMediaFieldSettings(string $field, ?array $settings): ?array
    {
        if (!isset($this->_mediaSettings[$field])) {
            if (!$settings) {
                return null;
            }

            $type = $settings['type'] ?? '';

            // Media field.
            if (!empty($settings['media_field']) || array_key_exists('destination', $settings) || in_array($type, ['avatar', 'file', 'pagemedia'], true)) {
                $settings['media_field'] = true;
                $var = 'destination';
            }

            // Media picker field.
            if (!empty($settings['media_picker_field']) || in_array($type, ['filepicker', 'pagemediaselect'], true)) {
                $settings['media_picker_field'] = true;
                $var = 'folder';
            }

            $this->_mediaSettings[$field] = null;

            // Set media folder for media fields.
            if (isset($var)) {
                $settings['type'] = 'local';
                $token = $settings[$var] ?? '';
                if (in_array(rtrim($token, '/'), ['', '@self', 'self@', '@self@'], true)) {
                    $settings += $this->getMediaSettings();
                    $settings['self'] = true;
                } else {
                    /** @var string|null $uri */
                    $uri = null;
                    $event = new Event([
                        'token' => $token,
                        'object' => $this,
                        'field' => $field,
                        'type' => $type,
                        'settings' => &$settings, // Value can be changed.
                        'uri' => &$uri // Value will be set to here.
                    ]);

                    Grav::instance()->fireEvent('onGetMediaFieldSettings', $event);

                    $settings[$var] = $uri ?? Utils::getPathFromToken($token, $this);
                    $settings['self'] = false;
                }

                $this->_mediaSettings[$field] = $settings + ['accept' => '*', 'limit' => 1000, 'self' => true];
            }
        }

        return $this->_mediaSettings[$field];
    }

    /**
     * Sets the associated media collection.
     *
     * @param  MediaCollectionInterface|Media  $media Representation of associated media.
     * @return $this
     */
    protected function setMedia(MediaCollectionInterface $media)
    {
        $cache = $this->getMediaCache();
        $cacheKey = md5('media' . $this->getCacheKey());
        $cache->set($cacheKey, $media);

        $this->media = $media;

        return $this;
    }

    /**
     * @return void
     */
    protected function freeMedia()
    {
        $this->media = null;
    }

    /**
     * Clear media cache.
     *
     * @return void
     */
    protected function clearMediaCache()
    {
        $cache = $this->getMediaCache();
        $cacheKey = md5('media' . $this->getCacheKey());
        $cache->delete($cacheKey);

        $this->freeMedia();
    }

    /**
     * @return CacheInterface
     */
    protected function getMediaCache()
    {
        /** @var Cache $cache */
        $cache = Grav::instance()['cache'];

        return $cache->getSimpleCache();
    }

    /**
     * @return string
     */
    abstract protected function getCacheKey(): string;
}
