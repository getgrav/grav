<?php

/**
 * @package    Grav\Common\Data
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Data;

use Grav\Common\Grav;

class ValidationException extends \RuntimeException
{
    protected $messages = [];

    public function setMessages(array $messages = []) {
        $this->messages = $messages;

        $language = Grav::instance()['language'];
        $this->message = $language->translate('GRAV.FORM.VALIDATION_FAIL', null, true) . ' ' . $this->message;

        foreach ($messages as $variable => &$list) {
            $list = array_unique($list);
            foreach ($list as $message) {
                $this->message .= "<br/>$message";
            }
        }

        return $this;
    }

    public function getMessages()
    {
        return $this->messages;
    }
}
