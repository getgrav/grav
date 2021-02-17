<?php

/**
 * @package    Grav\Common\Twig
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Twig;

use Clockwork\Request\Timeline\Timeline;
use Twig\Profiler\Profile;

/**
 * Class TwigClockworkDumper
 * @package Grav\Common\Twig
 */
class TwigClockworkDumper
{
    protected $lastId = 1;

    /**
     * Dumps a profile into a new rendered views timeline
     *
     * @param Profile $profile
     * @return Timeline
     */
    public function dump(Profile $profile)
    {
        $timeline = new Timeline;

        $this->dumpProfile($profile, $timeline);

        return $timeline;
    }

    /**
     * @param Profile $profile
     * @param Timeline $timeline
     * @param null $parent
     */
    public function dumpProfile(Profile $profile, Timeline $timeline, $parent = null)
    {
        $id = $this->lastId++;

        if ($profile->isRoot()) {
            $name = $profile->getName();
        } elseif ($profile->isTemplate()) {
            $name = $profile->getTemplate();
        } else {
            $name = $profile->getTemplate() . '::' . $profile->getType() . '(' . $profile->getName() . ')';
        }

        foreach ($profile as $p) {
            $this->dumpProfile($p, $timeline, $id);
        }

        $data = $profile->__serialize();

        $timeline->event($name, [
            'name'  => $id,
            'start' => $data[3]['wt'] ?? null,
            'end'   => $data[4]['wt'] ?? null,
            'data'  => [
                'data'        => [],
                'memoryUsage' => $data[4]['mu'] ?? null,
                'parent'      => $parent
            ]
        ]);
    }
}
