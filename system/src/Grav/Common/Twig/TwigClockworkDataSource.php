<?php

/**
 * @package    Grav\Common\Twig
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Twig;

use Clockwork\DataSource\DataSource;
use Clockwork\Helpers\Serializer;
use Clockwork\Request\Request;
use Clockwork\Request\Timeline;

class TwigClockworkDataSource extends DataSource
{
    /**
     * Views data structure
     */
    protected $views;

    /**
     * Create a new data source, takes Twig instance as an argument
     */
    public function __construct($twig)
    {
        $this->views = new Timeline();
    }

    /**
     * Adds twig data to the request
     */
    public function resolve(Request $request)
    {
        $this->processTwigTimings();

        $request->viewsData    = $this->views->finalize();

        return $request;
    }

    protected function processTwigTimings()
    {
        $time = 10;

        $data = ['foo', 'bar'];

        $this->views->addEvent(
            'view Test',
            'Rendering a view',
            $time,
            $time + 10,
            [ 'name' => 'Test Name', 'data' => (new Serializer)->normalize($data) ]
        );
    }
}
