<?php

namespace Grav\Plugin\EventList;

class Event
{
    /** @var \DateTime */
    public $start_time;

    /** @var \DateTime */
    public $end_time;

    /** @var string */
    public $summary;

    /**
     * Constructor
     *
     * @param \DateTime $start_time
     * 
     * @param \DateTime $end_time
     * 
     * @param string $summary
     */
    public function __construct($start_time, $end_time, $summary)
    {
        $this->start_time = $start_time;
        $this->end_time = $end_time;
        $this->summary = $summary;
    }

    /**
     * Compare two events
     *
     * @param Event $event_1
     * 
     * @param Event $event_2
     * 
     * @return bool
     */
    public static function compare($event_1, $event_2)
    {
        // @TODO Maybe we should just compare the date, not the time
        if ($event_1->start_time < $event_2->start_time)
            return -1;
        else if ($event_1->start_time > $event_2->start_time)
            return +1;

        if ($event_1->end_time < $event_2->end_time)
            return -1;
        else if ($event_1->end_time > $event_2->end_time)
            return +1;
        else return 0;
    }
}
