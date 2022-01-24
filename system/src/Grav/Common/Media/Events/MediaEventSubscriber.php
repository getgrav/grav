<?php declare(strict_types=1);

/**
 * @package    Grav\Common\Media
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Media\Events;

use Grav\Common\Media\Factories\FolderMediaFactory;
use Grav\Common\Media\Factories\MediaFactory;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 *
 */
class MediaEventSubscriber implements EventSubscriberInterface
{
    /**
     * @return array[]
     */
    public static function getSubscribedEvents(): array
    {
        return [
            MediaFactory::class => ['onMediaFactoryInit', 10]
        ];
    }

    /**
     * @param MediaFactory $factory
     * @return void
     */
    public function onMediaFactoryInit(MediaFactory $factory): void
    {
        $factory->register(new FolderMediaFactory());
    }
}
