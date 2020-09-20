<?php

/**
 * @package    Grav\Common\Twig
 *
 * @copyright  Copyright (C) 2015 - 2020 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Twig;

use Grav\Common\Utils;
use Twig\Profiler\Profile;
use Clockwork\Request\Timeline;

/**
 * Class TwigProfileProcessor
 * @package Grav\Common\Twig
 */
class TwigProfileProcessor
{
    /** @var float */
    private $root;

    /**
     * @param Profile $profile
     * @param Timeline $views
     * @param int $counter
     * @param string $prefix
     * @param false $sibling
     * @return void
     */
    public function process(Profile $profile, Timeline $views, $counter = 0, $prefix = '', $sibling = false)
    {
        if ($profile->isRoot()) {
            $this->root = $profile->getDuration();
            $name = $profile->getName();
        } else {
            if ($profile->isTemplate()) {
                $name = $this->formatTemplate($profile, $prefix);
            } else {
                $name = $this->formatNonTemplate($profile, $prefix);
            }
            $prefix .= '⎯⎯';
        }

        $percent = $this->root ? $profile->getDuration() / $this->root * 100 : 0;

        $data = [
            'tm' => $this->formatTime($profile, $percent),
            'mu' => Utils::prettySize($profile->getMemoryUsage())
        ];

        if ($profile->isRoot()) {
            $data += ['pmu' => Utils::prettySize($profile->getPeakMemoryUsage())];
        }


        $views->addEvent(
            $counter,
            $profile->getTemplate(),
            0,
            $profile->getDuration(),
            [ 'name' => $name, 'data' => $data ]
        );

        $nCount = count($profile->getProfiles());
        foreach ($profile as $i => $p) {
            $this->process($p, $views, ++$counter, $prefix, $i + 1 !== $nCount);
        }
    }

    /**
     * @param Profile $profile
     * @param string $prefix
     * @return string
     */
    protected function formatTemplate(Profile $profile, $prefix)
    {
        return sprintf('%s⤍ %s', $prefix, $profile->getTemplate());
    }

    /**
     * @param Profile $profile
     * @param string $prefix
     * @return string
     */
    protected function formatNonTemplate(Profile $profile, $prefix)
    {
        return sprintf('%s⤍ %s::%s(%s)', $prefix, $profile->getTemplate(), $profile->getType(), $profile->getName());
    }

    /**
     * @param Profile $profile
     * @param float $percent
     * @return string
     */
    protected function formatTime(Profile $profile, $percent)
    {
        return sprintf('%.2fms/%.0f%%', $profile->getDuration() * 1000, $percent);
    }
}
