<?php

/**
 * @package    Grav\Framework\Logger
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Logger\Processors;

use Grav\Common\Grav;
use Grav\Common\User\Interfaces\UserInterface;
use Monolog\Processor\ProcessorInterface;

/**
 * Adds username and email to log messages.
 */
class UserProcessor implements ProcessorInterface
{
    /**
     * {@inheritDoc}
     */
    public function __invoke(array $record): array
    {
        /** @var UserInterface|null $user */
        $user = Grav::instance()['user'] ?? null;
        if ($user && $user->exists()) {
            $record['extra']['user'] = ['username' => $user->username, 'email' => $user->email];
        }

        return $record;
    }
}
