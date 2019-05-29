<?php

/**
 * @package    Grav\Common\Twig
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Twig;

use Twig\Profiler\Profile;
use Clockwork\Request\Timeline;

class TwigProfileProcessor
{
    private $root;

    public function process(Profile $profile, Timeline $views, $counter = 0)
    {

        if ($profile->isRoot()) {
            $this->root = $profile->getDuration();
            $name = $profile->getName();
        } else {
            if ($profile->isTemplate()) {
                $name = $profile->getTemplate();
            } else {
                $name = $this->formatNonTemplate($profile);
            }
        }

        $percent = $this->root ? $profile->getDuration() / $this->root * 100 : 0;


        $data = [$this->formatTime($profile, $percent)];


        $views->addEvent(
            $counter,
            $profile->getTemplate(),
            0,
            $profile->getDuration(),
            [ 'name' => $name, 'data' => $data ]
        );

        foreach ($profile as $i => $p) {
            $this->process($p, $views, ++$counter);
        }


    }

    protected function formatNonTemplate(Profile $profile)
    {
        return sprintf('%s::%s(%s)', $profile->getTemplate(), $profile->getType(), $profile->getName());
    }

    protected function formatTime(Profile $profile, $percent)
    {
        return sprintf('%.2fms/%.0f%%', $profile->getDuration() * 1000, $percent);
    }
}
