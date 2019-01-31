<?php
/**
 * @package    Grav.Common.Errors
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Errors;

use Whoops\Handler\Handler;

class BareHandler extends Handler
{

    /**
     * @return int|null
     */
    public function handle()
    {
        $inspector = $this->getInspector();
        $code = $inspector->getException()->getCode();
        if ( ($code >= 400) && ($code < 600) )
        {
            $this->getRun()->sendHttpCode($code);    
        }

        return Handler::QUIT;
    }

}
