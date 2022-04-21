<?php declare(strict_types=1);

/**
 * @package    Grav\Common\Media
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Media\Factories;

use Grav\Common\Grav;
use Grav\Common\Media\Events\MediaEventSubscriber;
use Grav\Common\Media\Interfaces\MediaCollectionInterface;
use Grav\Common\Media\Interfaces\MediaFactoryInterface;
use RuntimeException;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Factory class for creating media collections.
 */
final class MediaFactory implements MediaFactoryInterface
{
    /** @var MediaFactoryInterface[] */
    private $collectionTypes = [];

    /**
     * @param Grav $grav
     */
    public function __construct(Grav $grav)
    {
        /** @var EventDispatcher $dispatcher */
        $dispatcher = $grav['events'];
        $dispatcher->addSubscriber(new MediaEventSubscriber());

        $grav->dispatchEvent($this);
    }

    /**
     * @param MediaFactoryInterface $factory
     * @return void
     */
    public function register(MediaFactoryInterface $factory): void
    {
        foreach ($factory->getCollectionTypes() as $type) {
            $this->collectionTypes[$type] = $factory;
        }
    }

    /**
     * @param string $type
     * @param MediaFactoryInterface $factory
     * @return void
     */
    public function registerCollectionType(string $type, MediaFactoryInterface $factory): void
    {
        $this->collectionTypes[$type] = $factory;
    }

    /**
     * @param string $type
     * @return void
     */
    public function unregisterCollectionType(string $type): void
    {
        unset($this->collectionTypes[$type]);
    }

    /**
     * @param string $type
     * @return bool
     */
    public function hasCollectionType(string $type): bool
    {
        return isset($this->collectionTypes[$type]);
    }

    /**
     * @return string[]
     */
    public function getCollectionTypes(): array
    {
        return array_keys($this->collectionTypes);
    }

    /**
     * @param array $settings
     * @return MediaCollectionInterface|null
     */
    public function createCollection(array $settings): ?MediaCollectionInterface
    {
        $type = $settings['type'] ?? 'local';
        $factory = $this->collectionTypes[$type] ?? null;
        if ($factory) {
            return $factory->createCollection($settings);
        }

        return null;
    }

    /**
     * @param string $uri
     * @return string
     */
    public function readFile(string $uri): string
    {
        if (preg_match('{^(?:media-([^:]+)://)?(.*)$}', $uri, $matches)) {
            $type = str_replace('-', '_', $matches[1]);
            $filepath = $matches[2];
        } else {
            $type = 'local';
            $filepath = $uri;
        }

        $factory = $this->collectionTypes[$type] ?? null;
        if ($factory) {
            return $factory->readFile($type, $filepath);
        }

        throw new RuntimeException(sprintf('Reading media file failed: type %s does not exist', $type), 500);
    }

    /**
     * @param string $uri
     * @return resource
     */
    public function readStream(string $uri)
    {
        if (preg_match('{^(?:media-([^:]+)://)?(.*)$}', $uri, $matches)) {
            $type = str_replace('-', '_', $matches[1]);
            $filepath = $matches[2];
        } else {
            $type = 'local';
            $filepath = $uri;
        }

        $factory = $this->collectionTypes[$type] ?? null;
        if ($factory) {
            return $factory->readStream($type, $filepath);
        }

        throw new RuntimeException(sprintf('Reading media file failed: type %s does not exist', $type), 500);
    }
}
