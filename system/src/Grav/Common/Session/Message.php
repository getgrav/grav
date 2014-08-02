<?php
namespace Grav\Common\Session;

use Grav\Common\Getters;

/**
 * Session wide messages.
 *
 * @author RocketTheme
 * @license MIT
 */
class Message
{
    /**
     * @var array|string[]
     */
    protected $messages = array();

    /**
     * Add message to the queue.
     *
     * @param string $message
     * @param string $scope
     * @return $this
     */
    public function add($message, $scope = 'default')
    {
        $message = array('message' => $message, 'scope' => $scope);

        $this->messages[] = $message;

        return $this;
    }

    /**
     * Clear message queue.
     *
     * @param string $scope
     * @return $this
     */
    public function clear($scope = null)
    {
        if ($scope === null) {
            $this->messages = array();
        } else {
            foreach ($this->messages as $key => $message) {
                if ($message['scope'] == $scope) {
                    unset($this->messages[$key]);
                }
            }
        }
        return $this;
    }

    /**
     * Fetch all messages.
     *
     * @param string $scope
     * @return array
     */
    public function all($scope = null)
    {
        if ($scope === null) {
            return array_values($this->messages);
        }

        $messages = array();
        foreach ($this->messages as $message) {
            if ($message['scope'] == $scope) {
                $messages[] = $message;
            }
        }

        return $messages;
    }

    /**
     * Fetch and clear message queue.
     *
     * @param string $scope
     * @return array
     */
    public function fetch($scope = null)
    {
        $messages = $this->all($scope);
        $this->clear($scope);

        return $messages;
    }

}
