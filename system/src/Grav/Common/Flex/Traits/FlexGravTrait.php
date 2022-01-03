<?php

declare(strict_types=1);

/**
 * @package    Grav\Common\Flex
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Flex\Traits;

use Grav\Common\Grav;
use Grav\Common\User\Interfaces\UserInterface;
use Grav\Framework\Flex\Flex;

/**
 * Implements Grav specific logic
 */
trait FlexGravTrait
{
    /**
     * @return Grav
     */
    protected function getContainer(): Grav
    {
        return Grav::instance();
    }

    /**
     * @return Flex
     */
    protected function getFlexContainer(): Flex
    {
        $container = $this->getContainer();

        /** @var Flex $flex */
        $flex = $container['flex'];

        return $flex;
    }

    /**
     * @return UserInterface|null
     */
    protected function getActiveUser(): ?UserInterface
    {
        $container = $this->getContainer();

        /** @var UserInterface|null $user */
        $user = $container['user'] ?? null;

        return $user;
    }

    /**
     * @return bool
     */
    protected function isAdminSite(): bool
    {
        $container = $this->getContainer();

        return isset($container['admin']);
    }

    /**
     * @return string
     */
    protected function getAuthorizeScope(): string
    {
        return $this->isAdminSite() ? 'admin' : 'site';
    }
}
