<?php

/**
 * @package    Grav\Common\Twig
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Twig;

use Clockwork\DataSource\DataSource;
use Clockwork\Request\Request;
use Twig\Environment;
use Twig\Extension\ProfilerExtension;
use Twig\Profiler\Profile;

/**
 * Class TwigClockworkDataSource
 * @package Grav\Common\Twig
 */
class TwigClockworkDataSource extends DataSource
{
    /** @var Environment */
    protected $twig;

    /** @var Profile */
    protected $profile;

    // Create a new data source, takes Twig instance as an argument
    public function __construct(Environment $twig)
    {
        $this->twig = $twig;
    }

    /**
     * Register the Twig profiler extension
     */
    public function listenToEvents(): void
    {
        $this->twig->addExtension(new ProfilerExtension($this->profile = new Profile()));
    }

    /**
     * Adds rendered views to the request
     *
     * @param Request $request
     * @return Request
     */
    public function resolve(Request $request)
    {
        $timeline = (new TwigClockworkDumper())->dump($this->profile);

        $request->viewsData = array_merge($request->viewsData, $timeline->finalize());

        return $request;
    }
}
