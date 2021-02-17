<?php

/**
 * @package    Grav\Framework\Session
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Session;

use Grav\Framework\Compat\Serializable;
use function array_key_exists;

/**
 * Implements session messages.
 */
class Messages implements \Serializable
{
    use Serializable;

    /** @var array */
    protected $messages = [];
    /** @var bool */
    protected $isCleared = false;

    /**
     * Add message to the queue.
     *
     * @param string $message
     * @param string $scope
     * @return $this
     */
    public function add(string $message, string $scope = 'default'): Messages
    {
        $key = md5($scope . '~' . $message);
        $item = ['message' => $message, 'scope' => $scope];

        // don't add duplicates
        if (!array_key_exists($key, $this->messages)) {
            $this->messages[$key] = $item;
        }

        return $this;
    }

    /**
     * Clear message queue.
     *
     * @param string|null $scope
     * @return $this
     */
    public function clear(string $scope = null): Messages
    {
        if ($scope === null) {
            if ($this->messages !== []) {
                $this->isCleared = true;
                $this->messages = [];
            }
        } else {
            foreach ($this->messages as $key => $message) {
                if ($message['scope'] === $scope) {
                    $this->isCleared = true;
                    unset($this->messages[$key]);
                }
            }
        }

        return $this;
    }

    /**
     * @return bool
     */
    public function isCleared(): bool
    {
        return $this->isCleared;
    }

    /**
     * Fetch all messages.
     *
     * @param string|null $scope
     * @return array
     */
    public function all(string $scope = null): array
    {
        if ($scope === null) {
            return array_values($this->messages);
        }

        $messages = [];
        foreach ($this->messages as $message) {
            if ($message['scope'] === $scope) {
                $messages[] = $message;
            }
        }

        return $messages;
    }

    /**
     * Fetch and clear message queue.
     *
     * @param string|null $scope
     * @return array
     */
    public function fetch(string $scope = null): array
    {
        $messages = $this->all($scope);
        $this->clear($scope);

        return $messages;
    }

    /**
     * @return array
     */
    public function __serialize(): array
    {
        return [
          'messages' => $this->messages
        ];
    }

    /**
     * @param array $data
     * @return void
     */
    public function __unserialize(array $data): void
    {
        $this->messages = $data['messages'];
    }
}
