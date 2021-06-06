<?php

namespace Grav\Plugin\EventList;

use Grav\Common\Grav;
use Sabre\VObject;

class Events
{
    /** @var Grav */
    protected $grav;

    /**
     * Constructor
     *
     * @param Grav $grav
     */
    public function __construct(Grav $grav)
    {
        $this->grav = $grav;
    }

    private function get_path($filename)
    {
        $locator = $this->grav["locator"];
        return $locator->findResource('user://data/calendars/' . $filename);
    }

    /**
     * Parses a file and return the events
     * 
     * @return Event[]
     */
    private function parse($file)
    {
        $vcalendar = VObject\Reader::read(
            fopen($file, 'r'),
            VObject\Reader::OPTION_FORGIVING
        );
        $events = array();

        $current_date = new \DateTime();

        foreach ($vcalendar->VEVENT as $event) {
            $start_date = $event->DTSTART->getDateTime();

            if ($start_date >= $current_date)
                $events[(string)$event->UID] = new Event(
                    $start_date,
                    $event->DTEND->getDateTime(),
                    (string)$event->SUMMARY
                );
        }


        uasort($events, 'Grav\Plugin\EventList\Event::compare');

        return $events;
    }

    public function get_events($filename)
    {
        $path = $this->get_path($filename);
        return $this->parse($path);
    }
}
