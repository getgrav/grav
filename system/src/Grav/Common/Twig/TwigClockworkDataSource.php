<?php

/**
 * @package    Grav\Common\Twig
 *
 * @copyright  Copyright (C) 2015 - 2020 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Twig;

use Clockwork\DataSource\DataSource;
use Clockwork\Request\Request;
use Clockwork\Request\Timeline;
use Grav\Common\Grav;

/**
 * Class TwigClockworkDataSource
 * @package Grav\Common\Twig
 */
class TwigClockworkDataSource extends DataSource
{
    /** @var Timeline Views data structure */
    protected $views;

    /**
     * TwigClockworkDataSource constructor.
     */
    public function __construct()
    {
        $this->views = new Timeline();
    }

    /**
     * Resolves and adds the Twig profiler data to the request
     *
     * @param Request $request
     * @return Request
     */
    public function resolve(Request $request)
    {
        $profile = Grav::instance()['twig']->profile();

        if ($profile) {
            $processor = new TwigProfileProcessor();

            $processor->process($profile, $this->views);
            $request->viewsData    = $this->views->finalize();
        }

        return $request;
    }
}
